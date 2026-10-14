-- Инициализация баз PromERP (выполняется один раз при создании тома pgdata).
--
-- Основная база (POSTGRES_DB, по умолчанию promerp) — группа БД `default`.
-- Вторая база promerp_mdm — группа БД `mdm` (модули мастер-данных erp230/erp229);
-- имя должно совпадать с DB_MDM_NAME в .env.

-- 1. Расширения основной базы
CREATE EXTENSION IF NOT EXISTS ltree;        -- иерархии (реестр erp227, MDM)
CREATE EXTENSION IF NOT EXISTS btree_gist;   -- EXCLUDE-ограничения по диапазонам
CREATE EXTENSION IF NOT EXISTS pgcrypto;     -- gen_random_uuid / digest
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";  -- uuid_generate_v4
CREATE EXTENSION IF NOT EXISTS vector;       -- pgvector: эмбеддинги (erp222)

-- 2. Вторая база для мастер-данных
SELECT 'CREATE DATABASE promerp_mdm OWNER ' || quote_ident(current_user)
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'promerp_mdm')
\gexec

-- 3. Расширения второй базы
\connect promerp_mdm

CREATE EXTENSION IF NOT EXISTS ltree;
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS vector;
