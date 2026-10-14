<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_Erp_118_001_Links_Table extends pgMigration
{
    // protected $_db_group = 'default';

    public function up()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS "lib_118_links" (
    "id" SERIAL PRIMARY KEY,
    "user_id" INTEGER NOT NULL,
    "url" VARCHAR(2048) NOT NULL,
    "title" VARCHAR(255) DEFAULT NULL,
    "description" TEXT DEFAULT NULL,
    "color" VARCHAR(7) DEFAULT '#007bff',
    "emoji" VARCHAR(10) DEFAULT '🔗',
    "icon_class" VARCHAR(50) DEFAULT 'bi-link-45deg',
    "group_name" VARCHAR(100) DEFAULT NULL,
    "is_menu_item" SMALLINT DEFAULT 0,
    "order" INTEGER DEFAULT 0,
    "created_at" TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION update_modified_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ language 'plpgsql';

DROP TRIGGER IF EXISTS update_lib_118_links_modtime ON "lib_118_links";

CREATE TRIGGER update_lib_118_links_modtime
    BEFORE UPDATE ON "lib_118_links"
    FOR EACH ROW
    EXECUTE PROCEDURE update_modified_column();
SQL;

        $this->run_query($sql);
    }

    public function down()
    {
        $sql = <<<SQL
DROP TRIGGER IF EXISTS update_lib_118_links_modtime ON "lib_118_links";
DROP TABLE IF EXISTS "lib_118_links" CASCADE;
SQL;

        $this->run_query($sql);
    }
}