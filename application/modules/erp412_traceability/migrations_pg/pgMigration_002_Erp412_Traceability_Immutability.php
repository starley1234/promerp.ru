<?php defined('SYSPATH') or die('No direct script access.');
/** Harden trace facts after the initial schema: facts are corrected by compensating records. */
class pgMigration_002_Erp412_Traceability_Immutability extends pgMigration
{
 public function up()
 {
  $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_412_genealogy_fact_uq ON erp_412_genealogy(input_lot_id,output_lot_id,coalesce(order_id,0),coalesce(operation_id,0),relation)");
  $this->run_query("CREATE OR REPLACE FUNCTION erp_412_trace_fact_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION '% is append-only',TG_TABLE_NAME; END $$");
  foreach(array('erp_412_allocations','erp_412_genealogy') as $table){$this->run_query("DROP TRIGGER IF EXISTS {$table}_immutable_trg ON {$table}");$this->run_query("CREATE TRIGGER {$table}_immutable_trg BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION erp_412_trace_fact_immutable()");}
 }
 public function down()
 {
  foreach(array('erp_412_allocations','erp_412_genealogy') as $table)$this->run_query("DROP TRIGGER IF EXISTS {$table}_immutable_trg ON {$table}");
  $this->run_query("DROP FUNCTION IF EXISTS erp_412_trace_fact_immutable()");$this->run_query("DROP INDEX IF EXISTS erp_412_genealogy_fact_uq");
 }
}
