<?php defined('SYSPATH') or die('No direct script access.');

/** Versioned EBOM/MBOM aggregate. Approved revisions are immutable. */
class Service_Erp231_ProductStructure
{
    protected static $_instance;
    public static function instance() { return self::$_instance ?: (self::$_instance = new self); }
    protected function __construct() {}

    public function search(array $filters = array(), $limit = 50, $offset = 0)
    {
        $where = array('1=1'); $params = array();
        foreach (array('kind','status','site_code') as $field) if ( ! empty($filters[$field])) {
            $where[] = 's.'.$field.'=:'.$field; $params[':'.$field] = (string)$filters[$field];
        }
        if ( ! empty($filters['product_item_id'])) { $where[]='s.product_item_id=:product'; $params[':product']=(int)$filters['product_item_id']; }
        if ( ! empty($filters['term'])) { $where[]="(lower(coalesce(o.code,'')) LIKE :term OR lower(o.name) LIKE :term OR lower(s.revision) LIKE :term)"; $params[':term']='%'.mb_strtolower((string)$filters['term']).'%'; }
        $sql=' FROM erp_231_structures s JOIN erp_227_objects o ON o.id=s.product_item_id WHERE '.implode(' AND ',$where);
        $total=(int)DB::query(Database::SELECT,'SELECT count(*) n'.$sql)->parameters($params)->execute()->get('n',0);
        $params[':limit']=max(1,min(200,(int)$limit)); $params[':offset']=max(0,(int)$offset);
        $rows=DB::query(Database::SELECT,"SELECT s.*,o.code product_code,o.name product_name,(SELECT count(*) FROM erp_231_lines l WHERE l.structure_id=s.id) line_count$sql ORDER BY s.updated_at DESC,s.id DESC LIMIT :limit OFFSET :offset")
            ->parameters($params)->execute()->as_array();
        return array('rows'=>$rows,'total'=>$total);
    }

    public function get($id, $with_lines = TRUE)
    {
        $row=DB::query(Database::SELECT,"SELECT s.*,o.code product_code,o.name product_name FROM erp_231_structures s JOIN erp_227_objects o ON o.id=s.product_item_id WHERE s.id=:id")
            ->param(':id',(int)$id)->execute()->current();
        if ( ! $row) throw new Kohana_Exception('Спецификация не найдена');
        if ($with_lines) $row['lines']=$this->lines($id);
        return $row;
    }

    public function lines($structure_id)
    {
        return DB::query(Database::SELECT,"SELECT l.*,o.code component_code,o.name component_name FROM erp_231_lines l JOIN erp_227_objects o ON o.id=l.component_item_id WHERE l.structure_id=:id ORDER BY l.line_no,l.id")
            ->param(':id',(int)$structure_id)->execute()->as_array();
    }

    public function create(array $data, $actor_id = NULL)
    {
        $product=(int)Arr::get($data,'product_item_id'); $kind=(string)Arr::get($data,'kind','ebom');
        $site=trim((string)Arr::get($data,'site_code',''));
        if ( ! in_array($kind,array('ebom','mbom','service'),TRUE)) throw new Kohana_Exception('Неизвестный вид спецификации');
        $this->assert_item($product,'Изделие');
        $revision=trim((string)Arr::get($data,'revision'));
        if ($revision==='') {
            $n=(int)DB::query(Database::SELECT,'SELECT count(*) n FROM erp_231_structures WHERE product_item_id=:p AND kind=:k AND site_code=:s')
                ->parameters(array(':p'=>$product,':k'=>$kind,':s'=>$site))->execute()->get('n',0);
            $revision='R'.str_pad((string)($n+1),2,'0',STR_PAD_LEFT);
        }
        $result=DB::insert('erp_231_structures',array('product_item_id','kind','revision','status','site_code','source_system','source_revision','tech_card_id','effective_from','effective_to','created_by'))
            ->values(array($product,$kind,$revision,'draft',$site,Arr::get($data,'source_system'),Arr::get($data,'source_revision'),Arr::get($data,'tech_card_id')?:NULL,Arr::get($data,'effective_from')?:NULL,Arr::get($data,'effective_to')?:NULL,$actor_id?:NULL))->execute();
        $id=(int)$result[0]; $this->event('erp231.structure.created',$id,$actor_id);
        return $this->get($id);
    }

    public function add_line($structure_id, array $data, $actor_id = NULL)
    {
        $structure=$this->editable($structure_id); $component=(int)Arr::get($data,'component_item_id');
        $this->assert_item($component,'Компонент');
        if ($component===(int)$structure['product_item_id']) throw new Kohana_Exception('Изделие не может содержать само себя');
        $parent=Arr::get($data,'parent_line_id')?(int)Arr::get($data,'parent_line_id'):NULL;
        if ($parent) {
            $owner=(int)DB::select('structure_id')->from('erp_231_lines')->where('id','=',$parent)->execute()->get('structure_id',0);
            if ($owner!==(int)$structure_id) throw new Kohana_Exception('Родительская строка относится к другой спецификации');
        }
        $qty=(float)Arr::get($data,'qty'); if ($qty<=0) throw new Kohana_Exception('Количество должно быть больше нуля');
        $uom=(string)Arr::get($data,'uom','pcs');
        $line_no=(int)Arr::get($data,'line_no');
        if ($line_no<=0) $line_no=(int)DB::query(Database::SELECT,'SELECT coalesce(max(line_no),0)+10 n FROM erp_231_lines WHERE structure_id=:id')->param(':id',(int)$structure_id)->execute()->get('n',10);
        $supply=(string)Arr::get($data,'supply_type','buy'); if ( ! in_array($supply,array('make','buy','phantom','consumable'),TRUE)) throw new Kohana_Exception('Неизвестный тип обеспечения');
        $result=DB::insert('erp_231_lines',array('structure_id','line_no','parent_line_id','component_item_id','qty','uom','scrap_pct','supply_type','operation_no','effectivity','alternatives','note','created_by'))
            ->values(array((int)$structure_id,$line_no,$parent,$component,$qty,$uom,(float)Arr::get($data,'scrap_pct',0),$supply,Arr::get($data,'operation_no')?:NULL,json_encode((array)Arr::get($data,'effectivity',array()),JSON_UNESCAPED_UNICODE),json_encode((array)Arr::get($data,'alternatives',array()),JSON_UNESCAPED_UNICODE),Arr::get($data,'note'),$actor_id?:NULL))->execute();
        DB::update('erp_231_structures')->set(array('updated_at'=>DB::expr('CURRENT_TIMESTAMP')))->where('id','=',(int)$structure_id)->execute();
        return (int)$result[0];
    }

    public function remove_line($line_id)
    {
        $row=DB::select('structure_id')->from('erp_231_lines')->where('id','=',(int)$line_id)->execute()->current();
        if ( ! $row) throw new Kohana_Exception('Строка не найдена');
        $this->editable((int)$row['structure_id']);
        DB::delete('erp_231_lines')->where('id','=',(int)$line_id)->execute();
    }

    public function validate($structure_id)
    {
        $s=$this->get($structure_id); $errors=array(); $seen=array();
        foreach($s['lines'] as $line) {
            if (isset($seen[$line['line_no']])) $errors[]='Повтор номера строки '.$line['line_no'];
            $seen[$line['line_no']]=TRUE;
            if ((int)$line['component_item_id']===(int)$s['product_item_id']) $errors[]='Самоссылка в строке '.$line['line_no'];
        }
        // Follow structures by product/component and reject A → … → A.
        $cycle=DB::query(Database::SELECT,"WITH RECURSIVE g(product_id,component_id,path,cycle) AS (
            SELECT s.product_item_id,l.component_item_id,ARRAY[s.product_item_id,l.component_item_id],l.component_item_id=s.product_item_id
            FROM erp_231_structures s JOIN erp_231_lines l ON l.structure_id=s.id WHERE s.id=:id
            UNION ALL
            SELECT g.product_id,l.component_item_id,g.path||l.component_item_id,l.component_item_id=ANY(g.path)
            FROM g JOIN erp_231_structures s ON s.product_item_id=g.component_id AND s.status IN ('draft','review','approved')
            JOIN erp_231_lines l ON l.structure_id=s.id WHERE NOT g.cycle AND cardinality(g.path)<50)
            SELECT 1 found FROM g WHERE cycle LIMIT 1")
            ->param(':id',(int)$structure_id)->execute()->current();
        if ($cycle) $errors[]='Обнаружен цикл состава изделия';
        return array('valid'=> ! $errors,'errors'=>$errors,'line_count'=>count($s['lines']));
    }

    public function submit_review($id, $actor_id = NULL)
    {
        $this->editable($id); $check=$this->validate($id);
        if ( ! $check['valid']) throw new Kohana_Exception('Спецификация не прошла проверку: :errors',array(':errors'=>implode('; ',$check['errors'])));
        DB::update('erp_231_structures')->set(array('status'=>'review','updated_at'=>DB::expr('CURRENT_TIMESTAMP')))->where('id','=',(int)$id)->execute();
        $this->event('erp231.structure.review',$id,$actor_id); return $this->get($id);
    }

    public function approve($id, $actor_id)
    {
        $s=$this->get($id); if ($s['status']!=='review') throw new Kohana_Exception('Утвердить можно только проверяемую ревизию');
        $check=$this->validate($id); if ( ! $check['valid'] OR !$check['line_count']) throw new Kohana_Exception('Нельзя утвердить пустую или ошибочную спецификацию');
        $db=Database::instance(); $db->begin();
        try {
            DB::update('erp_231_structures')->set(array('status'=>'obsolete','effective_to'=>DB::expr('CURRENT_DATE'),'updated_at'=>DB::expr('CURRENT_TIMESTAMP')))
                ->where('product_item_id','=',(int)$s['product_item_id'])->where('kind','=',$s['kind'])->where('site_code','=',$s['site_code'])->where('status','=','approved')->execute();
            DB::update('erp_231_structures')->set(array('status'=>'approved','approved_by'=>$actor_id?(int)$actor_id:NULL,'approved_at'=>DB::expr('CURRENT_TIMESTAMP'),'effective_from'=>DB::expr('coalesce(effective_from,CURRENT_DATE)'),'updated_at'=>DB::expr('CURRENT_TIMESTAMP')))->where('id','=',(int)$id)->execute();
            $db->commit();
        } catch(Exception $e) { $db->rollback(); throw $e; }
        $this->event('erp231.structure.approved',$id,$actor_id); return $this->get($id);
    }

    public function copy_ebom_to_mbom($ebom_id, array $options = array(), $actor_id = NULL)
    {
        $ebom=$this->get($ebom_id); if ($ebom['kind']!=='ebom') throw new Kohana_Exception('Источник должен быть EBOM');
        if ($ebom['status']!=='approved') throw new Kohana_Exception('MBOM создаётся только из утверждённой EBOM');
        $mbom=$this->create(array('product_item_id'=>$ebom['product_item_id'],'kind'=>'mbom','site_code'=>Arr::get($options,'site_code',$ebom['site_code']),'revision'=>Arr::get($options,'revision'),'source_system'=>'erp231','source_revision'=>$ebom['revision'],'tech_card_id'=>Arr::get($options,'tech_card_id')), $actor_id);
        $line_map=array();
        foreach($ebom['lines'] as $line) {
            $parent=$line['parent_line_id'] && isset($line_map[$line['parent_line_id']])?$line_map[$line['parent_line_id']]:NULL;
            $new=$this->add_line($mbom['id'],array('line_no'=>$line['line_no'],'parent_line_id'=>$parent,'component_item_id'=>$line['component_item_id'],'qty'=>$line['qty'],'uom'=>$line['uom'],'scrap_pct'=>$line['scrap_pct'],'supply_type'=>$line['supply_type'],'operation_no'=>$line['operation_no'],'note'=>$line['note']),$actor_id);
            $line_map[$line['id']]=$new;
            DB::insert('erp_231_mappings',array('mbom_structure_id','ebom_line_id','mbom_line_id','mapping_kind','reason','created_by'))->values(array((int)$mbom['id'],(int)$line['id'],$new,'same','Первичное копирование EBOM → MBOM',$actor_id?:NULL))->execute();
        }
        $this->event('erp231.mbom.created',$mbom['id'],$actor_id); return $this->get($mbom['id']);
    }

    public function mappings($mbom_id)
    {
        return DB::query(Database::SELECT,"SELECT m.*,e.line_no ebom_line_no,me.name ebom_component,b.line_no mbom_line_no,mb.name mbom_component FROM erp_231_mappings m JOIN erp_231_lines e ON e.id=m.ebom_line_id JOIN erp_227_objects me ON me.id=e.component_item_id LEFT JOIN erp_231_lines b ON b.id=m.mbom_line_id LEFT JOIN erp_227_objects mb ON mb.id=b.component_item_id WHERE m.mbom_structure_id=:id ORDER BY e.line_no,b.line_no")
            ->param(':id',(int)$mbom_id)->execute()->as_array();
    }

    protected function editable($id) { $s=$this->get($id,FALSE); if ( ! in_array($s['status'],array('draft','review'),TRUE)) throw new Kohana_Exception('Утверждённая или устаревшая ревизия неизменяема'); return $s; }
    protected function assert_item($id,$label) { $row=DB::select('id')->from('erp_227_objects')->where('id','=',(int)$id)->where('is_deleted','=',FALSE)->execute()->current(); if(!$row) throw new Kohana_Exception($label.' номенклатуры не найдено'); }
    protected function event($name,$id,$actor) { try { Event::fire($name,array('id'=>(int)$id,'actor_id'=>$actor)); } catch(Exception $e) { Kohana::$log->add(Log::WARNING,'erp231 event failed: :error',array(':error'=>$e->getMessage())); } }
}
