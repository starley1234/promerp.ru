<?php defined('SYSPATH') or die('No direct script access.');

/** Account lifecycle, session epoch and object-level data scopes. */
class pgMigration_Erp_110_004_AccessGovernance extends pgMigration
{
    public function up()
    {
        $this->run_query("
            ALTER TABLE users
                ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'active',
                ADD COLUMN IF NOT EXISTS blocked_at TIMESTAMP(0) NULL,
                ADD COLUMN IF NOT EXISTS blocked_reason TEXT NULL,
                ADD COLUMN IF NOT EXISTS session_version INTEGER NOT NULL DEFAULT 1,
                ADD COLUMN IF NOT EXISTS access_policy VARCHAR(16) NOT NULL DEFAULT 'unrestricted';
            ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check;
            ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','blocked'));
            ALTER TABLE users DROP CONSTRAINT IF EXISTS users_access_policy_check;
            ALTER TABLE users ADD CONSTRAINT users_access_policy_check CHECK (access_policy IN ('unrestricted','restricted'));
            ALTER TABLE users DROP CONSTRAINT IF EXISTS users_session_version_check;
            ALTER TABLE users ADD CONSTRAINT users_session_version_check CHECK (session_version > 0);

            CREATE TABLE IF NOT EXISTS erp_100_user_scopes (
                id                  SERIAL PRIMARY KEY,
                user_id             INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                resource_type       VARCHAR(24) NOT NULL CHECK (resource_type IN ('department','warehouse','work_center')),
                resource_id         INTEGER NOT NULL CHECK (resource_id > 0),
                include_descendants SMALLINT NOT NULL DEFAULT 0 CHECK (include_descendants IN (0,1)),
                valid_from          DATE NOT NULL DEFAULT CURRENT_DATE,
                valid_to            DATE NULL,
                created_by          INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at          TIMESTAMP(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT erp_100_user_scope_dates CHECK (valid_to IS NULL OR valid_to >= valid_from),
                CONSTRAINT erp_100_user_scope_unique UNIQUE (user_id, resource_type, resource_id, valid_from)
            );
            CREATE INDEX IF NOT EXISTS erp_100_user_scopes_lookup_idx
                ON erp_100_user_scopes(user_id, resource_type, valid_to);
        ");
    }

    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_100_user_scopes CASCADE;');
        $this->run_query("ALTER TABLE users DROP COLUMN IF EXISTS access_policy, DROP COLUMN IF EXISTS session_version,
            DROP COLUMN IF EXISTS blocked_reason, DROP COLUMN IF EXISTS blocked_at, DROP COLUMN IF EXISTS status;");
    }
}
