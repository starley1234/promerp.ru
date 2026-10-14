<?php defined('SYSPATH') or die('No direct script access.');

/** Transactional outbox with lease, exponential retry and dead-letter replay. */
class Service_Erp208_Outbox
{
    protected static $_instance;
    public static function instance(){return self::$_instance ?: (self::$_instance=new self);}
    protected function __construct(){}

    public function enqueue($event_name,array $payload,$idempotency_key,array $options=array())
    {
        $event_name=trim((string)$event_name);$key=trim((string)$idempotency_key);
        if($event_name==='' OR $key==='' OR strlen($key)>190)throw new Kohana_Exception('Outbox: укажите событие и корректный idempotency key');
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $headers=json_encode((array)Arr::get($options,'headers',array()),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        // ON CONFLICT is essential here: catching a unique violation would
        // poison the caller's PostgreSQL transaction and break atomic outbox.
        $inserted=DB::query(Database::SELECT,"INSERT INTO erp_208_outbox
            (event_name,aggregate_type,aggregate_id,payload,headers,idempotency_key,correlation_id,max_attempts,available_at)
            VALUES (:event,:aggregate_type,:aggregate_id,cast(:payload AS jsonb),cast(:headers AS jsonb),:key,
                coalesce(cast(:correlation AS uuid),gen_random_uuid()),:max_attempts,
                coalesce(cast(:available AS timestamp),CURRENT_TIMESTAMP))
            ON CONFLICT (idempotency_key) DO NOTHING RETURNING id")
            ->parameters(array(
                ':event'=>$event_name, ':aggregate_type'=>Arr::get($options,'aggregate_type'),
                ':aggregate_id'=>Arr::get($options,'aggregate_id'), ':payload'=>$json,
                ':headers'=>$headers, ':key'=>$key, ':correlation'=>Arr::get($options,'correlation_id'),
                ':max_attempts'=>max(1,min(100,(int)Arr::get($options,'max_attempts',8))),
                ':available'=>Arr::get($options,'available_at'),
            ))->execute()->current();
        if($inserted)return array('id'=>(int)$inserted['id'],'replayed'=>FALSE);

        $row=DB::select('id','event_name','payload')->from('erp_208_outbox')->where('idempotency_key','=',$key)->execute()->current();
        if(!$row)throw new Kohana_Exception('Outbox: конфликт ключа не удалось разрешить');
        $stored=is_string($row['payload'])?json_decode($row['payload'],TRUE):$row['payload'];
        if($row['event_name']!==$event_name OR $stored!=$payload)throw new Kohana_Exception('Outbox: idempotency key уже использован для другого сообщения');
        return array('id'=>(int)$row['id'],'replayed'=>TRUE);
    }

    /** Atomically leases ready messages; concurrent workers cannot claim the same row. */
    public function claim($worker,$limit=20,$lease_seconds=60,$event_prefix=NULL)
    {
        $worker=trim((string)$worker);if($worker==='')throw new Kohana_Exception('Outbox: worker name обязателен');
        $limit=max(1,min(200,(int)$limit));$lease=max(10,min(3600,(int)$lease_seconds));$prefix=trim((string)$event_prefix);
        return DB::query(Database::SELECT,"WITH candidates AS (
            SELECT id FROM erp_208_outbox
            WHERE ((status='pending' AND available_at<=CURRENT_TIMESTAMP)
               OR (status='processing' AND lease_until<CURRENT_TIMESTAMP))
              AND (:event_prefix='' OR event_name LIKE :event_pattern)
            ORDER BY available_at,id FOR UPDATE SKIP LOCKED LIMIT :limit
        ) UPDATE erp_208_outbox o SET status='processing',attempts=o.attempts+1,
            lease_until=CURRENT_TIMESTAMP+(:lease * interval '1 second'),leased_by=:worker,
            updated_at=CURRENT_TIMESTAMP,last_error=NULL FROM candidates c WHERE o.id=c.id
            RETURNING o.*")
            ->parameters(array(':limit'=>$limit,':lease'=>$lease,':worker'=>$worker,':event_prefix'=>$prefix,':event_pattern'=>$prefix.'%'))->execute()->as_array();
    }

    public function delivered($id,$worker)
    {
        $n=DB::query(Database::UPDATE,"UPDATE erp_208_outbox SET status='delivered',delivered_at=CURRENT_TIMESTAMP,lease_until=NULL,leased_by=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='processing' AND leased_by=:worker")
            ->parameters(array(':id'=>(int)$id,':worker'=>(string)$worker))->execute();
        if((int)$n===0)throw new Kohana_Exception('Outbox: lease потеряна, подтверждение отклонено');
    }

    public function failed($id,$worker,$error)
    {
        $row=DB::select('attempts','max_attempts')->from('erp_208_outbox')->where('id','=',(int)$id)->where('status','=','processing')->where('leased_by','=',(string)$worker)->execute()->current();
        if(!$row)throw new Kohana_Exception('Outbox: lease потеряна, ошибка не записана');
        $dead=(int)$row['attempts'] >= (int)$row['max_attempts'];$delay=min(3600,(int)pow(2,min(12,(int)$row['attempts'])));
        DB::query(Database::UPDATE,"UPDATE erp_208_outbox SET status=:status,available_at=CURRENT_TIMESTAMP+(:delay * interval '1 second'),lease_until=NULL,leased_by=NULL,last_error=:error,updated_at=CURRENT_TIMESTAMP WHERE id=:id")
            ->parameters(array(':status'=>$dead?'dead':'pending',':delay'=>$dead?0:$delay,':error'=>mb_substr(trim((string)$error),0,4000),':id'=>(int)$id))->execute();
        return $dead?'dead':'pending';
    }

    /** Process one claimed batch. Delivery callback must be idempotent. */
    public function work($worker,$delivery,$limit=20,$lease_seconds=60,$event_prefix=NULL)
    {
        if(!is_callable($delivery))throw new Kohana_Exception('Outbox: delivery callback недоступен');
        $stats=array('claimed'=>0,'delivered'=>0,'retried'=>0,'dead'=>0);
        foreach($this->claim($worker,$limit,$lease_seconds,$event_prefix) as $row){$stats['claimed']++;
            try{$payload=is_array($row['payload'])?$row['payload']:json_decode($row['payload'],TRUE);call_user_func($delivery,$payload,$row);$this->delivered($row['id'],$worker);$stats['delivered']++;}
            catch(Throwable $e){$state=$this->failed($row['id'],$worker,$e->getMessage());$stats[$state==='dead'?'dead':'retried']++;}
        }
        return $stats;
    }

    /** Consumer-side deduplication. FALSE means this key was processed before. */
    public function remember_consumed($consumer,$key,$outbox_id=NULL,$result=NULL)
    {
        try{DB::insert('erp_208_consumer_receipts',array('consumer','idempotency_key','outbox_id','result_hash'))->values(array((string)$consumer,(string)$key,$outbox_id?(int)$outbox_id:NULL,$result===NULL?NULL:hash('sha256',is_string($result)?$result:json_encode($result))))->execute();return TRUE;}
        catch(Database_Exception $e){return FALSE;}
    }

    public function replay($id)
    {
        $n=DB::query(Database::UPDATE,"UPDATE erp_208_outbox SET status='pending',attempts=0,available_at=CURRENT_TIMESTAMP,lease_until=NULL,leased_by=NULL,last_error=NULL,delivered_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='dead'")->param(':id',(int)$id)->execute();
        if((int)$n===0)throw new Kohana_Exception('Outbox: повторить можно только dead-letter сообщение');
    }

    public function rows(array $filters=array(),$limit=50)
    {
        $q=DB::select()->from('erp_208_outbox');
        if(!empty($filters['status']))$q->where('status','=',(string)$filters['status']);
        if(!empty($filters['event_name']))$q->where('event_name','=',(string)$filters['event_name']);
        if(!empty($filters['correlation_id']))$q->where('correlation_id','=',(string)$filters['correlation_id']);
        return $q->order_by('id','DESC')->limit(max(1,min(200,(int)$limit)))->execute()->as_array();
    }

    public function stats()
    {
        $result=array('pending'=>0,'processing'=>0,'delivered'=>0,'dead'=>0,'ready'=>0,'oldest_pending_seconds'=>0);
        foreach(DB::query(Database::SELECT,"SELECT status,count(*) n FROM erp_208_outbox GROUP BY status")->execute() as $r)$result[$r['status']]=(int)$r['n'];
        $age=DB::query(Database::SELECT,"SELECT count(*) FILTER(WHERE status='pending' AND available_at<=CURRENT_TIMESTAMP) ready,coalesce(extract(epoch FROM CURRENT_TIMESTAMP-(min(created_at) FILTER(WHERE status='pending'))),0) age FROM erp_208_outbox")->execute()->current();
        $result['ready']=(int)$age['ready'];$result['oldest_pending_seconds']=round((float)$age['age']);return $result;
    }
}
