# Regulamento do Sistema “Centelhas Commander”

## 1. Objetivo e fonte da verdade
Este documento define o regulamento completo e único do sistema “Centelhas Commander”. Em caso de divergência com qualquer outro material, este texto é a fonte da verdade.

## 2. Glossário
- **Season (Evento):** unidade principal de competição, configurada e administrada no sistema.
- **Centelhas:** saldo/ativo usado nas mesas e no ranking da Season.
- **Mesa:** instância de jogo criada dentro de uma Season.
- **Aposta:** valor de Centelhas acordado para participar de uma mesa.
- **Scoop:** situação em que um jogador concede (desiste) da partida, sem encerrar o jogo para os demais jogadores.
- **Admin:** usuário com permissão de administração da Season.
- **Jogador:** usuário participante, com acesso somente leitura às informações de regras, saldo e resultados.

## 3. Estrutura de Evento (Season)
1. Cada competição ocorre dentro de uma Season.
2. Uma Season define:
   - O conjunto de participantes elegíveis.
   - As regras operacionais descritas neste documento.
   - As **centelhas iniciais**, configuráveis por evento (Season).
3. A criação de novas Seasons e a configuração do saldo inicial são responsabilidade do Admin.

## 4. Centelhas iniciais
1. Cada jogador inicia a Season com um saldo de Centelhas definido na configuração do evento.
2. O valor é único por Season e pode variar entre Seasons diferentes.

## 5. Criação de mesa e apostas
1. A criação de uma mesa exige **aposta consensual** entre todos os jogadores participantes.
2. A aposta **padrão** da mesa é **igual para todos os jogadores**.
3. Opcionalmente, pode haver **aposta individual por jogador**, desde que:
   - Seja explicitamente acordada por todos os participantes.
   - Fique registrada na criação da mesa.
4. A aposta é sempre definida antes do início da mesa.

## 6. Bloqueio de aposta
1. Após a mesa iniciar, a aposta é **bloqueada**.
2. Com a aposta bloqueada, nenhum ajuste pode ser feito nos valores de participação daquela mesa.

## 7. Transferência de aposta ao eliminar
1. Quando um jogador é eliminado da mesa, a **aposta associada àquele jogador** é **transferida** ao jogador que o eliminou.
2. O valor transferido passa a integrar o montante do jogador que realizou a eliminação.

## 8. Scoop (concessão)
1. Em caso de **scoop**, um jogador concede (desiste) da partida, **sem encerrar a mesa** para os demais jogadores.
2. As apostas do jogador que concedeu seguem as mesmas regras de transferência aplicáveis à sua eliminação.

## 9. Empate e rollback
1. Em caso de **empate**, a mesa realiza **rollback** para o estado **anterior à jogada que gerou o empate**.
2. Após o rollback, o resultado da mesa deve ser reavaliado conforme o novo estado.

## 10. Compras na banca e ajustes manuais
1. **Compras na banca** (entrada de Centelhas no saldo de um jogador) só podem ser realizadas por Admin.
2. **Ajustes manuais** (aumentos ou reduções de saldo) são exclusivos do Admin e devem ser registrados com motivo.

## 11. Premiação
1. A premiação é realizada **exclusivamente por redução manual** aplicada pelo Admin.
2. A redução manual representa o valor concedido como prêmio (ex.: retirada do saldo do sistema ou conversão fora do sistema).

## 12. Permissões de jogadores
1. Jogadores têm acesso **somente leitura** a:
   - Saldo de Centelhas pessoal.
   - Resultados das mesas.
   - Regras e informações da Season.
2. Jogadores não podem alterar apostas, saldos, configurações ou resultados.

## 13. Regra “comandante por jogador na mesa”
1. Em qualquer mesa, **cada jogador deve ter exatamente um comandante** associado.
2. O comandante é definido no início da mesa e não pode ser alterado após o bloqueio de aposta.

## 14. Decisões em aberto
1. **Eliminação ambígua:** uma partida não pode ser finalizada nesse estado; é necessário apontar um jogador que realizou a eliminação, ou o jogador deve ser marcado como desistente, fazendo com que suas centelhas sejam adquiridas pelo ganhador da mesa (último jogador vivo).
2. **Desempate do ranking:** em caso de empate, ambos os jogadores compartilharão a posição; exemplo: jogadores A e B empatados em 1º lugar e jogador C em 3º lugar.
