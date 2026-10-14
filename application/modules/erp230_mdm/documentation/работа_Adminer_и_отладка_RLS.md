### Дать привелегии пользователю mdm_god в обход РЛС: 

cd /tmp && sudo -u postgres psql -d mdm -c 'ALTER ROLE "mdm_god" BYPASSRLS;'


### Откатить привелегии пользователю mdm_god в обход РЛС:  BYPASSRLS нужно откатить:

cd /tmp && sudo -u postgres psql -d mdm -c 'ALTER ROLE "mdm_god" NOBYPASSRLS;'


---------------------

# Создание пользователя `mdm_god` в PostgreSQL с полными правами и обходом RLS


sudo -u postgres psql -d mdm

```sql
-- Выполнять от имени суперпользователя (postgres)
CREATE ROLE mdm_god WITH LOGIN PASSWORD 'ВашНадёжныйПароль' BYPASSRLS;
GRANT CONNECT ON DATABASE mdm TO mdm_god;

\c mdm

GRANT ALL PRIVILEGES ON SCHEMA public TO mdm_god;
GRANT ALL PRIVILEGES ON ALL TABLES     IN SCHEMA public TO mdm_god;
GRANT ALL PRIVILEGES ON ALL SEQUENCES  IN SCHEMA public TO mdm_god;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS  IN SCHEMA public TO mdm_god;

ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES    TO mdm_god;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO mdm_god;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO mdm_god;
```

---

### На что обратить внимание
- **Пароль** — замените `'ВашНадёжныйПароль'` на реальный надёжный пароль.
- **Схема** — если данные лежат не только в `public`, повторите блок `GRANT`/`ALTER DEFAULT PRIVILEGES` для каждой схемы.
- **`BYPASSRLS`** может назначать только суперпользователь или роль, у которой самой есть этот атрибут.


---

### Отключить обход РЛС
/tmp# cd /tmp && sudo -u postgres psql -d mdm -c 'ALTER ROLE "mdm_god" NOBYPASSRLS;'