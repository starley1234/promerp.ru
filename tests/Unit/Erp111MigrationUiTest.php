<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class Erp111MigrationUiTest extends TestCase
{
    public function test_bootstrap_controller_does_not_load_orm_auth()
    {
        $this->assertSame('Controller',get_parent_class(\Controller_Erp111_Migrate::class));
    }

    public function test_only_explicit_post_can_execute_migrations()
    {
        $this->assertFalse(\Controller_Erp111_Migrate::may_execute('GET','run'));
        $this->assertFalse(\Controller_Erp111_Migrate::may_execute('POST','status'));
        $this->assertTrue(\Controller_Erp111_Migrate::may_execute('POST','run'));
    }

    public function test_admin_view_has_csrf_protected_button_not_run_link()
    {
        $html=(string)\View::factory('erp111/migrate/index',array(
            'inventory'=>array(array('class'=>'pgMigration_Test','module'=>'test','db_group'=>'default','applied'=>FALSE,'applied_at'=>NULL)),
            'pending'=>array(array('class'=>'pgMigration_Test')),'orphans'=>array(),
            'message'=>NULL,'message_type'=>'info','operation_log'=>array(),'configuration_warning'=>NULL,'csrf_token'=>'safe-token','action_url'=>'/migrate_pg',
        ));
        $this->assertStringContainsString('method="post"',$html);
        $this->assertStringContainsString('name="csrf_token" value="safe-token"',$html);
        $this->assertStringContainsString('Применить ожидающие миграции',$html);
        $this->assertStringNotContainsString('?run=1',$html);
    }

    public function test_migrator_can_isolate_database_groups()
    {
        $result=\Service_Erp111_Migrator::instance()->migrate(array('db_group'=>'__missing__','dry_run'=>TRUE));
        $this->assertSame(array(),$result['applied']);
        $this->assertNull($result['failed']);
        $this->assertSame(array(),$result['errors']);
    }

    public function test_initial_mdm_migration_can_reconcile_legacy_schema_safely()
    {
        $source=file_get_contents(APPPATH.'modules/erp230_mdm/migrations_pg/pgMigration_000_erp230_mdm_create_tables.php');

        $this->assertStringContainsString('assert_existing_schema_is_compatible', $source);
        $this->assertStringContainsString('Отсутствующие колонки', $source);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS org_units', $source);
        $this->assertStringContainsString('CREATE UNIQUE INDEX IF NOT EXISTS uk_object_codes_upper', $source);
        $this->assertStringContainsString('DROP TRIGGER IF EXISTS trg_org_path ON org_units', $source);
        $this->assertStringContainsString('DROP POLICY IF EXISTS p_obj_select ON objects', $source);
        $this->assertStringContainsString("ON CONFLICT (id) DO NOTHING", $source);
        $this->assertStringContainsString('принята и безопасно дополнена существующая MDM-схема', $source);

        $seedSource=file_get_contents(APPPATH.'modules/erp230_mdm/migrations_pg/pgMigration_002_erp230_mdm_add_data.php');
        $this->assertStringContainsString('ON CONFLICT (id) DO UPDATE SET', $seedSource);
    }

    public function test_route_keeps_familiar_admin_url()
    {
        $this->assertSame('migrate_pg',\Route::get('erp111_migrations')->uri());
    }
}
