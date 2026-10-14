<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class Erp231ProductStructureTest extends TestCase
{
    protected $service; protected $items=array(); protected $structures=array();
    protected function setUp(): void
    {
        try { \DB::select('id')->from('erp_231_structures')->limit(1)->execute(); }
        catch(\Exception $e) { $this->markTestSkipped('Выполните миграции erp231'); return; }
        $this->service=\Service_Erp231_ProductStructure::instance();
    }
    protected function tearDown(): void
    {
        if(!$this->service)return;
        try {
            if($this->structures) {
                // Production code cannot edit approved lines. Tests explicitly
                // unlock only their own revisions before fixture cleanup.
                \DB::update('erp_231_structures')->set(array('status'=>'obsolete'))->where('id','IN',$this->structures)->execute();
                $lineIds=\DB::select('id')->from('erp_231_lines')->where('structure_id','IN',$this->structures)->execute()->as_array(NULL,'id');
                \DB::delete('erp_231_mappings')->where('mbom_structure_id','IN',$this->structures)->execute();
                if($lineIds) \DB::delete('erp_231_mappings')->where('ebom_line_id','IN',$lineIds)->execute();
                \DB::delete('erp_231_changes')->where('to_structure_id','IN',$this->structures)->execute();
                \DB::delete('erp_231_lines')->where('structure_id','IN',$this->structures)->execute();
                \DB::delete('erp_231_structures')->where('id','IN',$this->structures)->execute();
            }
            if($this->items) \DB::delete('erp_227_objects')->where('id','IN',$this->items)->execute();
        } catch(\Exception $e) {}
    }
    protected function item($name)
    {
        $type=(int)\DB::select('id')->from('erp_227_object_types')->limit(1)->execute()->get('id');
        $code='TEST-231-'.strtoupper(substr(md5($name.microtime(TRUE)),0,10));
        $r=\DB::insert('erp_227_objects',array('type_id','code','name','status','is_deleted'))->values(array($type,$code,$name,'active',FALSE))->execute();
        $id=(int)$r[0];$this->items[]=$id;return $id;
    }
    protected function structure($product,$kind='ebom',$revision=NULL)
    {
        $s=$this->service->create(array('product_item_id'=>$product,'kind'=>$kind,'revision'=>$revision,'site_code'=>'TEST'),NULL);
        $this->structures[]=(int)$s['id'];return $s;
    }
    public function test_revision_lifecycle_copy_mapping_and_immutability()
    {
        $product=$this->item('Тестовое изделие');$component=$this->item('Тестовый компонент');
        $ebom=$this->structure($product);
        $line=$this->service->add_line($ebom['id'],array('component_item_id'=>$component,'qty'=>2.5,'uom'=>'pcs','supply_type'=>'buy'),NULL);
        $check=$this->service->validate($ebom['id']);$this->assertTrue($check['valid']);$this->assertSame(1,$check['line_count']);
        $this->service->submit_review($ebom['id']);$approved=$this->service->approve($ebom['id'],NULL);
        $this->assertSame('approved',$approved['status']);
        $mbom=$this->service->copy_ebom_to_mbom($ebom['id'],array('site_code'=>'TEST'),NULL);$this->structures[]=(int)$mbom['id'];
        $this->assertSame('mbom',$mbom['kind']);$this->assertCount(1,$mbom['lines']);$this->assertCount(1,$this->service->mappings($mbom['id']));
        $this->expectException(\Kohana_Exception::class);$this->service->remove_line($line);
    }
    public function test_direct_and_multilevel_cycles_are_rejected()
    {
        $a=$this->item('Изделие A');$b=$this->item('Узел B');
        $sa=$this->structure($a,'ebom','A1');$this->service->add_line($sa['id'],array('component_item_id'=>$b,'qty'=>1));
        $sb=$this->structure($b,'ebom','B1');$this->service->add_line($sb['id'],array('component_item_id'=>$a,'qty'=>1));
        $check=$this->service->validate($sa['id']);$this->assertFalse($check['valid']);$this->assertStringContainsString('цикл',mb_strtolower(implode(' ',$check['errors'])));
    }
    public function test_workbench_is_renderable_and_all_pilot_modules_are_in_navigation()
    {
        $html=(string)\View::factory('erp231/index')->set('rows',array())->set('total',0)->set('error',NULL)->set('can_manage',FALSE);
        $this->assertStringContainsString('Составы EBOM / MBOM',$html);$this->assertStringContainsString('Спецификации не найдены',$html);
        $menu=file_get_contents(APPPATH.'classes/Ui/Navigation.php');foreach(array('/product-structures','/traceability','/integration') as $href)$this->assertStringContainsString("'".$href."'",$menu);
        $this->assertSame('product-structures',\Route::get('erp231')->uri());
    }
    public function test_mcp_keeps_approval_boundary()
    {
        $tools=\Service_Erp804_Registry::instance()->tools();
        foreach(array('product_structure.search','product_structure.get','product_structure.validate','product_structure.draft.create','product_structure.line.add','product_structure.approve') as $name)$this->assertArrayHasKey($name,$tools);
        $this->assertFalse($tools['product_structure.search']['mutating']);$this->assertTrue($tools['product_structure.approve']['approval']);
    }
}
