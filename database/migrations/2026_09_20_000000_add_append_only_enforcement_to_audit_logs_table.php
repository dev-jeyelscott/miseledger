<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enforce the append-only audit_logs invariant at the database boundary.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_logs_prevent_mutation() RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Audit log entries cannot be deleted.';
                ELSIF TG_OP = 'TRUNCATE' THEN
                    RAISE EXCEPTION 'Audit log entries cannot be truncated.';
                ELSIF TG_OP = 'UPDATE' THEN
                    -- Allow only the cascading actor_id -> NULL nullification
                    -- performed by the users.id ON DELETE SET NULL foreign key
                    -- action (detected via nested trigger depth); every other
                    -- column must stay byte-identical.
                    IF pg_trigger_depth() > 1
                        AND OLD.actor_id IS NOT NULL
                        AND NEW.actor_id IS NULL
                        AND NEW.id = OLD.id
                        AND NEW.organization_id = OLD.organization_id
                        AND NEW.action = OLD.action
                        AND NEW.entity_type = OLD.entity_type
                        AND NEW.entity_id = OLD.entity_id
                        AND NEW.before_data IS NOT DISTINCT FROM OLD.before_data
                        AND NEW.after_data IS NOT DISTINCT FROM OLD.after_data
                        AND NEW.correlation_id IS NOT DISTINCT FROM OLD.correlation_id
                        AND NEW.is_deduplication_key = OLD.is_deduplication_key
                        AND NEW.created_at = OLD.created_at
                    THEN
                        RETURN NEW;
                    END IF;

                    RAISE EXCEPTION 'Audit log entries are immutable.';
                END IF;

                RETURN NULL;
            END;
            $$;
        SQL);

        DB::statement(
            'CREATE OR REPLACE TRIGGER audit_logs_prevent_update_delete '
            .'BEFORE UPDATE OR DELETE ON audit_logs '
            .'FOR EACH ROW EXECUTE FUNCTION audit_logs_prevent_mutation()',
        );

        DB::statement(
            'CREATE OR REPLACE TRIGGER audit_logs_prevent_truncate '
            .'BEFORE TRUNCATE ON audit_logs '
            .'FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_prevent_mutation()',
        );
    }

    /**
     * Remove the append-only enforcement.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_prevent_truncate ON audit_logs');
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_prevent_update_delete ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_prevent_mutation()');
    }
};
