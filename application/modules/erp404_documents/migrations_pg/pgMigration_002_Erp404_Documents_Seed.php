<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Документы и печать (erp404_documents): стартовые шаблоны.
 *
 * Заводим четыре рабочие формы, без которых модуль нельзя начать пользовать:
 * заказ поставщику (erp403), приходная накладная (erp403), производственный
 * заказ (erp402) и паспорт качества (erp405). Это данные, а не демонстрация:
 * пустой шаблон печатать нечем, а сочинять формы «для примера» — значит
 * оставить пользователя с чужим документом.
 *
 * Синтаксис подстановок описан в `application/modules/erp404_documents/README.md`:
 *
 *   {{ref.number}}          поле источника (из Service_Erp404_Sources);
 *   {{document.number}}     поле выпускаемого документа;
 *   {{meta.company}}        общие данные организации и даты;
 *   {{#rows ref.lines}}…{{/rows}}  повтор блока по строкам источника,
 *                                  внутри доступны поля строки и {{index}}.
 *
 * Миграция идемпотентна: `ON CONFLICT (code) DO NOTHING` — повторный прогон не
 * задваивает шаблоны и не затирает правки пользователя (тело шаблона правится
 * в интерфейсе, и это его версия, а не наша).
 *
 * @package    Erp404
 * @category   Migrations
 */
class pgMigration_002_Erp404_Documents_Seed extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        foreach ($this->_templates() as $code => $template)
        {
            $ref_type = $template['ref_type'];
            $name     = $template['name'];
            $kind     = $template['kind'];
            $body     = $template['body'];

            $this->run_query("
                INSERT INTO erp_404_templates (code, name, ref_type, kind, body, version, is_active, note, search_text)
                VALUES (
                    '".$this->_esc($code)."',
                    '".$this->_esc($name)."',
                    '".$this->_esc($ref_type)."',
                    '".$this->_esc($kind)."',
                    \$tpl\$".$body."\$tpl\$,
                    1,
                    1,
                    'Стартовый шаблон erp404_documents',
                    '".$this->_esc($code.' '.$name)."'
                )
                ON CONFLICT (code) DO NOTHING
            ");
        }
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->run_query("DELETE FROM erp_404_templates WHERE note = 'Стартовый шаблон erp404_documents'");
    }

    /**
     * Экранирование строковой константы для SQL.
     *
     * @param   string  $value
     * @return  string
     */
    protected function _esc($value)
    {
        return str_replace("'", "''", (string) $value);
    }

    /**
     * Шаблоны: код => описание и тело.
     *
     * @return  array
     */
    protected function _templates()
    {
        return array(
            'ORDER-SUPPLIER' => array(
                'name'     => 'Заказ поставщику',
                'ref_type' => 'erp403_order',
                'kind'     => 'print',
                'body'     => $this->_body_order(),
            ),
            'RECEIPT-IN' => array(
                'name'     => 'Приходная накладная',
                'ref_type' => 'erp403_receipt',
                'kind'     => 'print',
                'body'     => $this->_body_receipt(),
            ),
            'PROD-ORDER' => array(
                'name'     => 'Производственный заказ',
                'ref_type' => 'erp402_order',
                'kind'     => 'print',
                'body'     => $this->_body_production(),
            ),
            'QUALITY-PASSPORT' => array(
                'name'     => 'Паспорт качества',
                'ref_type' => 'erp405_inspection',
                'kind'     => 'print',
                'body'     => $this->_body_quality(),
            ),
        );
    }

    /**
     * Заказ поставщику.
     *
     * @return  string
     */
    protected function _body_order()
    {
        return <<<'HTML'
<h1>Заказ поставщику № {{ref.number}}</h1>

<p class="muted">
  Документ: {{document.number}} от {{meta.date}}<br>
  Организация: {{meta.company}}<br>
  Поставщик: {{ref.supplier_name}}<br>
  Дата поставки: {{ref.delivery_date}}<br>
  Склад приёмки: {{ref.warehouse_name}}
</p>

<table>
  <thead>
    <tr><th>#</th><th>Позиция</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr>
  </thead>
  <tbody>
    {{#rows ref.lines}}<tr>
      <td>{{index}}</td>
      <td>{{item_name}}</td>
      <td>{{qty}}</td>
      <td>{{price}}</td>
      <td>{{amount}}</td>
    </tr>{{/rows}}
  </tbody>
  <tfoot>
    <tr><td colspan="4">Итого</td><td>{{ref.amount}}</td></tr>
  </tfoot>
</table>

<p class="sign">Заказчик: ______________________ / {{meta.user}}<br>
Поставщик: ______________________</p>
HTML;
    }

    /**
     * Приходная накладная.
     *
     * @return  string
     */
    protected function _body_receipt()
    {
        return <<<'HTML'
<h1>Приходная накладная № {{ref.number}}</h1>

<p class="muted">
  Документ: {{document.number}} от {{meta.date}}<br>
  Поставщик: {{ref.supplier_name}}<br>
  Склад: {{ref.warehouse_name}}<br>
  Статус приёмки: {{ref.status_label}}
</p>

<table>
  <thead>
    <tr><th>#</th><th>Позиция</th><th>Принято</th><th>Входной контроль</th><th>Цена</th></tr>
  </thead>
  <tbody>
    {{#rows ref.lines}}<tr>
      <td>{{index}}</td>
      <td>{{item_name}}</td>
      <td>{{qty}}</td>
      <td>{{quality_label}}</td>
      <td>{{price}}</td>
    </tr>{{/rows}}
  </tbody>
</table>

<p class="sign">Принял: ______________________ / {{meta.user}}</p>
HTML;
    }

    /**
     * Производственный заказ.
     *
     * @return  string
     */
    protected function _body_production()
    {
        return <<<'HTML'
<h1>Производственный заказ № {{ref.number}}</h1>

<p class="muted">
  Документ: {{document.number}} от {{meta.date}}<br>
  Номенклатура: {{ref.item_name}}<br>
  План: {{ref.qty}} за {{ref.due_date}}<br>
  Состояние: {{ref.status_label}}
</p>

<h2>Состав</h2>
<table>
  <thead>
    <tr><th>#</th><th>Материал</th><th>Норма</th><th>К выдаче</th></tr>
  </thead>
  <tbody>
    {{#rows ref.materials}}<tr>
      <td>{{index}}</td>
      <td>{{item_name}}</td>
      <td>{{norm}}</td>
      <td>{{required}}</td>
    </tr>{{/rows}}
  </tbody>
</table>

<h2>Маршрут</h2>
<table>
  <thead>
    <tr><th>#</th><th>Операция</th><th>Центр</th><th>План, мин</th></tr>
  </thead>
  <tbody>
    {{#rows ref.route}}<tr>
      <td>{{index}}</td>
      <td>{{operation_name}}</td>
      <td>{{work_center_name}}</td>
      <td>{{planned_minutes}}</td>
    </tr>{{/rows}}
  </tbody>
</table>

<p class="sign">Мастер: ______________________ / {{meta.user}}</p>
HTML;
    }

    /**
     * Паспорт качества.
     *
     * @return  string
     */
    protected function _body_quality()
    {
        return <<<'HTML'
<h1>Паспорт качества № {{ref.number}}</h1>

<p class="muted">
  Документ: {{document.number}} от {{meta.date}}<br>
  Организация: {{meta.company}}<br>
  Партия: {{ref.item_name}}, {{ref.qty}} ед.<br>
  Вид контроля: {{ref.kind_label}}<br>
  Итог: {{ref.result_label}}
</p>

<table>
  <thead>
    <tr><th>#</th><th>Несоответствие</th><th>Кол-во</th><th>Причина</th></tr>
  </thead>
  <tbody>
    {{#rows ref.defects}}<tr>
      <td>{{index}}</td>
      <td>{{code}} — {{code_name}}</td>
      <td>{{qty}}</td>
      <td>{{cause_label}}</td>
    </tr>{{/rows}}
  </tbody>
</table>

<p class="muted">Решение по партии: {{ref.decision_label}}</p>

<p class="sign">Контролёр: ______________________ / {{meta.user}}</p>
HTML;
    }
}
