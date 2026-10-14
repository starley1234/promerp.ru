<?php defined('SYSPATH') or die('No direct script access.');

/** Deliver a bounded outbox batch; invoke periodically via cron/systemd timer. */
class Task_Events_Outbox extends Minion_Task
{
    protected $_options=array('limit'=>50,'lease'=>120,'worker'=>NULL);
    protected function _execute(?array $options=NULL)
    {
        $worker=Arr::get($options,'worker')?:gethostname().'-'.getmypid();
        $stats=Service_Erp208_Outbox::instance()->work($worker,function(array $payload,array $row){
            $delivered=Service_Erp208_EventBus::instance()->publish($row['event_name'],$payload,NULL,TRUE);
            if(!$delivered)throw new RuntimeException('Для события нет активного получателя');
        },(int)Arr::get($options,'limit',50),(int)Arr::get($options,'lease',120));
        echo json_encode($stats,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
    }
}
