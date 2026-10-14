<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Dedicated daily shift-assignment form sourced from the erp408 dispatch board.
 *
 * The reference is a human-readable ISO day, not an internal database ID.
 * Issuing the erp404 document freezes the board in an immutable snapshot.
 */
class pgMigration_004_Erp404_Documents_SeedShiftAssignment extends pgMigration
{
    public function up()
    {
        $body = <<<'HTML'
<h1>Сменное задание на {{ref.day}}</h1>

<p class="muted">
  Документ: {{document.number}} от {{meta.date}}<br>
  Организация: {{meta.company}}<br>
  Операций: {{ref.operations}} · Плановых часов: {{ref.hours}}
</p>

<table>
  <thead>
    <tr><th>#</th><th>Центр / смена</th><th>Заказ</th><th>Операция</th><th>Задание</th><th>Кол-во</th><th>Часы</th><th>Статус</th></tr>
  </thead>
  <tbody>
    {{#rows ref.lines}}<tr>
      <td>{{index}}</td>
      <td>{{center}}<br><span class="muted">{{shift}}</span></td>
      <td>{{order_number}}</td>
      <td>{{op_no}}</td>
      <td>{{name}}</td>
      <td>{{qty_planned}}</td>
      <td>{{hours}}</td>
      <td>{{status_label}}</td>
    </tr>{{/rows}}
  </tbody>
</table>

<p class="sign">Выдал: ______________________ / {{meta.user}}<br>
Мастер смены: ______________________</p>
HTML;

        $this->run_query("
            INSERT INTO erp_404_templates
                (code, name, ref_type, kind, body, version, is_active, note, search_text)
            VALUES
                ('SHIFT-ASSIGNMENT', 'Сменное задание', 'erp408_shift_day', 'print',
                 \$tpl\$".$body."\$tpl\$, 1, 1,
                 'Стартовый шаблон erp404_documents',
                 'SHIFT-ASSIGNMENT Сменное задание цех производство')
            ON CONFLICT (code) DO NOTHING
        ");
    }

    public function down()
    {
        $this->run_query("DELETE FROM erp_404_templates WHERE code = 'SHIFT-ASSIGNMENT' AND note = 'Стартовый шаблон erp404_documents'");
    }
}
