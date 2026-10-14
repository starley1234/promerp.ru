<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class Erp208OutboxTest extends TestCase
{
    const PREFIX='erp208.outbox.test.';
    protected $service;
    protected function setUp():void
    {
        try{\DB::select('id')->from('erp_208_outbox')->limit(1)->execute();}catch(\Exception $e){$this->markTestSkipped('Выполните миграции outbox erp208');return;}
        $this->service=\Service_Erp208_Outbox::instance();
    }
    protected function tearDown():void
    {
        if(!$this->service)return;
        try{\DB::delete('erp_208_consumer_receipts')->where('consumer','LIKE',self::PREFIX.'%')->execute();\DB::delete('erp_208_outbox')->where('event_name','LIKE',self::PREFIX.'%')->execute();}catch(\Exception $e){}
    }
    protected function enqueue($suffix,array $payload=array('value'=>1),array $options=array())
    {return $this->service->enqueue(self::PREFIX.$suffix,$payload,self::PREFIX.$suffix.'.'.substr(md5(microtime(TRUE).mt_rand()),0,8),$options);}

    public function test_enqueue_is_idempotent_and_rejects_other_payload()
    {
        $key=self::PREFIX.'same-key';$a=$this->service->enqueue(self::PREFIX.'same',array('a'=>1),$key);$b=$this->service->enqueue(self::PREFIX.'same',array('a'=>1),$key);
        $this->assertSame($a['id'],$b['id']);$this->assertFalse($a['replayed']);$this->assertTrue($b['replayed']);
        $this->expectException(\Kohana_Exception::class);$this->service->enqueue(self::PREFIX.'same',array('a'=>2),$key);
    }
    public function test_idempotent_replay_does_not_abort_outer_transaction()
    {
        $db=\Database::instance();$db->begin();
        try{$key=self::PREFIX.'transaction-key';$this->service->enqueue(self::PREFIX.'transaction',array('a'=>1),$key);$replay=$this->service->enqueue(self::PREFIX.'transaction',array('a'=>1),$key);\DB::select(array(\DB::expr('1'),'ok'))->execute();$db->commit();}
        catch(\Throwable $e){$db->rollback();throw $e;}
        $this->assertTrue($replay['replayed']);
    }
    public function test_business_transaction_rollback_also_removes_outbox_message()
    {
        $db=\Database::instance();$db->begin();$r=$this->enqueue('rollback');$db->rollback();
        $count=(int)\DB::select(array(\DB::expr('count(*)'),'n'))->from('erp_208_outbox')->where('id','=',$r['id'])->execute()->get('n');$this->assertSame(0,$count);
    }
    public function test_claim_uses_lease_and_delivery_acknowledges_owner()
    {
        $r=$this->enqueue('lease');$first=$this->service->claim('worker-a',10,60,self::PREFIX);$second=$this->service->claim('worker-b',10,60,self::PREFIX);
        $this->assertCount(1,$first);$this->assertCount(0,$second);$this->assertSame($r['id'],(int)$first[0]['id']);
        $this->service->delivered($r['id'],'worker-a');$row=$this->service->rows(array('event_name'=>self::PREFIX.'lease'),1)[0];$this->assertSame('delivered',$row['status']);
    }
    public function test_failure_retries_then_moves_to_dead_and_can_be_replayed()
    {
        $r=$this->enqueue('dead',array('value'=>1),array('max_attempts'=>1));
        $stats=$this->service->work('worker-dead',function(){throw new \RuntimeException('receiver offline');},10,60,self::PREFIX);
        $this->assertSame(1,$stats['dead']);$row=$this->service->rows(array('event_name'=>self::PREFIX.'dead'),1)[0];$this->assertSame('dead',$row['status']);
        $this->service->replay($r['id']);$row=$this->service->rows(array('event_name'=>self::PREFIX.'dead'),1)[0];$this->assertSame('pending',$row['status']);$this->assertSame(0,(int)$row['attempts']);
    }
    public function test_consumer_receipt_prevents_duplicate_effect()
    {
        $r=$this->enqueue('receipt');$this->assertTrue($this->service->remember_consumed(self::PREFIX.'adapter','key-1',$r['id'],array('ok'=>1)));
        $this->assertFalse($this->service->remember_consumed(self::PREFIX.'adapter','key-1',$r['id'],array('ok'=>1)));
    }
    public function test_stats_expose_operator_signals()
    {
        $this->enqueue('stats');$s=$this->service->stats();$this->assertGreaterThan(0,$s['pending']);$this->assertGreaterThan(0,$s['ready']);$this->assertArrayHasKey('oldest_pending_seconds',$s);
    }
}
