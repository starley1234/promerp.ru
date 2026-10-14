<?php defined('SYSPATH') or die('No direct script access.');
$s=function(array $p,array $r=array()){return array('type'=>'object','properties'=>$p,'required'=>$r);};
$f=function($type,$description,array $extra=array()){return array('type'=>$type,'description'=>$description)+$extra;};
return array('module'=>'erp231','tools'=>array(
    'product_structure.search'=>array(
        'title'=>'Поиск EBOM/MBOM','description'=>'Ищет ревизии спецификаций по изделию, виду, состоянию и площадке. Сначала используйте этот инструмент вместо угадывания ID.',
        'scope'=>'erp231.view','mutating'=>FALSE,
        'handler'=>function(array $a){$r=Service_Erp231_ProductStructure::instance()->search($a,isset($a['limit'])?(int)$a['limit']:50);return array('summary'=>'Найдено ревизий: '.$r['total'])+$r;},
        'schema'=>$s(array('term'=>$f('string','Код, название или ревизия'),'product_item_id'=>$f('integer','ID изделия из реестра'),'kind'=>$f('string','Вид',array('enum'=>array('ebom','mbom','service'))),'status'=>$f('string','Статус',array('enum'=>array('draft','review','approved','obsolete'))),'site_code'=>$f('string','Код площадки'),'limit'=>$f('integer','Лимит',array('minimum'=>1,'maximum'=>200))))
    ),
    'product_structure.get'=>array(
        'title'=>'Ревизия EBOM/MBOM','description'=>'Возвращает шапку и строки спецификации с человекочитаемыми кодами и названиями.',
        'scope'=>'erp231.view','mutating'=>FALSE,'handler'=>function(array $a){$r=Service_Erp231_ProductStructure::instance()->get((int)$a['id']);return array('summary'=>$r['kind'].' '.$r['revision'].' · '.$r['product_name'],'structure'=>$r);},
        'schema'=>$s(array('id'=>$f('integer','ID из product_structure.search')),array('id'))
    ),
    'product_structure.validate'=>array(
        'title'=>'Проверить EBOM/MBOM','description'=>'Детерминированно проверяет ссылки и циклы перед передачей человеку на согласование.',
        'scope'=>'erp231.view','mutating'=>FALSE,'handler'=>function(array $a){$r=Service_Erp231_ProductStructure::instance()->validate((int)$a['id']);return array('summary'=>$r['valid']?'Ошибок не найдено':'Найдены ошибки')+$r;},
        'schema'=>$s(array('id'=>$f('integer','ID ревизии')),array('id'))
    ),
    'product_structure.draft.create'=>array(
        'title'=>'Создать черновик EBOM/MBOM','description'=>'Создаёт только draft. Агент не может утвердить спецификацию; product_item_id выбирается через инструменты реестра.',
        'scope'=>'erp231.manage','mutating'=>TRUE,'idempotent'=>FALSE,'handler'=>function(array $a){$r=Service_Erp231_ProductStructure::instance()->create($a,NULL);return array('summary'=>'Создан черновик '.$r['kind'].' '.$r['revision'],'structure'=>$r);},
        'schema'=>$s(array('product_item_id'=>$f('integer','ID изделия'),'kind'=>$f('string','Вид',array('enum'=>array('ebom','mbom','service'))),'revision'=>$f('string','Ревизия; пусто — автоматически'),'site_code'=>$f('string','Площадка'),'source_system'=>$f('string','Источник'),'source_revision'=>$f('string','Ревизия источника')),array('product_item_id','kind'))
    ),
    'product_structure.line.add'=>array(
        'title'=>'Добавить строку в черновик','description'=>'Добавляет компонент в draft/review; проверяет номенклатуру, количество и принадлежность родителя.',
        'scope'=>'erp231.manage','mutating'=>TRUE,'idempotent'=>FALSE,'handler'=>function(array $a){$id=Service_Erp231_ProductStructure::instance()->add_line((int)$a['structure_id'],$a,NULL);return array('summary'=>'Добавлена строка #'.$id,'line_id'=>$id);},
        'schema'=>$s(array('structure_id'=>$f('integer','ID черновика'),'component_item_id'=>$f('integer','ID компонента из реестра'),'qty'=>$f('number','Количество',array('exclusiveMinimum'=>0)),'uom'=>$f('string','Единица erp227, например pcs'),'line_no'=>$f('integer','Номер строки'),'parent_line_id'=>$f('integer','Родительская строка'),'supply_type'=>$f('string','Обеспечение',array('enum'=>array('make','buy','phantom','consumable'))),'scrap_pct'=>$f('number','Процент отхода'),'operation_no'=>$f('integer','Номер операции'),'note'=>$f('string','Примечание')),array('structure_id','component_item_id','qty'))
    ),
    'product_structure.approve'=>array(
        'title'=>'Утвердить ревизию','description'=>'Критическое действие человека: делает состав неизменяемым и замещает предыдущую утверждённую ревизию.',
        'scope'=>'erp231.approve','mutating'=>TRUE,'approval'=>TRUE,'destructive'=>FALSE,'handler'=>function(array $a){$r=Service_Erp231_ProductStructure::instance()->approve((int)$a['id'],NULL);return array('summary'=>'Утверждена '.$r['kind'].' '.$r['revision'],'structure'=>$r);},
        'schema'=>$s(array('id'=>$f('integer','ID ревизии в статусе review')),array('id'))
    )
));
