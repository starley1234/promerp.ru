<?php defined('SYSPATH') or die('No direct script access.');
class pgMigration_001_Erp412_Traceability_CreateTables extends pgMigration
{
 public function up()
 {
  $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_412_lot_number_seq");
  $this->run_query("CREATE TABLE IF NOT EXISTS erp_412_lots(
   id BIGSERIAL PRIMARY KEY,uuid UUID NOT NULL DEFAULT gen_random_uuid(),item_id INTEGER NOT NULL,lot_number VARCHAR(80) NOT NULL,serial_number VARCHAR(120),tracking_mode VARCHAR(12) NOT NULL DEFAULT 'lot',status VARCHAR(16) NOT NULL DEFAULT 'available',source_type VARCHAR(32),source_id INTEGER,supplier_id INTEGER,manufactured_at TIMESTAMPTZ,expires_at TIMESTAMPTZ,metadata JSONB NOT NULL DEFAULT '{}'::jsonb,created_by INTEGER,created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
   CONSTRAINT erp_412_lots_uuid_uq UNIQUE(uuid),CONSTRAINT erp_412_lots_item_number_uq UNIQUE(item_id,lot_number),CONSTRAINT erp_412_lots_tracking_ck CHECK(tracking_mode IN('lot','serial')),CONSTRAINT erp_412_lots_status_ck CHECK(status IN('available','quarantine','consumed','scrapped','recalled')))");
  $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_412_lots_serial_uq ON erp_412_lots(serial_number) WHERE serial_number IS NOT NULL");
  $this->run_query("CREATE UNIQUE INDEX IF NOT EXISTS erp_412_lots_source_uq ON erp_412_lots(source_type,source_id) WHERE source_type IS NOT NULL AND source_id IS NOT NULL");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_lots_item_idx ON erp_412_lots(item_id,status)");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_lots_number_idx ON erp_412_lots(lot_number)");

  $this->run_query("CREATE TABLE IF NOT EXISTS erp_412_allocations(
   id BIGSERIAL PRIMARY KEY,lot_id BIGINT NOT NULL REFERENCES erp_412_lots(id) ON DELETE RESTRICT,movement_id INTEGER NOT NULL,direction VARCHAR(16) NOT NULL,warehouse_id INTEGER NOT NULL,qty NUMERIC(18,6) NOT NULL,created_by INTEGER,created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
   CONSTRAINT erp_412_alloc_qty_ck CHECK(qty>0),CONSTRAINT erp_412_alloc_direction_ck CHECK(direction IN('receipt','issue','transfer_out','transfer_in','adjust_in','adjust_out')),CONSTRAINT erp_412_alloc_uq UNIQUE(lot_id,movement_id,direction,warehouse_id))");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_alloc_movement_idx ON erp_412_allocations(movement_id)");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_alloc_lot_idx ON erp_412_allocations(lot_id,warehouse_id)");

  $this->run_query("CREATE TABLE IF NOT EXISTS erp_412_genealogy(
   id BIGSERIAL PRIMARY KEY,input_lot_id BIGINT NOT NULL REFERENCES erp_412_lots(id) ON DELETE RESTRICT,output_lot_id BIGINT NOT NULL REFERENCES erp_412_lots(id) ON DELETE RESTRICT,relation VARCHAR(16) NOT NULL DEFAULT 'consume',order_id INTEGER,operation_id INTEGER,qty_input NUMERIC(18,6) NOT NULL,qty_output NUMERIC(18,6),uom_code VARCHAR(16) NOT NULL DEFAULT 'pcs',created_by INTEGER,created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
   CONSTRAINT erp_412_genealogy_self_ck CHECK(input_lot_id<>output_lot_id),CONSTRAINT erp_412_genealogy_qty_ck CHECK(qty_input>0 AND (qty_output IS NULL OR qty_output>0)),CONSTRAINT erp_412_genealogy_relation_ck CHECK(relation IN('consume','split','merge','rework','substitute')),CONSTRAINT erp_412_genealogy_uq UNIQUE(input_lot_id,output_lot_id,order_id,operation_id))");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_genealogy_input_idx ON erp_412_genealogy(input_lot_id)");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_genealogy_output_idx ON erp_412_genealogy(output_lot_id)");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_genealogy_order_idx ON erp_412_genealogy(order_id)");

  $this->run_query("CREATE TABLE IF NOT EXISTS erp_412_quality_links(
   id BIGSERIAL PRIMARY KEY,lot_id BIGINT NOT NULL REFERENCES erp_412_lots(id) ON DELETE RESTRICT,inspection_id INTEGER NOT NULL,created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT erp_412_quality_link_uq UNIQUE(lot_id,inspection_id))");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_quality_inspection_idx ON erp_412_quality_links(inspection_id)");

  $this->run_query("CREATE TABLE IF NOT EXISTS erp_412_events(
   id BIGSERIAL PRIMARY KEY,lot_id BIGINT NOT NULL REFERENCES erp_412_lots(id) ON DELETE RESTRICT,event_type VARCHAR(24) NOT NULL,before_status VARCHAR(16),after_status VARCHAR(16),reason TEXT,reference_type VARCHAR(32),reference_id INTEGER,created_by INTEGER,created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $this->run_query("CREATE INDEX IF NOT EXISTS erp_412_events_lot_idx ON erp_412_events(lot_id,created_at DESC)");
  $this->run_query("CREATE OR REPLACE FUNCTION erp_412_events_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'erp_412_events is append-only'; END $$");
  $this->run_query("DROP TRIGGER IF EXISTS erp_412_events_immutable_trg ON erp_412_events");
  $this->run_query("CREATE TRIGGER erp_412_events_immutable_trg BEFORE UPDATE OR DELETE ON erp_412_events FOR EACH ROW EXECUTE FUNCTION erp_412_events_immutable()");
 }
 public function down(){
  $this->run_query("DROP TABLE IF EXISTS erp_412_quality_links CASCADE");$this->run_query("DROP TABLE IF EXISTS erp_412_genealogy CASCADE");$this->run_query("DROP TABLE IF EXISTS erp_412_allocations CASCADE");$this->run_query("DROP TABLE IF EXISTS erp_412_events CASCADE");$this->run_query("DROP TABLE IF EXISTS erp_412_lots CASCADE");$this->run_query("DROP FUNCTION IF EXISTS erp_412_events_immutable()");$this->run_query("DROP SEQUENCE IF EXISTS erp_412_lot_number_seq");
 }
}
