#!/usr/bin/env bash
# Загрузка переменных из .env в окружение процесса + поиск интерпретатора PHP.
#
# Использование:  source bin/env.sh
#
# Kohana не читает .env сама, поэтому обёртки bin/console, bin/serve, bin/test,
# bin/setup.sh подключают этот файл явно.
#
# После source доступны:
#   * переменные из .env (экспортированы в окружение процесса);
#   * функция promerp_php — путь к подходящему PHP 8.1+ или пустая строка.

_repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

set -o allexport
# shellcheck disable=SC1091
[ -f "$_repo_root/.env" ] && . "$_repo_root/.env"
set +o allexport

# Путь к PHP: PHP_BIN из окружения → php из PATH → локальные сборки проекта.
promerp_php() {
    local candidate resolved
    local -a candidates=(
        "${PHP_BIN:-}"
        php
        php8.4
        php84
        php8.3
        php8.2
        /usr/local/bin/php
        /usr/bin/php
        "$_repo_root/tools/php84/php"
        "$HOME/tools/php84/php"
        "$HOME/.local/promerp-env/php/php"
        "$_repo_root/tools/php/bin/php"
        "$HOME/tools/php/bin/php"
    )

    for candidate in "${candidates[@]}"; do
        [ -n "$candidate" ] || continue
        command -v "$candidate" >/dev/null 2>&1 || continue

        resolved="$(command -v "$candidate")"

        # Годится только PHP 8.1+ (ядро Konine 3.4)
        if "$resolved" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' >/dev/null 2>&1; then
            printf '%s' "$resolved"
            return 0
        fi
    done

    return 1
}
