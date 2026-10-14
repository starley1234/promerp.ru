#!/usr/bin/env bash
#
# Восстановление среды разработки PromERP «с нуля».
#
# Скрипт заново разворачивает всё, что не лежит в git:
#   * PHP 8.1+ с расширениями pgsql/pdo_pgsql/mbstring
#   * PostgreSQL 16 (переносимые бинари из PyPI-пакета pgserver)
#   * application/config/* из application/config.dist/*
#   * .env, каталоги логов/кэша/сессий, vendor/autoload.php
#   * БД promerp, миграции, учётная запись администратора
#
# Запуск:
#   bash bin/restore-env.sh              # всё сразу
#   bash bin/restore-env.sh --pg-only    # только база
#   bash bin/restore-env.sh --php-only   # только PHP
#   bash bin/restore-env.sh --app-only   # только конфиги/миграции (PHP и PG уже есть)
#   bash bin/restore-env.sh --serve      # в конце поднять dev-сервер
#
# Переменные окружения (необязательно):
#   PHP_PREFIX=/home/user/.local/promerp-env/php   куда ставим PHP
#   PG_PREFIX=/home/user/.local/promerp-env/pg     куда кладём PostgreSQL
#   PGPORT=5432 DB_NAME=promerp DB_USER=promerp
#   PHP_VERSION=8.2.33               версия PHP для сборки из исходников
#   LIBPHP_PACKAGE=@libphp/amazon-linux-2023-v84   npm-пакет с готовым PHP 8.4
#   ADMIN_EMAIL / ADMIN_PASSWORD     учётка администратора (по умолчанию admin@promerp.local / admin12345)
#
# Порядок добычи PHP: PHP_BIN → php в PATH → сборки проекта → apt-get → npm-пакет
# (готовый PHP 8.4 без компиляции) → сборка из исходников. В средах без apt и без
# доступа к php.net работает npm-пакет: PHP 8.4.14 с pgsql, pdo_pgsql, mbstring,
# intl и opcache ставится одной загрузкой, без компилятора.
#
set -u

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

PHP_PREFIX="${PHP_PREFIX:-$HOME/tools/php84}"
PG_PREFIX="${PG_PREFIX:-$HOME/tools/pg}"
PGDATA="${PGDATA:-$PG_PREFIX/data}"
PGPORT="${PGPORT:-5432}"
PG_SOCKET_DIR="${PG_SOCKET_DIR:-$PG_PREFIX}"
DB_NAME="${DB_NAME:-promerp}"
DB_USER="${DB_USER:-promerp}"
DB_PASS="${DB_PASS:-promerp}"
PHP_VERSION="${PHP_VERSION:-8.2.33}"
# Явные переменные окружения имеют приоритет над .env: значения запоминаем до
# того, как применим умолчания, и возвращаем их после чтения .env.
ADMIN_EMAIL_CLI="${ADMIN_EMAIL:-}"
ADMIN_PASSWORD_CLI="${ADMIN_PASSWORD:-}"
ADMIN_USERNAME_CLI="${ADMIN_USERNAME:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@promerp.local}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin12345}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
LIBPHP_PACKAGE="${LIBPHP_PACKAGE:-@libphp/amazon-linux-2023-v84}"
PGSERVER_VERSION="${PGSERVER_VERSION:-0.1.4}"

WORK_DIR="${WORK_DIR:-$HOME/.promerp-restore}"
PHP_BIN="${PHP_BIN:-}"

MODE="all"
DO_SERVE=0
for arg in "$@"; do
    case "$arg" in
        --pg-only)  MODE="pg" ;;
        --php-only) MODE="php" ;;
        --app-only) MODE="app" ;;
        --serve)    DO_SERVE=1 ;;
        -h|--help)  sed -n '2,25p' "$0"; exit 0 ;;
    esac
done

log()  { printf '\n\033[1;36m==>\033[0m %s\n' "$*"; }
ok()   { printf '    \033[1;32m✔\033[0m %s\n' "$*"; }
warn() { printf '    \033[1;33m!\033[0m %s\n' "$*"; }
die()  { printf '\n\033[1;31m✘ %s\033[0m\n' "$*" >&2; exit 1; }

# -----------------------------------------------------------------------------
# 0. Инструменты
# -----------------------------------------------------------------------------
PYTHON="$(command -v python3 || command -v python)"
[ -n "$PYTHON" ] || die "Нужен python3 (для получения переносимого PostgreSQL)"

have_php() {
    local bin="$1"
    [ -n "$bin" ] && [ -x "$bin" ] || return 1
    "$bin" -r 'exit(extension_loaded("pgsql") && extension_loaded("pdo_pgsql") && extension_loaded("mbstring") ? 0 : 1);' 2>/dev/null
}

# -----------------------------------------------------------------------------
# 1. PHP
# -----------------------------------------------------------------------------
find_php() {
    if [ -n "$PHP_BIN" ] && have_php "$PHP_BIN"; then echo "$PHP_BIN"; return; fi
    for cand in php php8.3 php8.2 php8.1 /usr/bin/php /usr/local/bin/php "$PHP_PREFIX/bin/php"; do
        if command -v "$cand" >/dev/null 2>&1 && have_php "$(command -v "$cand")"; then
            echo "$(command -v "$cand")"; return
        fi
    done
    if have_php "$PHP_PREFIX/bin/php"; then echo "$PHP_PREFIX/bin/php"; return; fi
}

install_php_apt() {
    log "Пробую поставить PHP через apt-get"
    command -v apt-get >/dev/null 2>&1 || return 1
    mkdir -p /var/lib/apt/lists/partial 2>/dev/null
    apt-get update -qq >/dev/null 2>&1 || { warn "apt-get update не удался"; return 1; }
    apt-get install -y -qq php-cli php-pgsql php-mbstring php-xml php-bcmath >/dev/null 2>&1 || return 1
    command -v php >/dev/null 2>&1 && have_php "$(command -v php)"
}

# Готовый PHP 8.4 из npm-пакета @libphp/amazon-linux-2023-v84.
#
# Зачем: apt/deb-репозитории доступны не везде, а сборка из исходников требует
# autoconf/bison и много времени. Пакет приносит собранный под Linux PHP 8.4.14
# вместе с расширениями (pgsql, pdo_pgsql, mbstring, intl, opcache) и наборами
# разделяемых библиотек; остаётся разложить файлы по $PHP_PREFIX и написать
# обёртку, потому что собственный php.ini пакета указывает на /var/task/php.
install_php_npm() {
    log "Ставлю готовый PHP из npm-пакета $LIBPHP_PACKAGE (без компиляции)"

    local tmp="$WORK_DIR/libphp"
    local tarball="$tmp/libphp.tgz"
    mkdir -p "$tmp"

    # Точный адрес архива берём из метаданных пакета: имя файла зависит от версии
    "$PYTHON" - "$LIBPHP_PACKAGE" "$tarball" <<'PY' || return 1
import json, sys, urllib.request

package, dest = sys.argv[1], sys.argv[2]
name = package.replace('/', '%2f') if package.startswith('@') else package

with urllib.request.urlopen('https://registry.npmjs.org/%s' % name, timeout=30) as response:
    data = json.load(response)

version = data['dist-tags']['latest']
info = data['versions'][version]['dist']
print('    пакет %s@%s' % (package, version))

request = urllib.request.Request(info['tarball'], headers={'User-Agent': 'promerp-restore-env'})
with urllib.request.urlopen(request, timeout=300) as response, open(dest, 'wb') as handle:
    while True:
        chunk = response.read(1 << 20)
        if not chunk:
            break
        handle.write(chunk)
PY
    [ -s "$tarball" ] || { warn "npm-пакет не скачался (нужен доступ к registry.npmjs.org)"; return 1; }

    rm -rf "$tmp/package"
    tar xzf "$tarball" -C "$tmp" || return 1

    local src="$tmp/package/native"

    [ -x "$src/php/php" ] || { warn "в npm-пакете нет native/php/php"; return 1; }

    mkdir -p "$PHP_PREFIX/modules" "$PHP_PREFIX/lib"

    cp -f "$src/php/php" "$PHP_PREFIX/php.bin"
    # php-cgi/php-fpm проекту не нужны: сайт ходит через CLI и bin/serve
    cp -Rf "$src/php/modules/." "$PHP_PREFIX/modules/" 2>/dev/null || true
    cp -Rf "$src/lib/." "$PHP_PREFIX/lib/" 2>/dev/null || true

    # php.ini пакета ссылается на каталог сборки /var/task/php/modules и тянет
    # десятки расширений без проверки — собираем свой, только из существующих .so
    {
        echo "; Сгенерировано bin/restore-env.sh из npm-пакета $LIBPHP_PACKAGE"
        echo "; Расширения перечислены только те, что реально есть в modules/"
        echo "extension_dir = \"$PHP_PREFIX/modules\""
        [ -f "$PHP_PREFIX/modules/opcache.so" ] && echo "zend_extension = opcache"
        for ext in bcmath ctype curl dom exif fileinfo iconv intl mbstring pdo pdo_pgsql pgsql phar posix simplexml sockets sodium tokenizer xml xmlreader xmlwriter zip; do
            [ -f "$PHP_PREFIX/modules/$ext.so" ] && echo "extension = $ext"
        done
        echo "display_errors = 1"
        echo "error_reporting = E_ALL & ~E_DEPRECATED"
        echo "memory_limit = 512M"
        echo "date.timezone = Europe/Moscow"
        echo "variables_order = \"EGPCS\""
        echo "sys_temp_dir = \"/tmp\""
        echo "session.save_path = \"/tmp\""
    } > "$PHP_PREFIX/php.ini"

    # Обёртка вместо бинарника: без своего php.ini и LD_LIBRARY_PATH он не стартует
    cat > "$PHP_PREFIX/php" <<'SH'
#!/bin/sh
# Обёртка портативного PHP: подставляет разделяемые библиотеки сборки и свой php.ini.
PHP_HOME="$(cd "$(dirname "$0")" && pwd)"
export LD_LIBRARY_PATH="$PHP_HOME/lib${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
exec "$PHP_HOME/php.bin" -c "$PHP_HOME/php.ini" "$@"
SH
    chmod 755 "$PHP_PREFIX/php" "$PHP_PREFIX/php.bin"

    if have_php "$PHP_PREFIX/php"; then
        return 0
    fi

    warn "PHP из npm-пакета не проходит проверку расширений"
    return 1
}

install_php_source() {
    log "Сборка PHP $PHP_VERSION из исходников в $PHP_PREFIX"
    mkdir -p "$WORK_DIR" "$PHP_PREFIX/bin"
    cd "$WORK_DIR"

    local tarball="$WORK_DIR/php-$PHP_VERSION.tar.gz"
    if [ ! -s "$tarball" ]; then
        # Основной источник — официальный tarball: в нём уже есть ./configure.
        warn "Качаю https://www.php.net/distributions/php-$PHP_VERSION.tar.gz"
        curl -fsSL -o "$tarball" "https://www.php.net/distributions/php-$PHP_VERSION.tar.gz" || {
            warn "php.net недоступен, беру архив GitHub (понадобится autoconf для ./buildconf)"
            curl -fsSL -o "$tarball" \
              "https://codeload.github.com/php/php-src/tar.gz/refs/tags/php-$PHP_VERSION" || return 1
        }
    fi

    [ -d "php-src-php-$PHP_VERSION" ] || tar xzf "$tarball"
    cd php-src-php-$PHP_VERSION* 2>/dev/null || cd php-src-* 2>/dev/null || die "Не распаковался исходник PHP"

    if [ ! -f ./configure ]; then
        command -v autoconf >/dev/null 2>&1 || die "Нет ./configure и нет autoconf: установите autoconf (apt-get install autoconf) либо скачайте официальный tarball с php.net"
        ./buildconf --force >/dev/null 2>&1 || die "./buildconf не выполнился"
    fi

    export PATH="$PG_PREFIX/pginstall/bin:$PATH"
    export LD_LIBRARY_PATH="$PG_PREFIX/pginstall/lib:${LD_LIBRARY_PATH:-}"

    ./configure --prefix="$PHP_PREFIX" \
        --with-pgsql --with-pdo-pgsql \
        --enable-mbstring --enable-sockets --enable-bcmath \
        --enable-pcntl --enable-posix --enable-fileinfo --enable-phar \
        --enable-ctype --enable-filter --enable-session \
        --without-sqlite3 --without-pdo-sqlite \
        --disable-cgi --without-pear > "$WORK_DIR/php-configure.log" 2>&1 \
        || { warn "configure провалился, смотри $WORK_DIR/php-configure.log"; tail -20 "$WORK_DIR/php-configure.log"; return 1; }

    make -j"$(nproc 2>/dev/null || echo 2)" > "$WORK_DIR/php-make.log" 2>&1 \
        || { warn "make провалился, смотри $WORK_DIR/php-make.log"; tail -20 "$WORK_DIR/php-make.log"; return 1; }

    make install > "$WORK_DIR/php-install.log" 2>&1 || return 1
    have_php "$PHP_PREFIX/bin/php"
}

ensure_php() {
    PHP_BIN="$(find_php)"
    if [ -n "$PHP_BIN" ]; then ok "PHP найден: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"; return 0; fi

    warn "Подходящий PHP не найден (нужны pgsql, pdo_pgsql, mbstring)"
    if install_php_apt; then PHP_BIN="$(command -v php)"; ok "PHP установлен из apt: $PHP_BIN"; return 0; fi
    if install_php_npm; then PHP_BIN="$PHP_PREFIX/php"; ok "PHP из npm-пакета: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"; return 0; fi
    if install_php_source; then PHP_BIN="$PHP_PREFIX/bin/php"; ok "PHP собран: $PHP_BIN"; return 0; fi

    die "Не удалось получить PHP с pgsql. Варианты: apt-get install php-cli php-pgsql php-mbstring; npm-пакет $LIBPHP_PACKAGE; docker compose up -d"
}

# -----------------------------------------------------------------------------
# 2. PostgreSQL (переносимые бинари из PyPI-пакета pgserver)
# -----------------------------------------------------------------------------
ensure_postgres() {
    local pgbin="$PG_PREFIX/pginstall/bin"
    export LD_LIBRARY_PATH="$PG_PREFIX/pginstall/lib:${LD_LIBRARY_PATH:-}"

    if [ -x "$pgbin/pg_isready" ] && "$pgbin/pg_isready" -h 127.0.0.1 -p "$PGPORT" >/dev/null 2>&1; then
        ok "PostgreSQL уже запущен на порту $PGPORT"
        return 0
    fi

    if [ ! -x "$pgbin/initdb" ]; then
        log "Скачиваю переносимый PostgreSQL (PyPI: pgserver $PGSERVER_VERSION)"
        mkdir -p "$WORK_DIR"
        ( cd "$WORK_DIR" && "$PYTHON" -m pip download --no-deps -q pgserver=="$PGSERVER_VERSION" ) \
            || die "Не скачался pgserver (нужен доступ к PyPI)"
        local wheel
        wheel="$(ls "$WORK_DIR"/pgserver-*.whl 2>/dev/null | head -1)"
        [ -n "$wheel" ] || die "Не найден wheel pgserver"
        mkdir -p "$PG_PREFIX"
        "$PYTHON" - "$wheel" "$PG_PREFIX" <<'PY'
import sys, zipfile, os, shutil, glob
wheel, dest = sys.argv[1], sys.argv[2]
tmp = os.path.join(dest, '_wheel')
shutil.rmtree(tmp, ignore_errors=True)
os.makedirs(tmp, exist_ok=True)
with zipfile.ZipFile(wheel) as z:
    z.extractall(tmp)
pginstall = glob.glob(os.path.join(tmp, 'pgserver', 'pginstall'))
if not pginstall:
    pginstall = glob.glob(os.path.join(tmp, '**', 'pginstall'), recursive=True)
if not pginstall:
    sys.exit('в wheel нет каталога pginstall')
target = os.path.join(dest, 'pginstall')
shutil.rmtree(target, ignore_errors=True)
shutil.copytree(pginstall[0], target)
# разделяемые библиотеки из pgserver.libs
for lib in glob.glob(os.path.join(tmp, '*.libs', '*')):
    try:
        shutil.copy(lib, os.path.join(target, 'lib'))
    except Exception:
        pass
# zipfile не сохраняет бит выполнения — выставляем вручную
for root, dirs, files in os.walk(target):
    for name in files:
        path = os.path.join(root, name)
        mode = os.stat(path).st_mode
        if os.path.basename(root) == 'bin':
            os.chmod(path, mode | 0o755)
        elif name.endswith('.so') or '.so.' in name:
            os.chmod(path, mode | 0o555)
shutil.rmtree(tmp, ignore_errors=True)
print('распаковано в ' + target)
PY
        ok "PostgreSQL распакован в $PG_PREFIX/pginstall"
    fi

    if [ ! -f "$PGDATA/PG_VERSION" ]; then
        log "initdb в $PGDATA"
        mkdir -p "$PGDATA"
        "$pgbin/initdb" -D "$PGDATA" -U "$DB_USER" --auth=trust --encoding=UTF8 --locale=C \
            > "$WORK_DIR/initdb.log" 2>&1 || { tail -20 "$WORK_DIR/initdb.log"; die "initdb не выполнился"; }
        ok "Кластер создан"
    fi

    log "Запуск PostgreSQL на 127.0.0.1:$PGPORT"
    mkdir -p "$PG_SOCKET_DIR"
    "$pgbin/pg_ctl" -D "$PGDATA" -l "$PG_PREFIX/pg.log" \
        -o "-p $PGPORT -k $PG_SOCKET_DIR -c listen_addresses='127.0.0.1'" -w start \
        > "$WORK_DIR/pg-start.log" 2>&1 || { tail -20 "$WORK_DIR/pg-start.log"; die "Не удалось запустить PostgreSQL"; }
    ok "PostgreSQL запущен"

    # Две базы: рабочая и MDM (её ждут dev:doctor и группа mdm в config/database.php)
    local db
    for db in "$DB_NAME" "${DB_NAME}_mdm"; do
        if ! "$pgbin/psql" -h 127.0.0.1 -p "$PGPORT" -U "$DB_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$db'" | grep -q 1; then
            "$pgbin/createdb" -h 127.0.0.1 -p "$PGPORT" -U "$DB_USER" "$db" && ok "БД $db создана"
        else
            ok "БД $db уже есть"
        fi
    done

    ensure_extensions "$pgbin"
}

# Расширения PostgreSQL, нужные модулям: ltree и btree_gist — реестру erp227,
# uuid-ossp и pgcrypto — идентификаторам и журналу, vector — поиску по описаниям.
# В переносимой сборке pgserver их нет: скрипт собирает contrib-модули из
# исходников (bin/pg-contrib.sh), а для pgcrypto нужны заголовки и библиотека
# OpenSSL — их ищем сами, чтобы не требовать переменных от человека.
ensure_extensions() {
    local pgbin="$1"
    local db missing
    local -a extensions=(ltree btree_gist "uuid-ossp" pgcrypto vector)

    for db in "$DB_NAME" "${DB_NAME}_mdm"; do
        missing=""

        for ext in "${extensions[@]}"; do
            "$pgbin/psql" -h 127.0.0.1 -p "$PGPORT" -U "$DB_USER" -d "$db" -q \
                -c "CREATE EXTENSION IF NOT EXISTS \"$ext\";" >/dev/null 2>&1 || missing="$missing $ext"
        done

        [ -z "$missing" ] && { ok "Расширения подключены: $db (${extensions[*]})"; continue; }

        warn "В базе $db не хватает расширений:$missing — собираю contrib из исходников"

        if [ -z "${OPENSSL_INCLUDE:-}" ]; then
            local candidate
            for candidate in "$WORK_DIR/openssl-src/include" "$WORK_DIR/openssl-src" /usr/include; do
                [ -f "$candidate/openssl/opensslv.h" ] && { export OPENSSL_INCLUDE="$candidate"; break; }
            done
        fi

        if [ -z "${OPENSSL_LIB:-}" ]; then
            local candidate
            for candidate in "$PHP_PREFIX/lib" "$PG_PREFIX/pginstall/lib" "$WORK_DIR/openssl-src" /usr/lib/x86_64-linux-gnu; do
                ls "$candidate"/libcrypto.so* >/dev/null 2>&1 && { export OPENSSL_LIB="$candidate"; break; }
            done
        fi

        PG_PREFIX="$PG_PREFIX" WORK_DIR="$WORK_DIR" \
            OPENSSL_INCLUDE="${OPENSSL_INCLUDE:-}" OPENSSL_LIB="${OPENSSL_LIB:-}" \
            bash bin/pg-contrib.sh ${missing} 2>&1 | tail -15

        missing=""

        for ext in "${extensions[@]}"; do
            "$pgbin/psql" -h 127.0.0.1 -p "$PGPORT" -U "$DB_USER" -d "$db" -q \
                -c "CREATE EXTENSION IF NOT EXISTS \"$ext\";" >/dev/null 2>&1 || missing="$missing $ext"
        done

        [ -z "$missing" ] && ok "Расширения подключены: $db" \
            || warn "В базе $db всё ещё нет расширений:$missing (проверьте gcc и исходники PostgreSQL)"
    done
}

# -----------------------------------------------------------------------------
# 3. Приложение: конфиги, .env, каталоги, vendor
# -----------------------------------------------------------------------------
ensure_app() {
    log "Готовлю конфигурацию приложения"

    mkdir -p application/config application/logs application/cache application/sessions public_html/uploads

    local f
    for f in application/config.dist/*.php; do
        local name; name="$(basename "$f")"
        if [ ! -f "application/config/$name" ]; then
            cp "$f" "application/config/$name"
        fi
    done
    ok "Конфиги из config.dist скопированы (существующие не тронуты)"

    if [ ! -f .env ]; then
        if [ -f .env.example ]; then
            cp .env.example .env
        else
            cat > .env <<EOF
DB_HOST=127.0.0.1
DB_PORT=$PGPORT
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS

ADMIN_EMAIL=$ADMIN_EMAIL
ADMIN_PASSWORD=$ADMIN_PASSWORD
ADMIN_USERNAME=$ADMIN_USERNAME
EOF
        fi
        # правим хост/порт под локальный запуск (в .env.example — docker-значения)
        sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|; s|^DB_PORT=.*|DB_PORT=$PGPORT|; s|^DB_NAME=.*|DB_NAME=$DB_NAME|; s|^DB_USER=.*|DB_USER=$DB_USER|" .env
        ok ".env создан (DB_HOST=127.0.0.1)"
    else
        ok ".env уже есть"
    fi

    # Доводим и уже существующий .env до рабочего состояния: setup генерирует
    # пустые COOKIE_SALT/AUTH_HASH_KEY, не меняя заданные пользователем значения.
    bash bin/setup.sh --quiet

    if [ ! -f vendor/autoload.php ]; then
        mkdir -p vendor
        cat > vendor/autoload.php <<'PHP'
<?php
// Минимальный PSR-4 автозагрузчик: composer/packagist недоступны в этой среде.
// Если vendor/ будет установлен через composer, этот файл заменится настоящим.
spl_autoload_register(function ($class) {
    $prefixes = array(
        'Tests\\\\' => __DIR__.'/../tests/',
    );
    foreach ($prefixes as $prefix => $base) {
        if (strpos($class, $prefix) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = $base.str_replace('\\\\', '/', $relative).'.php';
            if (is_file($file)) { require $file; return; }
        }
    }
});
PHP
        ok "vendor/autoload.php создан (fallback без composer)"
    else
        ok "vendor/autoload.php уже есть"
    fi

    set -a; . ./.env; set +a

    # Приоритет: явная переменная окружения → .env → умолчание скрипта.
    # Иначе пароль, который печатает скрипт, расходится с паролем, который
    # реально выставлен в базе (на этом уже спотыкались: .env хранил admin123,
    # а вход ожидался с другим паролем).
    [ -n "$ADMIN_EMAIL_CLI" ] && ADMIN_EMAIL="$ADMIN_EMAIL_CLI"
    [ -n "$ADMIN_PASSWORD_CLI" ] && ADMIN_PASSWORD="$ADMIN_PASSWORD_CLI"
    [ -n "$ADMIN_USERNAME_CLI" ] && ADMIN_USERNAME="$ADMIN_USERNAME_CLI"

    if grep -q '^ADMIN_PASSWORD=' .env && [ -z "$ADMIN_PASSWORD_CLI" ]; then
        ok "Учётная запись администратора берётся из .env ($ADMIN_EMAIL)"
    fi

    # Сначала поднимаем схему и пользователя, и только затем проверяем страницы.
    # Иначе doctor/smoke закономерно сообщают о десятках отсутствующих таблиц и
    # создают впечатление нерабочего окружения при успешной установке.
    log "Миграции (db:migrate)"
    "$PHP_BIN" public_html/index.php --task=db:migrate 2>&1 | tail -25

    log "Учётная запись администратора"
    "$PHP_BIN" public_html/index.php --task=auth:user \
        --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --username="$ADMIN_USERNAME" --admin=1 2>&1 | tail -5

    log "Проверка окружения (dev:doctor)"
    "$PHP_BIN" public_html/index.php --task=dev:doctor 2>&1 | tail -20

    log "Проверка кода и страниц"
    "$PHP_BIN" public_html/index.php --task=dev:audit 2>&1 | tail -5 || true
    "$PHP_BIN" public_html/index.php --task=dev:smoke 2>&1 | tail -5 || true
}

# -----------------------------------------------------------------------------
# Запуск
# -----------------------------------------------------------------------------
case "$MODE" in
    php) ensure_php ;;
    pg)  ensure_postgres ;;
    app) ensure_php; ensure_app ;;
    all) ensure_postgres; ensure_php; ensure_app ;;
esac

if [ "$DO_SERVE" = "1" ]; then
    log "Dev-сервер на http://0.0.0.0:${PORT:-8080}"
    PORT="${PORT:-8080}" exec bash bin/serve
fi

log "Готово"
[ -n "$PHP_BIN" ] && echo "    PHP: $PHP_BIN"
echo "    PG : 127.0.0.1:$PGPORT (база $DB_NAME, пользователь $DB_USER)"
echo "    Вход: $ADMIN_EMAIL / $ADMIN_PASSWORD"
echo
echo "    Дальше: make check (линт + аудит + тесты + маршруты + окружение)"
