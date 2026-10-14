#!/usr/bin/env bash
#
# Подготовка окружения разработки PromERP:
#   * рабочие каталоги (cache, logs, sessions, uploads);
#   * application/config/* из шаблонов application/config.dist/*;
#   * .env со сгенерированными секретами (COOKIE_SALT, AUTH_HASH_KEY);
#   * заглушка vendor/autoload.php, если composer ещё не запускался.
#
# Запуск:
#   bash bin/setup.sh           # создать отсутствующее (существующее не трогаем)
#   bash bin/setup.sh --force   # перезаписать конфиги шаблонами и .env
#   bash bin/setup.sh --no-composer # не менять vendor (для CI базового контура)
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

FORCE=0
QUIET=0
NO_COMPOSER=0
for arg in "$@"; do
    case "$arg" in
        --force) FORCE=1 ;;
        --quiet) QUIET=1 ;;
        --no-composer) NO_COMPOSER=1 ;;
        -h|--help) sed -n '2,16p' "$0"; exit 0 ;;
    esac
done

say()  { [ "$QUIET" = "1" ] || printf '%s\n' "$*"; }

# shellcheck disable=SC1091
source bin/env.sh

PHP_BIN="$(promerp_php || true)"

random_hex() {
    if [ -n "$PHP_BIN" ] && command -v "$PHP_BIN" >/dev/null 2>&1; then
        "$PHP_BIN" -r 'echo bin2hex(random_bytes(32));'
    else
        head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n'
    fi
}

say "== PromERP: подготовка окружения =="

# --- 1. Каталоги ------------------------------------------------------------
say ""
say "-- Рабочие каталоги"
mkdir -p application/cache application/logs application/sessions application/config \
         public_html/uploads public_html/avatar
chmod -R 775 application/cache application/logs application/sessions \
             public_html/uploads public_html/avatar 2>/dev/null || true
say "   ok: application/{cache,logs,sessions}, public_html/{uploads,avatar}"

# --- 2. .env ---------------------------------------------------------------
say ""
say "-- Файл окружения .env"
env_created=0
if [ ! -f .env ] || [ "$FORCE" = "1" ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        printf 'DB_HOST=127.0.0.1\nDB_PORT=5432\nDB_NAME=promerp\nDB_USER=promerp\nDB_PASS=promerp\n' > .env
    fi
    env_created=1
fi

# В уже существующем .env секрет мог остаться пустым (например, после ручного
# копирования .env.example). Такой файл нельзя считать готовым: Auth упадёт при
# создании администратора. Непустые значения при обычном запуске не меняем.
secrets_generated=0
for name in COOKIE_SALT AUTH_HASH_KEY; do
    current="$(awk -F= -v n="$name" '$1==n {sub(/^[^=]*=/, ""); print; exit}' .env)"
    if [ "$FORCE" = "1" ] || [ -z "$current" ]; then
        value="$(random_hex)"
        if grep -q "^${name}=" .env; then
            awk -v n="$name" -v v="$value" \
                'BEGIN{FS=OFS="="} $1==n{print n"="v; next} {print}' .env > .env.tmp
            mv .env.tmp .env
        else
            printf '%s=%s\n' "$name" "$value" >> .env
        fi
        secrets_generated=1
    fi
done

chmod 600 .env 2>/dev/null || true
if [ "$env_created" = "1" ]; then
    say "   + .env создан, секреты сгенерированы"
elif [ "$secrets_generated" = "1" ]; then
    say "   + пустые секреты в .env сгенерированы"
else
    say "   пропуск (.env уже настроен)"
fi

# --- 3. Конфиги ------------------------------------------------------------
say ""
say "-- Конфигурация (application/config.dist -> application/config)"
if [ ! -d application/config.dist ]; then
    echo "   каталог application/config.dist не найден" >&2
    exit 1
fi

for src in application/config.dist/*.php; do
    name="$(basename "$src")"
    dst="application/config/$name"
    if [ -f "$dst" ] && [ "$FORCE" -eq 0 ]; then
        say "   пропуск (уже есть): $name"
    else
        cp "$src" "$dst"
        say "   + $name"
    fi
done

# --- 4. Composer -----------------------------------------------------------
say ""
say "-- Composer"
if [ "$NO_COMPOSER" = "1" ]; then
    say "   пропуск (--no-composer): используется зафиксированный базовый автозагрузчик"
elif command -v composer >/dev/null 2>&1; then
    composer install --no-interaction --no-progress || say "   composer install завершился с ошибкой"
elif [ -f composer.phar ]; then
    "$PHP_BIN" composer.phar install --no-interaction || true
else
    say "   composer не найден — оставляю vendor/autoload.php заглушкой (make deps)"
fi

if [ ! -f vendor/autoload.php ]; then
    mkdir -p vendor
    cat > vendor/autoload.php <<'PHP'
<?php
/**
 * Резервный автозагрузчик: включён, пока не выполнен composer install.
 * Регистрирует только пространства имён самого проекта.
 */
spl_autoload_register(function ($class) {
    $prefixes = [
        'Tests\\' => __DIR__.'/../tests/',
    ];

    foreach ($prefixes as $prefix => $base)
    {
        if (strpos($class, $prefix) === 0)
        {
            $file = $base.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            if (is_file($file))
            {
                require $file;
                return;
            }
        }
    }
});
PHP
    say "   + vendor/autoload.php (заглушка без composer)"
fi

# --- 5. Диагностика --------------------------------------------------------
say ""
if [ -n "$PHP_BIN" ]; then
    say "-- Проверка окружения"
    "$PHP_BIN" public_html/index.php --task=dev:doctor \
        || say "   диагностика вернула замечания (до накатки миграций это нормально)"
else
    say "   php не найден в PATH — выполните bash bin/restore-env.sh --php-only"
fi

say ""
say "Готово. Дальше:"
say "  make migrate     # накатить миграции PostgreSQL"
say "  make seed        # создать администратора"
say "  make serve       # поднять dev-сервер (http://localhost:8080)"
