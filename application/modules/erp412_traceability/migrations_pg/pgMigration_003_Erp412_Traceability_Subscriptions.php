<?php defined('SYSPATH') or die('No direct script access.');
class pgMigration_003_Erp412_Traceability_Subscriptions extends pgMigration
{
 public function up()
 {
  $this->run_query("INSERT INTO erp_208_event_subscribers(event_name,module_id,callback_method,is_notification,is_active,description) VALUES
   ('erp401.stock_received','erp412','Service_Erp412_Traceability::on_stock_received',false,true,'Создать lot identity и receipt allocation'),
   ('erp405.inspection.quarantined','erp412','Service_Erp412_Traceability::on_quality_quarantined',false,true,'Поставить связанную партию в карантин'),
   ('erp405.inspection.released','erp412','Service_Erp412_Traceability::on_quality_released',false,true,'Разрешить связанную партию'),
   ('erp405.scrap.written_off','erp412','Service_Erp412_Traceability::on_quality_scrapped',false,true,'Списать связанную партию')
   ON CONFLICT(event_name,module_id,callback_method) DO UPDATE SET is_active=true,description=excluded.description");
 }
 public function down(){$this->run_query("DELETE FROM erp_208_event_subscribers WHERE module_id='erp412'");}
}
