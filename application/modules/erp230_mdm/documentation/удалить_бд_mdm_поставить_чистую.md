# Переключаемся на пользователя postgres
sudo -i -u postgres

# Принудительно разрываем активные соединения с базой mdm (если они есть)
psql -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'mdm' AND pid <> pg_backend_pid();"

# Удаляем базу данных
dropdb mdm --if-exists

# Создаем базу заново
createdb mdm

exit

sudo -u postgres psql -d mdm
CREATE EXTENSION IF NOT EXISTS vector;



# Предоставить пользователю mdm_user полные права на одноименную базу данных mdm:
\q

sudo -u postgres psql -d mdm -c "GRANT ALL PRIVILEGES ON DATABASE mdm TO mdm_user; GRANT ALL ON SCHEMA public TO mdm_user; GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO mdm_user; GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO mdm_user; GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO mdm_user;"


# права для пользователя mdm_god
sudo -u postgres psql -d mdm -c "GRANT ALL PRIVILEGES ON DATABASE mdm TO mdm_god; GRANT ALL ON SCHEMA public TO mdm_god; GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO mdm_god; GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO mdm_god; GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO mdm_god;"

# обход RLS для пользователя mdm_god
sudo -u postgres psql -d mdm -c "ALTER ROLE "mdm_god" BYPASSRLS;"



psql -d mdm -f init_db.sql



--------------------------


Для создания новой базы данных в PostgreSQL на Ubuntu выполните следующие шаги.

### 1. Подключение к PostgreSQL
По умолчанию в Ubuntu PostgreSQL использует системного пользователя `postgres`. Сначала нужно переключиться на него и войти в консоль управления `psql`:

```bash
sudo -u postgres psql
```

---

### 2. Создание базы данных
В консоли `psql` (где приглашение выглядит как `postgres=#`) введите следующую команду:

```sql
CREATE DATABASE mdm;
```
*Не забудьте точку с запятой в конце.*

---

### 3. Настройка прав (Рекомендуется)
Обычно для новой БД создают отдельного пользователя, чтобы не работать из-под суперпользователя.

1.  **Создать пользователя** (замените `my_password` на свой пароль):
    ```sql
    CREATE USER mdm_user WITH PASSWORD 'my_password';
    ```
2.  **Назначить владельца базы**:
    ```sql
    ALTER DATABASE mdm OWNER TO mdm_user;
    ```
3.  **Дать права** (для новых версий Postgres 15+):
    ```sql
    GRANT ALL PRIVILEGES ON DATABASE mdm TO mdm_user;
    ```

---

### 4. Проверка
Чтобы убедиться, что база создана, введите:
```sql
\l
```
Вы увидите список всех баз данных. Чтобы выйти из консоли, введите:
```sql
\q
```

---

### Короткий способ (через терминал)
Если вы не хотите заходить в консоль `psql`, можно создать базу одной командой в обычном терминале Ubuntu:

```bash
sudo -u postgres createdb mdm
```

<details>
<summary><b>Как подключиться к новой базе?</b></summary>

Чтобы зайти именно в созданную базу под созданным пользователем:
```bash
psql -h localhost -U mdm_user -d mdm
```
*(Если возникнет ошибка прав доступа `peer authentication`, проверьте файл `/etc/postgresql/XX/main/pg_hba.conf` и убедитесь, что для локальных подключений разрешен метод `md5` или `scram-sha-256`, а не только `peer`)*.
</details>