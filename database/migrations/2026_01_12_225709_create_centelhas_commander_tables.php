<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->bigInteger('initial_centelhas');
            $table->foreignId('created_by_admin_id')->constrained('users');
            $table->timestampsTz();

            $table->index(['starts_at', 'ends_at'], 'idx_events_dates');
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('email')->unique()->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('display_name', 'idx_players_display_name');
        });

        Schema::create('event_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->timestampTz('joined_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->bigInteger('current_balance')->nullable();

            $table->unique(['event_id', 'player_id']);
            $table->index('event_id', 'idx_event_players_event');
            $table->index('player_id', 'idx_event_players_player');
        });

        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('created_by_player_id')->constrained('players');
            $table->enum('status', ['draft', 'started', 'finished', 'rolled_back', 'void'])->default('draft');
            $table->timestampTz('bet_locked_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('rolled_back_at')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'status'], 'idx_tables_event_status');
            $table->index(['event_id', 'created_at'], 'idx_tables_event_created');
        });

        Schema::create('table_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();
            $table->foreignId('event_player_id')->constrained('event_players')->cascadeOnDelete();
            $table->string('commander_name');
            $table->bigInteger('bet_centelhas');
            $table->foreignId('eliminator_table_player_id')->nullable()->constrained('table_players');
            $table->boolean('is_scoop')->default(false);
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampTz('eliminated_at')->nullable();

            $table->unique(['table_id', 'event_player_id']);
            $table->index('table_id', 'idx_table_players_table');
            $table->index('event_player_id', 'idx_table_players_event_player');
            $table->index('eliminator_table_player_id', 'idx_table_players_eliminator');
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_player_id')->constrained('event_players')->cascadeOnDelete();
            $table->enum('source_type', [
                'event_initial_balance',
                'table_buy_in',
                'elimination_transfer',
                'scoop_transfer',
                'table_rollback',
                'bank_purchase',
                'admin_adjustment',
                'prize_withdrawal',
            ]);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->bigInteger('delta_centelhas');
            $table->bigInteger('balance_after')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['event_player_id', 'created_at'], 'idx_ledger_event_player_time');
            $table->index(['event_id', 'created_at'], 'idx_ledger_event_time');
            $table->index(['source_type', 'source_id'], 'idx_ledger_source');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('details_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['event_id', 'created_at'], 'idx_audit_logs_event_time');
            $table->index(['admin_id', 'created_at'], 'idx_audit_logs_admin_time');
        });

        $this->addChecks();
        $this->addAppendOnlyGuards();
    }

    public function down(): void
    {
        $this->dropAppendOnlyGuards();

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('table_players');
        Schema::dropIfExists('tables');
        Schema::dropIfExists('event_players');
        Schema::dropIfExists('players');
        Schema::dropIfExists('events');
    }

    private function addChecks(): void
    {
        if (! $this->isPostgresOrMysql()) {
            return;
        }

        DB::statement('ALTER TABLE events ADD CONSTRAINT events_initial_centelhas_check CHECK (initial_centelhas >= 0)');
        DB::statement('ALTER TABLE table_players ADD CONSTRAINT table_players_bet_centelhas_check CHECK (bet_centelhas > 0)');
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_delta_check CHECK (delta_centelhas <> 0)');
        DB::statement("ALTER TABLE tables ADD CONSTRAINT tables_status_check CHECK ((status = 'draft' AND started_at IS NULL) OR (status = 'started' AND started_at IS NOT NULL) OR (status = 'finished' AND finished_at IS NOT NULL) OR (status = 'rolled_back' AND rolled_back_at IS NOT NULL) OR (status = 'void'))");
    }

    private function addAppendOnlyGuards(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "CREATE OR REPLACE FUNCTION prevent_update_delete() RETURNS trigger AS $$\n" .
            "BEGIN\n" .
            "  RAISE EXCEPTION 'updates and deletes are not allowed on append-only tables';\n" .
            "END;\n" .
            "$$ LANGUAGE plpgsql;"
        );

        DB::statement(
            "CREATE TRIGGER ledger_entries_append_only\n" .
            "BEFORE UPDATE OR DELETE ON ledger_entries\n" .
            "FOR EACH ROW EXECUTE FUNCTION prevent_update_delete();"
        );

        DB::statement(
            "CREATE TRIGGER audit_logs_append_only\n" .
            "BEFORE UPDATE OR DELETE ON audit_logs\n" .
            "FOR EACH ROW EXECUTE FUNCTION prevent_update_delete();"
        );
    }

    private function dropAppendOnlyGuards(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS ledger_entries_append_only ON ledger_entries');
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS prevent_update_delete()');
    }

    private function isPostgresOrMysql(): bool
    {
        return in_array(DB::getDriverName(), ['pgsql', 'mysql', 'mariadb'], true);
    }
};
