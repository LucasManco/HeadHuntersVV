# Proposta de modelo de dados relacional — Centelhas Commander

> Observação: o regulamento é a fonte da verdade. Este documento propõe um esquema relacional normalizado (em estilo PostgreSQL) para garantir integridade e rastreabilidade, atendendo às regras de centelhas, mesas, eliminações e auditoria.

## Visão geral (entidades principais)

- **Event** (Season)
- **Player**
- **EventPlayer** (inscrição do jogador no evento)
- **Ledger** (transações append-only de centelhas)
- **Table/Match** (mesa/partida)
- **TablePlayer** (participação na mesa, comandante e aposta)
- **Eliminação/Scoop** (campos no TablePlayer)
- **AuditLog** (ações administrativas — append-only)

## Enumerações e tipos auxiliares

```sql
-- Fonte (origem) das transações no ledger
CREATE TYPE ledger_source_type AS ENUM (
  'event_initial_balance',   -- carga inicial do evento
  'table_buy_in',            -- travar aposta/entrada na mesa (bloqueio)
  'elimination_transfer',    -- transferência de aposta por eliminação
  'scoop_transfer',          -- transferência de aposta por scoop
  'table_rollback',          -- rollback de mesa (estorno/ajuste)
  'bank_purchase',           -- compra na banca
  'admin_adjustment',        -- ajuste manual (positivo ou negativo)
  'prize_withdrawal'         -- premiação (redução manual)
);

CREATE TYPE table_status AS ENUM ('draft', 'started', 'finished', 'rolled_back', 'void');
```

## Tabelas

### 1) Event
```sql
CREATE TABLE events (
  id                 BIGSERIAL PRIMARY KEY,
  name               TEXT NOT NULL,
  starts_at          TIMESTAMPTZ NOT NULL,
  ends_at            TIMESTAMPTZ,
  initial_centelhas  BIGINT NOT NULL CHECK (initial_centelhas >= 0),
  created_by_admin_id BIGINT NOT NULL,
  created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_events_dates ON events (starts_at, ends_at);
```

### 2) Player
```sql
CREATE TABLE players (
  id          BIGSERIAL PRIMARY KEY,
  display_name TEXT NOT NULL,
  email       TEXT UNIQUE,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_players_display_name ON players (display_name);
```

### 3) EventPlayer (inscrição)
```sql
CREATE TABLE event_players (
  id          BIGSERIAL PRIMARY KEY,
  event_id    BIGINT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  player_id   BIGINT NOT NULL REFERENCES players(id) ON DELETE CASCADE,
  joined_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  is_active   BOOLEAN NOT NULL DEFAULT TRUE,

  -- saldo derivado do ledger (pode ser materializado em view/coluna gerenciada)
  current_balance BIGINT,

  UNIQUE (event_id, player_id)
);

CREATE INDEX idx_event_players_event ON event_players (event_id);
CREATE INDEX idx_event_players_player ON event_players (player_id);
```

> **Nota:** o saldo deve ser calculado a partir do **Ledger** (fonte da verdade). `current_balance` pode ser materializado por view/trigger se necessário para performance.

### 4) Ledger (transações append-only)
```sql
CREATE TABLE ledger_entries (
  id                 BIGSERIAL PRIMARY KEY,
  event_id           BIGINT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  event_player_id    BIGINT NOT NULL REFERENCES event_players(id) ON DELETE CASCADE,
  source_type        ledger_source_type NOT NULL,
  source_id          BIGINT, -- ID da entidade de origem (mesa, eliminação, ajuste, compra, etc.)
  delta_centelhas    BIGINT NOT NULL, -- pode ser positivo ou negativo
  balance_after      BIGINT, -- opcional: saldo resultante pós-transação
  created_by_admin_id BIGINT,
  created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),

  CHECK (delta_centelhas <> 0)
);

-- Índices para auditoria e consulta rápida do saldo
CREATE INDEX idx_ledger_event_player_time ON ledger_entries (event_player_id, created_at);
CREATE INDEX idx_ledger_event_time ON ledger_entries (event_id, created_at);
CREATE INDEX idx_ledger_source ON ledger_entries (source_type, source_id);
```

**Regra de integridade (append-only):**
- `ledger_entries` não sofre `UPDATE` nem `DELETE` (apenas `INSERT`).
- Correções são realizadas com **novas entradas** (ex.: estorno ou ajuste manual), mantendo histórico completo.
- Pode-se impor por trigger/permissions em banco.

### 5) Table/Match (mesa)
```sql
CREATE TABLE tables (
  id              BIGSERIAL PRIMARY KEY,
  event_id        BIGINT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  created_by_player_id BIGINT NOT NULL REFERENCES players(id),
  status          table_status NOT NULL DEFAULT 'draft',
  bet_locked_at   TIMESTAMPTZ,
  started_at      TIMESTAMPTZ,
  finished_at     TIMESTAMPTZ,
  rolled_back_at  TIMESTAMPTZ,

  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),

  CHECK (
    (status = 'draft' AND started_at IS NULL) OR
    (status = 'started' AND started_at IS NOT NULL) OR
    (status = 'finished' AND finished_at IS NOT NULL) OR
    (status = 'rolled_back' AND rolled_back_at IS NOT NULL) OR
    (status = 'void')
  )
);

CREATE INDEX idx_tables_event_status ON tables (event_id, status);
CREATE INDEX idx_tables_event_created ON tables (event_id, created_at);
```

### 6) TablePlayer (participação + comandante + aposta)
```sql
CREATE TABLE table_players (
  id              BIGSERIAL PRIMARY KEY,
  table_id        BIGINT NOT NULL REFERENCES tables(id) ON DELETE CASCADE,
  event_player_id BIGINT NOT NULL REFERENCES event_players(id) ON DELETE CASCADE,
  commander_name  TEXT NOT NULL,

  -- aposta padrão da mesa ou aposta individual (quando acordada)
  bet_centelhas   BIGINT NOT NULL CHECK (bet_centelhas > 0),

  eliminator_table_player_id BIGINT REFERENCES table_players(id),
  is_scoop        BOOLEAN NOT NULL DEFAULT FALSE,
  joined_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  eliminated_at   TIMESTAMPTZ,

  UNIQUE (table_id, event_player_id)
);

CREATE INDEX idx_table_players_table ON table_players (table_id);
CREATE INDEX idx_table_players_event_player ON table_players (event_player_id);
CREATE INDEX idx_table_players_eliminator ON table_players (eliminator_table_player_id);
```

**Regra de integridade:** cada jogador deve ter exatamente **um comandante** por mesa; o campo `commander_name` é obrigatório e **não pode ser alterado após o bloqueio de aposta** (controle via aplicação/trigger).
**Regra de eliminação/scoop:** cada jogador eliminado aponta **um único eliminador** (`eliminator_table_player_id`), e o **scoop** é marcado por `is_scoop = true` no próprio `table_players`.

### 7) AuditLog (ações administrativas — append-only)
```sql
CREATE TABLE audit_logs (
  id              BIGSERIAL PRIMARY KEY,
  admin_id        BIGINT NOT NULL,
  event_id        BIGINT REFERENCES events(id) ON DELETE SET NULL,
  action          TEXT NOT NULL,
  entity_type     TEXT NOT NULL,
  entity_id       BIGINT,
  details_json    JSON,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_logs_event_time ON audit_logs (event_id, created_at);
CREATE INDEX idx_audit_logs_admin_time ON audit_logs (admin_id, created_at);
```

**Regra de integridade (append-only):**
- `audit_logs` não sofre `UPDATE` nem `DELETE` (apenas `INSERT`).
- Correções são registradas como novas entradas (ex.: `action = 'correction'`).

## Regras de integridade adicionais (resumo)

1. **Saldo confiável pelo Ledger:** todo movimento de centelhas deve gerar uma entrada no `ledger_entries`.
2. **Aposta bloqueada:** após `bet_locked_at`, não é permitido alterar `table_players.bet_centelhas` nem `commander_name`.
3. **Transferência por eliminação/scoop:** toda eliminação gera transferência no ledger (entrada negativa no eliminado e positiva no eliminador/ganhador).
4. **Rollback de mesa:** registra novas entradas no ledger (`source_type = 'table_rollback'`) para estornar efeitos de uma mesa.
5. **Append-only:** `ledger_entries` e `audit_logs` devem ser protegidos de `UPDATE`/`DELETE` por permissions/trigger.

## Índices recomendados (consolidados)

- `ledger_entries (event_player_id, created_at)` para extrato por jogador.
- `ledger_entries (event_id, created_at)` para relatórios por evento.
- `ledger_entries (source_type, source_id)` para rastrear origem do lançamento.
- `tables (event_id, status)` e `tables (event_id, created_at)`.
- `table_players (table_id)` e `table_players (event_player_id)`.
- `event_players (event_id)`, `event_players (player_id)`.
- `audit_logs (event_id, created_at)` e `audit_logs (admin_id, created_at)`.

---

### Observações finais
- O **ledger** é a verdade do saldo; qualquer correção é um novo lançamento.
- O **audit log** deve registrar toda ação administrativa relevante (compras, ajustes, abertura/fechamento de mesas, rollback, etc.).
- O modelo atende as regras de aposta, comandante por mesa, eliminação/scoop e premiação por ajuste manual.
