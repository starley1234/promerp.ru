#!/usr/bin/env bash
#
# Сборка contrib-расширений PostgreSQL для переносимой сборки из PyPI (pgserver).
#
# В официальном Docker-образе и в пакете postgresql-contrib расширения уже есть,
# а переносимая сборка pgserver содержит только plpgsql и pgvector. PromERP
# использует ltree, btree_gist, uuid-ossp, pgcrypto и pg_trgm (ускорение поиска), поэтому на машинах без
# Docker/apt их приходится собирать из исходников — этим и занимается скрипт.
#
# Требуется: gcc и заголовки сервера PostgreSQL (входят в состав pgserver).
#
# Запуск:
#   bash bin/pg-contrib.sh                 # все расширения
#   bash bin/pg-contrib.sh ltree btree_gist
#
# Переменные:
#   PG_PREFIX=/home/user/pg          префикс переносимого PostgreSQL
#   PG_SOURCE_TAG=REL_16_2           тег исходников (по умолчанию из версии сервера)
#   WORK_DIR=~/.promerp-restore      каталог для загрузок и сборки
#   OPENSSL_INCLUDE=                 каталог с openssl/*.h (для pgcrypto)
#   OPENSSL_LIB=                     каталог с libcrypto.so (для pgcrypto)
#
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PG_PREFIX="${PG_PREFIX:-$HOME/.local/promerp-env/pg}"
PGSERVER_VERSION="${PGSERVER_VERSION:-0.1.4}"
WORK_DIR="${WORK_DIR:-$HOME/.promerp-restore}"
OPENSSL_INCLUDE="${OPENSSL_INCLUDE:-}"
OPENSSL_LIB="${OPENSSL_LIB:-}"
PHP_PREFIX="${PHP_PREFIX:-$HOME/.local/promerp-env/php}"

# OpenSSL нужен только pgcrypto. Если переменные не заданы, ищем заголовки и
# библиотеку сами: чаще всего рядом лежит портативная сборка PHP (в ней есть
# libcrypto.so.3), а заголовки — в рабочем каталоге bin/restore-env.sh.
if [ -z "$OPENSSL_INCLUDE" ]; then
    for candidate in "$WORK_DIR/openssl-src/include" "$WORK_DIR/openssl-src" /usr/include; do
        [ -f "$candidate/openssl/opensslv.h" ] && { OPENSSL_INCLUDE="$candidate"; break; }
    done
fi

if [ -z "$OPENSSL_LIB" ]; then
    for candidate in "$PHP_PREFIX/lib" "$PG_PREFIX/pginstall/lib" "$WORK_DIR/openssl-src" /usr/lib/x86_64-linux-gnu; do
        ls "$candidate"/libcrypto.so* >/dev/null 2>&1 && { OPENSSL_LIB="$candidate"; break; }
    done
fi

PGINSTALL="$PG_PREFIX/pginstall"
PG_BIN="$PGINSTALL/bin"
PG_LIB="$PGINSTALL/lib"
PG_MODULE_DIR="$PGINSTALL/lib/postgresql"   # $libdir (pkglibdir) — уточняется через pg_config
PG_SHARE="$PGINSTALL/share/postgresql/extension"

log()  { printf '\n\033[1;36m==>\033[0m %s\n' "$*"; }
ok()   { printf '    \033[1;32m✔\033[0m %s\n' "$*"; }
warn() { printf '    \033[1;33m!\033[0m %s\n' "$*"; }
die()  { printf '\n\033[1;31m✘ %s\033[0m\n' "$*" >&2; exit 1; }

EXTENSIONS=("$@")
if [ ${#EXTENSIONS[@]} -eq 0 ]; then
    EXTENSIONS=(ltree btree_gist uuid-ossp pgcrypto pg_trgm)
fi

[ -x "$PG_BIN/pg_config" ] || die "Не найден $PG_BIN/pg_config — сначала: bash bin/restore-env.sh --pg-only"
command -v gcc >/dev/null 2>&1 || die "Нужен gcc (apt-get install gcc)"

PG_VERSION="$("$PG_BIN/pg_config" --version | awk '{print $2}')"
PG_MAJOR="${PG_VERSION%%.*}"
: "${PG_SOURCE_TAG:=REL_${PG_VERSION//./_}}"

PG_INCLUDE="$("$PG_BIN/pg_config" --includedir-server)"
[ -d "$PG_INCLUDE" ] || die "Нет заголовков сервера: $PG_INCLUDE"
PG_MODULE_DIR="$("$PG_BIN/pg_config" --pkglibdir)"
PG_SHARE="$("$PG_BIN/pg_config" --sharedir)/extension"
PG_CFLAGS="-O2 -fPIC -I$PG_INCLUDE -I$PGINSTALL/include"
PG_CFLAGS="$PG_CFLAGS -I$PGINSTALL/include/postgresql -I$PGINSTALL/include/postgresql/internal"

mkdir -p "$WORK_DIR" "$PG_MODULE_DIR" "$PG_SHARE"

# --- исходники PostgreSQL ----------------------------------------------------
fetch_sources() {
    local src_dir="$WORK_DIR/postgres-$PG_SOURCE_TAG"
    SRC="$src_dir"
    if [ -d "$src_dir/contrib/ltree" ]; then
        return
    fi
    log "Скачиваю исходники PostgreSQL $PG_VERSION ($PG_SOURCE_TAG)"
    local tarball="$WORK_DIR/postgres-$PG_SOURCE_TAG.tar.gz"
    [ -s "$tarball" ] || curl -fsSL -o "$tarball" \
        "https://codeload.github.com/postgres/postgres/tar.gz/refs/tags/$PG_SOURCE_TAG" \
        || die "Не удалось скачать исходники PostgreSQL"
    tar xzf "$tarball" -C "$WORK_DIR" || die "Не удалось распаковать исходники"
    [ -d "$src_dir" ] || die "Каталог $src_dir не найден после распаковки"
}

# --- минимальный заголовок libuuid (если нет libuuid-dev) --------------------
ensure_uuid_header() {
    local shim="$WORK_DIR/uuid-shim"
    UUID_EXTRA_CFLAGS="-DHAVE_UUID_E2FS -DHAVE_UUID_UUID_H"
    if [ -f /usr/include/uuid/uuid.h ]; then
        UUID_EXTRA_LDFLAGS="-luuid"
        return
    fi
    mkdir -p "$shim/uuid"
    cat > "$shim/uuid/uuid.h" <<'HDR'
/* Минимальный заголовок libuuid (e2fsprogs): нужен только для сборки uuid-ossp. */
#ifndef _PROMERP_UUID_SHIM_H
#define _PROMERP_UUID_SHIM_H
#include <sys/types.h>
typedef unsigned char uuid_t[16];
void uuid_clear(uuid_t uu);
int  uuid_compare(const uuid_t uu1, const uuid_t uu2);
void uuid_copy(uuid_t dst, const uuid_t src);
void uuid_generate(uuid_t out);
void uuid_generate_random(uuid_t out);
void uuid_generate_time(uuid_t out);
int  uuid_is_null(const uuid_t uu);
int  uuid_parse(const char *in, uuid_t uu);
void uuid_unparse(const uuid_t uu, char *out);
void uuid_unparse_lower(const uuid_t uu, char *out);
void uuid_unparse_upper(const uuid_t uu, char *out);
#endif
HDR
    UUID_EXTRA_CFLAGS="$UUID_EXTRA_CFLAGS -I$shim"
    local lib
    lib="$(ls /usr/lib/*/libuuid.so.1 /lib/*/libuuid.so.1 2>/dev/null | head -1)"
    [ -n "$lib" ] || lib="$(ldconfig -p 2>/dev/null | awk '/libuuid\.so\.1 /{print $NF; exit}')"
    [ -n "$lib" ] || die "Нет ни заголовка uuid/uuid.h, ни libuuid — поставьте libuuid1 / uuid-dev"
    UUID_EXTRA_LDFLAGS="$lib"
    warn "libuuid-dev отсутствует — использую заголовок-заглушку и $lib"
}

# --- OpenSSL для pgcrypto ----------------------------------------------------
ensure_openssl() {
    [ -n "$OPENSSL_INCLUDE" ] && [ -d "$OPENSSL_INCLUDE/openssl" ] && return

    # Заголовки от уже установленного OpenSSL
    if [ -f /usr/include/openssl/evp.h ]; then
        OPENSSL_INCLUDE=/usr/include; OPENSSL_LIB=""; return
    fi

    # Заголовки из рабочей сборки PHP (bin/php-env.sh подкладывает свой libcrypto)
    local php_prefix="${PHP_PREFIX:-$HOME/tools/php}"
    if [ -d "$php_prefix/include/openssl" ]; then
        OPENSSL_INCLUDE="$php_prefix/include"; OPENSSL_LIB="$php_prefix/lib"; return
    fi

    local src="$WORK_DIR/openssl-src"
    if [ ! -d "$src/include/openssl" ]; then
        log "Скачиваю исходники OpenSSL (нужны только заголовки)"
        mkdir -p "$src"
        local tarball="$WORK_DIR/openssl.tar.gz"
        local branch="${OPENSSL_BRANCH:-openssl-3.0.16}"
        [ -s "$tarball" ] || curl -fsSL -o "$tarball" \
            "https://codeload.github.com/openssl/openssl/tar.gz/refs/tags/$branch" \
            || die "Не удалось скачать исходники OpenSSL"
        tar xzf "$tarball" -C "$src" --strip-components=1 || die "Не удалось распаковать OpenSSL"
        # Нужны только заголовки: библиотеку OpenSSL не собираем.
        ( cd "$src" && perl Configure linux-x86_64 no-shared no-tests >/dev/null 2>&1 && make build_generated >/dev/null 2>&1 ) \
            || warn "Не удалось сгенерировать заголовки OpenSSL"
    fi
    [ -d "$src/include/openssl" ] || die "Не удалось получить заголовки OpenSSL"

    OPENSSL_INCLUDE="$src/include"
    # Разделяемая библиотека из сборки PHP (той же мажорной ветки 3.x)
    if [ -z "$OPENSSL_LIB" ]; then
        local cand
        for cand in "$PHP_PREFIX/lib/libcrypto.so.3" "$HOME/tools/php84/lib/libcrypto.so.3"; do
            [ -f "$cand" ] && OPENSSL_LIB="$(dirname "$cand")" && break
        done
    fi
}

# --- zlib для pgcrypto -------------------------------------------------------
ensure_zlib() {
    ZLIB_EXTRA_CFLAGS=""
    ZLIB_EXTRA_LDFLAGS=""
    [ -f /usr/include/zlib.h ] && { ZLIB_EXTRA_LDFLAGS="-lz"; return; }

    local src="$WORK_DIR/zlib-src"
    if [ ! -f "$src/libz.a" ]; then
        log "Собираю zlib (нужен pgcrypto)"
        mkdir -p "$src"
        local tarball="$WORK_DIR/zlib.tar.gz"
        [ -s "$tarball" ] || curl -fsSL -o "$tarball" \
            "https://codeload.github.com/madler/zlib/tar.gz/refs/tags/v1.3.1" \
            || die "Не удалось скачать исходники zlib"
        tar xzf "$tarball" -C "$src" --strip-components=1 || die "Не удалось распаковать zlib"
        ( cd "$src" && CFLAGS="-fPIC -O2" ./configure --static >/dev/null 2>&1 && make libz.a >/dev/null 2>&1 ) \
            || die "Не удалось собрать zlib"
    fi
    [ -f "$src/zlib.h" ] || die "Нет zlib.h в $src"
    ZLIB_EXTRA_CFLAGS="-I$src"
    ZLIB_EXTRA_LDFLAGS="$src/libz.a"
}

# --- сборка модуля -----------------------------------------------------------
build_module() {
    local name="$1" src_dir="$2"
    local dir="$src_dir/contrib/$name"
    [ -d "$dir" ] || die "Нет каталога $dir"

    log "Сборка $name"
    local objs=() extra_cflags="$PG_CFLAGS" extra_ldflags=""
    local file
    for file in "$dir"/*.c; do
        [ -e "$file" ] || continue
        objs+=("$file")
    done
    [ ${#objs[@]} -gt 0 ] || die "В $dir нет .c файлов"

    if [ "$name" = "uuid-ossp" ]; then
        ensure_uuid_header
        extra_cflags="$extra_cflags $UUID_EXTRA_CFLAGS"
        extra_ldflags="$UUID_EXTRA_LDFLAGS"
    fi

    if [ "$name" = "pgcrypto" ]; then
        ensure_openssl
        ensure_zlib
        extra_cflags="$extra_cflags -I$OPENSSL_INCLUDE $ZLIB_EXTRA_CFLAGS"
        extra_ldflags="$extra_ldflags $ZLIB_EXTRA_LDFLAGS"
        if [ -n "$OPENSSL_LIB" ]; then
            # libcrypto из сборки PHP: копируем рядом с модулями PostgreSQL,
            # чтобы сервер находил её без внешних переменных окружения.
            local so
            for so in libcrypto.so.3 libssl.so.3; do
                [ -f "$OPENSSL_LIB/$so" ] && cp -f "$OPENSSL_LIB/$so" "$PGINSTALL/lib/$so"
            done
            # линкуемся с точным именем: в сборках PHP нет симлинка libcrypto.so
            extra_ldflags="$extra_ldflags $OPENSSL_LIB/libcrypto.so.3 -Wl,-rpath,$PGINSTALL/lib -Wl,-rpath,$OPENSSL_LIB"
        else
            extra_ldflags="$extra_ldflags -lcrypto"
        fi
    fi

    local out="$WORK_DIR/build-$name.so"
    # shellcheck disable=SC2086
    gcc -shared -fPIC -O2 -Wall -Wno-unused-function \
        $extra_cflags \
        -o "$out" \
        "${objs[@]}" \
        $extra_ldflags \
        || die "Не удалось собрать $name"

    cp "$out" "$PG_MODULE_DIR/$name.so"
    local data
    for data in "$dir"/*.control "$dir"/*--*.sql; do
        [ -e "$data" ] || continue
        cp "$data" "$PG_SHARE/"
    done
    ok "$name.so → $PG_MODULE_DIR, control/sql → $PG_SHARE"
}

fetch_sources

for ext in "${EXTENSIONS[@]}"; do
    build_module "$ext" "$SRC"
done

log "Готово"
echo "    Проверка:"
echo "      LD_LIBRARY_PATH=$PG_LIB $PG_BIN/psql -h 127.0.0.1 -U promerp -d promerp -c 'CREATE EXTENSION IF NOT EXISTS ltree;'"
