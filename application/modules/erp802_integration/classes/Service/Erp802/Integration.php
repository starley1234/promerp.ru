<?php defined('SYSPATH') or die('No direct script access.');

/** Idempotent package journal and narrow file/1C import gateway. */
class Service_Erp802_Integration
{
    const MAX_BYTES=10485760; const MAX_ROWS=10000;
    protected static $_instance;
    public static function instance(){return self::$_instance ?: (self::$_instance=new self);}
    protected function __construct(){}

    public function receive($source_code,$entity_kind,$format,$content,array $options=array(),$actor_id=NULL)
    {
        $source=$this->source($source_code);$entity=(string)$entity_kind;$format=mb_strtolower((string)$format);
        if($entity!=='items')throw new Kohana_Exception('Первый контракт erp802 поддерживает entity_kind=items');
        if(!in_array($format,array('json','csv'),TRUE))throw new Kohana_Exception('Поддерживаются JSON и CSV');
        if(!is_string($content) OR $content==='' OR strlen($content)>self::MAX_BYTES)throw new Kohana_Exception('Пакет пуст или превышает 10 МБ');
        $records=$this->parse($format,$content);if(count($records)>self::MAX_ROWS)throw new Kohana_Exception('В пакете больше 10000 строк');
        $checksum=hash('sha256',$content);$dry=!empty($options['dry_run']);
        $insert=DB::query(Database::SELECT,"INSERT INTO erp_802_packages(source_id,direction,entity_kind,format,checksum,external_batch_id,dry_run,payload_size,total_rows,created_by)
            VALUES(:source,'in',:entity,:format,:checksum,:batch,:dry,:size,:total,:actor)
            ON CONFLICT(source_id,direction,entity_kind,checksum,dry_run) DO NOTHING RETURNING id")
            ->parameters(array(':source'=>(int)$source['id'],':entity'=>$entity,':format'=>$format,':checksum'=>$checksum,':batch'=>Arr::get($options,'external_batch_id'),':dry'=>$dry,':size'=>strlen($content),':total'=>count($records),':actor'=>$actor_id?:NULL))->execute()->current();
        if(!$insert){$id=(int)DB::select('id')->from('erp_802_packages')->where('source_id','=',(int)$source['id'])->where('direction','=','in')->where('entity_kind','=',$entity)->where('checksum','=',$checksum)->where('dry_run','=',$dry)->execute()->get('id');$p=$this->package($id);$p['duplicate']=TRUE;return $p;}
        $id=(int)$insert['id'];$seen=array();$row_no=0;
        foreach($records as $record){$row_no++;$external=trim((string)Arr::get($record,'external_id'));$hash=$this->content_hash($record);$duplicate=$external!=='' && isset($seen[$external]);if($external!=='')$seen[$external]=TRUE;
            DB::query(Database::INSERT,"INSERT INTO erp_802_package_rows(package_id,row_no,external_id,operation,checksum,status,reason_code,message,raw_data)
                VALUES(:package,:row,:external,'upsert',:checksum,:status,:reason,:message,cast(:raw AS jsonb)) RETURNING id")
                ->parameters(array(':package'=>$id,':row'=>$row_no,':external'=>$external!==''?$external:'__missing_'.$row_no,':checksum'=>$hash,':status'=>$duplicate?'error':'pending',':reason'=>$duplicate?'duplicate_external_id':NULL,':message'=>$duplicate?'Повтор external_id внутри пакета':NULL,':raw'=>json_encode($record,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))->execute();
        }
        return $this->package($id)+array('duplicate'=>FALSE);
    }

    public function receive_and_process($source,$entity,$format,$content,array $options=array(),$actor_id=NULL)
    {
        $package=$this->receive($source,$entity,$format,$content,$options,$actor_id);
        if(empty($package['duplicate']))$package=$this->process($package['id'],$actor_id);
        return $package;
    }

    public function process($package_id,$actor_id=NULL)
    {
        $package=$this->package($package_id);if(in_array($package['status'],array('completed','partial'),TRUE) && (int)$package['error_rows']===0)return $package;
        DB::update('erp_802_packages')->set(array('status'=>'processing','started_at'=>DB::expr('coalesce(started_at,CURRENT_TIMESTAMP)'),'updated_at'=>DB::expr('CURRENT_TIMESTAMP')))->where('id','=',(int)$package_id)->execute();
        $dry=Erp::bool($package['dry_run']);
        $rows=DB::select()->from('erp_802_package_rows')->where('package_id','=',(int)$package_id)->where('status','IN',array('pending','error'))->order_by('row_no','ASC')->execute()->as_array();
        foreach($rows as $row){if($row['reason_code']==='duplicate_external_id')continue;$data=is_array($row['raw_data'])?$row['raw_data']:json_decode($row['raw_data'],TRUE);
            try{$result=$this->import_item((int)$package['source_id'],$data,(int)$package_id,$dry,$actor_id);$this->finish_row($row['id'],$result['status'],$result,NULL,NULL);}
            catch(Throwable $e){$this->finish_row($row['id'],'error',NULL,$this->reason($e),$e->getMessage());}
        }
        $counts=DB::query(Database::SELECT,"SELECT count(*) FILTER(WHERE status='applied') applied,count(*) FILTER(WHERE status='planned') planned,count(*) FILTER(WHERE status='skipped') skipped,count(*) FILTER(WHERE status='error') errors FROM erp_802_package_rows WHERE package_id=:id")->param(':id',(int)$package_id)->execute()->current();
        $applied=(int)$counts['applied']+(int)$counts['planned'];$errors=(int)$counts['errors'];$status=$errors?($applied||$counts['skipped']?'partial':'failed'):'completed';
        // Final state and completion fact are atomic: a committed completed
        // package can never exist without its durable outbox message.
        $db=Database::instance();$db->begin();
        try{
            DB::update('erp_802_packages')->set(array('status'=>$status,'applied_rows'=>$applied,'skipped_rows'=>(int)$counts['skipped'],'error_rows'=>$errors,'error_summary'=>$errors?$errors.' строк требуют исправления':NULL,'finished_at'=>DB::expr('CURRENT_TIMESTAMP'),'updated_at'=>DB::expr('CURRENT_TIMESTAMP')))->where('id','=',(int)$package_id)->execute();
            $final=$this->package($package_id);
            if(!$dry && $status==='completed' && class_exists('Service_Erp208_Outbox'))Service_Erp208_Outbox::instance()->enqueue('erp802.package.completed',array('package_uuid'=>$final['uuid'],'entity_kind'=>$final['entity_kind'],'applied_rows'=>(int)$final['applied_rows']),'erp802:package:'.$final['uuid'].':completed',array('aggregate_type'=>'integration_package','aggregate_id'=>$final['uuid'],'correlation_id'=>$final['correlation_id']));
            $db->commit();
        }catch(Throwable $e){$db->rollback();throw $e;}
        Event::fire('erp802.package.'.$status,array('id'=>(int)$package_id,'status'=>$status,'actor_id'=>$actor_id));return $final;
    }

    protected function import_item($source_id,array $data,$package_id,$dry,$actor_id)
    {
        $external=trim((string)Arr::get($data,'external_id'));$code=trim((string)Arr::get($data,'code'));$name=trim((string)Arr::get($data,'name'));$type=trim((string)Arr::get($data,'type','component'));
        if($external==='' OR $name==='')throw new Kohana_Exception('Обязательны external_id и name');
        $typeRow=Service_Erp227_Registry::instance()->get_type_by_code($type);if(!$typeRow->loaded())throw new Kohana_Exception('Неизвестный тип номенклатуры: '.$type);
        $hash=$this->content_hash($data);$mapping=DB::select()->from('erp_802_mappings')->where('source_id','=',$source_id)->where('entity_kind','=','items')->where('external_id','=',$external)->execute()->current();
        if($mapping && hash_equals((string)$mapping['content_hash'],$hash))return array('status'=>'skipped','reason_code'=>'unchanged','message'=>'Данные не изменились','internal_id'=>(int)$mapping['internal_id'],'internal_uuid'=>$mapping['internal_uuid']);
        $existing=NULL;
        if($mapping){$existing=Service_Erp227_Registry::instance()->get_by_id((int)$mapping['internal_id']);if(!$existing->loaded())throw new Kohana_Exception('Mapping указывает на отсутствующую номенклатуру');}
        elseif($code!==''){$candidate=Service_Erp227_Registry::instance()->get_object($code,$typeRow->id);if($candidate->loaded())throw new Kohana_Exception('Код уже существует без mapping: '.$code);}
        if($dry)return array('status'=>'planned','reason_code'=>$mapping?'update':'create','message'=>$mapping?'Будет создана новая ревизия':'Будет создана номенклатура','internal_id'=>$mapping?(int)$mapping['internal_id']:NULL,'internal_uuid'=>$mapping?$mapping['internal_uuid']:NULL);
        $attributes=Arr::get($data,'attributes',array());if(is_string($attributes)){$decoded=json_decode($attributes,TRUE);$attributes=is_array($decoded)?$decoded:array();}
        $values=array('type_id'=>(int)$typeRow->id,'code'=>$code!==''?$code:NULL,'name'=>$name,'status'=>Arr::get($data,'status','active'),'attributes'=>(array)$attributes,'updated_by'=>$actor_id?:NULL);
        if($existing){$object=Service_Erp227_Registry::instance()->update_object($existing->id,$values);$action='updated';}else{$values['created_by']=$actor_id?:NULL;$object=Service_Erp227_Registry::instance()->create_object($values);$action='created';}
        DB::query(Database::INSERT,"INSERT INTO erp_802_mappings(source_id,entity_kind,external_id,internal_id,internal_uuid,external_version,content_hash,last_package_id)
            VALUES(:source,'items',:external,:internal,:uuid,:version,:hash,:package)
            ON CONFLICT(source_id,entity_kind,external_id) DO UPDATE SET internal_id=excluded.internal_id,internal_uuid=excluded.internal_uuid,external_version=excluded.external_version,content_hash=excluded.content_hash,last_package_id=excluded.last_package_id,updated_at=CURRENT_TIMESTAMP")
            ->parameters(array(':source'=>$source_id,':external'=>$external,':internal'=>(int)$object->id,':uuid'=>(string)$object->external_uuid,':version'=>Arr::get($data,'version'),':hash'=>$hash,':package'=>$package_id))->execute();
        return array('status'=>'applied','reason_code'=>$action,'message'=>$action==='created'?'Создано':'Создана новая ревизия','internal_id'=>(int)$object->id,'internal_uuid'=>(string)$object->external_uuid);
    }

    public function replay($package_id,$actor_id=NULL)
    {
        $p=$this->package($package_id);if(!in_array($p['status'],array('failed','partial'),TRUE))throw new Kohana_Exception('Повтор доступен только для failed/partial пакета');
        DB::update('erp_802_package_rows')->set(array('status'=>'pending','reason_code'=>NULL,'message'=>NULL,'result_data'=>NULL,'processed_at'=>NULL))->where('package_id','=',(int)$package_id)->where('status','=','error')->where('reason_code','!=','duplicate_external_id')->execute();
        DB::update('erp_802_packages')->set(array('status'=>'received','error_summary'=>NULL,'finished_at'=>NULL,'updated_at'=>DB::expr('CURRENT_TIMESTAMP')))->where('id','=',(int)$package_id)->execute();return $this->process($package_id,$actor_id);
    }

    public function package($id)
    {
        $p=DB::query(Database::SELECT,"SELECT p.*,s.code source_code,s.name source_name FROM erp_802_packages p JOIN erp_802_sources s ON s.id=p.source_id WHERE p.id=:id")->param(':id',(int)$id)->execute()->current();if(!$p)throw new Kohana_Exception('Интеграционный пакет не найден');return $p;
    }
    public function package_rows($id){return DB::select()->from('erp_802_package_rows')->where('package_id','=',(int)$id)->order_by('row_no','ASC')->execute()->as_array();}
    public function packages(array $filters=array(),$limit=50){$q=DB::query(Database::SELECT,"SELECT p.*,s.code source_code,s.name source_name FROM erp_802_packages p JOIN erp_802_sources s ON s.id=p.source_id WHERE (:status='' OR p.status=:status) AND (:source='' OR s.code=:source) ORDER BY p.id DESC LIMIT :limit")->parameters(array(':status'=>(string)Arr::get($filters,'status',''),':source'=>(string)Arr::get($filters,'source_code',''),':limit'=>max(1,min(200,(int)$limit))))->execute()->as_array();return $q;}
    public function sources(){return DB::select('code','name','kind')->from('erp_802_sources')->where('is_active','=',TRUE)->order_by('name')->execute()->as_array();}

    protected function source($code){$r=DB::select()->from('erp_802_sources')->where('code','=',trim((string)$code))->where('is_active','=',TRUE)->execute()->current();if(!$r)throw new Kohana_Exception('Источник интеграции не найден или выключен');return $r;}
    protected function parse($format,$content)
    {
        if($format==='json'){$data=json_decode($content,TRUE);if(!is_array($data))throw new Kohana_Exception('Некорректный JSON');if(isset($data['items'])&&is_array($data['items']))$data=$data['items'];if(array_keys($data)!==range(0,count($data)-1))throw new Kohana_Exception('JSON должен содержать массив items');return $data;}
        $stream=fopen('php://temp','r+');fwrite($stream,$content);rewind($stream);$header=fgetcsv($stream,0,';');if(!$header OR count($header)===1){rewind($stream);$header=fgetcsv($stream,0,',');$delimiter=',';}else$delimiter=';';$header=array_map(function($v){return trim((string)$v);},$header);$rows=array();while(($values=fgetcsv($stream,0,$delimiter))!==FALSE){if(count($values)===1 && trim((string)$values[0])==='')continue;$values=array_pad($values,count($header),NULL);$rows[]=array_combine($header,array_slice($values,0,count($header)));}fclose($stream);return $rows;
    }
    protected function finish_row($id,$status,$result,$reason,$message){DB::update('erp_802_package_rows')->set(array('status'=>$status,'reason_code'=>$reason?:Arr::get((array)$result,'reason_code'),'message'=>$message?:Arr::get((array)$result,'message'),'result_data'=>$result?json_encode($result,JSON_UNESCAPED_UNICODE):NULL,'internal_id'=>Arr::get((array)$result,'internal_id'),'internal_uuid'=>Arr::get((array)$result,'internal_uuid'),'processed_at'=>DB::expr('CURRENT_TIMESTAMP')))->where('id','=',(int)$id)->execute();}
    protected function content_hash(array $data){unset($data['_comment']);$data=$this->canonical($data);return hash('sha256',json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    protected function canonical($v){if(!is_array($v))return $v;if(array_keys($v)!==range(0,count($v)-1))ksort($v);foreach($v as $k=>$x)$v[$k]=$this->canonical($x);return $v;}
    protected function reason(Throwable $e){$m=mb_strtolower($e->getMessage());if(strpos($m,'обязательны')!==FALSE)return 'required';if(strpos($m,'тип')!==FALSE)return 'unknown_type';if(strpos($m,'код уже')!==FALSE)return 'code_conflict';if(strpos($m,'mapping')!==FALSE)return 'orphan_mapping';return 'processing_error';}
}
