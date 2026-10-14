<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
class Erp802IntegrationTest extends TestCase
{
    const PREFIX='TEST-802-'; protected $service;
    protected function setUp():void{try{\DB::select('id')->from('erp_802_packages')->limit(1)->execute();}catch(\Throwable $e){$this->markTestSkipped('Выполните миграции erp802');return;}$this->service=\Service_Erp802_Integration::instance();}
    protected function tearDown():void
    {
        if(!$this->service)return;try{$packages=\DB::select('id')->from('erp_802_packages')->where('external_batch_id','LIKE',self::PREFIX.'%')->execute()->as_array(NULL,'id');if($packages){$uuids=\DB::select('uuid')->from('erp_802_packages')->where('id','IN',$packages)->execute()->as_array(NULL,'uuid');\DB::delete('erp_208_outbox')->where('event_name','=','erp802.package.completed')->where('aggregate_id','IN',$uuids)->execute();\DB::delete('erp_802_mappings')->where('last_package_id','IN',$packages)->execute();\DB::delete('erp_802_package_rows')->where('package_id','IN',$packages)->execute();\DB::delete('erp_802_packages')->where('id','IN',$packages)->execute();}\DB::delete('erp_227_objects')->where('code','LIKE',self::PREFIX.'%')->execute();}catch(\Throwable $e){}
    }
    protected function json($external,$code,$name='Тестовая деталь',$extra=array()){return json_encode(array('items'=>array(array_merge(array('external_id'=>$external,'code'=>$code,'name'=>$name,'type'=>'component','attributes'=>array('unit'=>'pcs')),$extra))),JSON_UNESCAPED_UNICODE);}
    protected function options($suffix,$dry=FALSE){return array('external_batch_id'=>self::PREFIX.$suffix,'dry_run'=>$dry);}

    public function test_dry_run_has_protocol_without_domain_write()
    {
        $code=self::PREFIX.'DRY';$p=$this->service->receive_and_process('file','items','json',$this->json('ext-dry',$code),$this->options('dry',TRUE));$rows=$this->service->package_rows($p['id']);
        $this->assertSame('completed',$p['status']);$this->assertSame('planned',$rows[0]['status']);$this->assertSame(0,(int)\DB::select(array(\DB::expr('count(*)'),'n'))->from('erp_227_objects')->where('code','=',$code)->execute()->get('n'));
    }
    public function test_import_is_idempotent_and_changed_content_creates_revision()
    {
        $code=self::PREFIX.'REV';$first=$this->service->receive_and_process('1c','items','json',$this->json('ext-rev',$code),$this->options('one'));$duplicate=$this->service->receive('1c','items','json',$this->json('ext-rev',$code),$this->options('duplicate'));
        $this->assertSame('completed',$first['status']);$this->assertTrue($duplicate['duplicate']);$this->assertSame($first['id'],$duplicate['id']);
        $changed=$this->service->receive_and_process('1c','items','json',$this->json('ext-rev',$code,'Новое имя'),$this->options('two'));$map=\DB::select()->from('erp_802_mappings')->where('external_id','=','ext-rev')->execute()->current();
        $this->assertSame('completed',$changed['status']);$this->assertNotSame((int)$first['id'],(int)$changed['id']);$this->assertSame(2,(int)\DB::select(array(\DB::expr('count(*)'),'n'))->from('erp_227_objects')->where('code','=',$code)->execute()->get('n'));$this->assertNotEmpty($map['internal_uuid']);
    }
    public function test_unmapped_code_collision_is_explicit_error()
    {
        $type=(int)\DB::select('id')->from('erp_227_object_types')->where('code','=','component')->execute()->get('id');$code=self::PREFIX.'COLLISION';\Service_Erp227_Registry::instance()->create_object(array('type_id'=>$type,'code'=>$code,'name'=>'Существующая'));
        $p=$this->service->receive_and_process('file','items','json',$this->json('foreign-id',$code),$this->options('collision'));$r=$this->service->package_rows($p['id'])[0];$this->assertSame('failed',$p['status']);$this->assertSame('code_conflict',$r['reason_code']);
    }
    public function test_csv_reports_duplicate_external_ids_per_row()
    {
        $csv="external_id;code;name;type\ndup;".self::PREFIX."A;A;component\ndup;".self::PREFIX."B;B;component\n";$p=$this->service->receive_and_process('file','items','csv',$csv,$this->options('duplicates',TRUE));$rows=$this->service->package_rows($p['id']);
        $this->assertSame('partial',$p['status']);$this->assertSame('planned',$rows[0]['status']);$this->assertSame('duplicate_external_id',$rows[1]['reason_code']);
    }
    public function test_mcp_declares_observation_and_approval_boundaries()
    {
        $tools=\Service_Erp804_Registry::instance()->tools();foreach(array('integration.packages','integration.package','integration.validate_items','integration.import_items','integration.retry') as $name)$this->assertArrayHasKey($name,$tools);$this->assertFalse($tools['integration.validate_items']['mutating']);$this->assertTrue($tools['integration.import_items']['approval']);$this->assertTrue($tools['integration.retry']['approval']);
    }
}
