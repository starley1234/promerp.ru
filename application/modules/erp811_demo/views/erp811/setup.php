<?php defined('SYSPATH') or die('No direct script access.'); ?>
<div class="space-y-6">
    <?php
    // Кнопки шапки — как на остальных страницах сервиса
    $actions = '<a href="/demo" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"><i class="fas fa-arrow-left" aria-hidden="true"></i> К обзору</a>';

    if ( ! empty($status['available']))
    {
        $actions .= '<form method="post" action="/demo/enter"><button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">'
            .'<i class="fas fa-right-to-bracket" aria-hidden="true"></i> Войти в демо-режим</button></form>';
    }

    echo Component::render('page_header', array(
        'crumbs'       => array(array('Демо-стенд', '/demo'), array('Управление и диагностика', NULL)),
        'title'        => 'Инициализация и диагностика демо-стенда',
        'lead'         => 'Сборка стенда, проверка его слоёв и журнал последней операции.',
        'actions_html' => $actions,
    ));
    ?>

    <!-- Status Banner -->
    <div class="p-6 rounded-lg <?= !empty($status['available']) ? 'bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60' : 'bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60' ?>">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <span class="w-12 h-12 rounded-lg <?= !empty($status['available']) ? 'bg-emerald-600 text-white' : 'bg-amber-600 text-white' ?> flex items-center justify-center text-2xl shrink-0">
                    <i class="fas <?= !empty($status['available']) ? 'fa-check-double' : 'fa-triangle-exclamation' ?>"></i>
                </span>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        <?= !empty($status['available']) ? 'Демонстрационный стенд активен и готов к работе' : 'Демонстрационный стенд ожидает инициализации' ?>
                    </h2>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                        Хранилище: <code class="px-1.5 py-0.5 rounded bg-white dark:bg-gray-800 font-mono text-gray-900 dark:text-white"><?= htmlspecialchars($status['database'].' / '.$status['schema'].' ('.$status['storage'].')') ?></code>
                        <?php if (!empty($status['built_at'])): ?>
                            &bull; Собран: <span class="font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($status['built_at']) ?></span>
                            (таблиц: <?= (int)$status['tables'] ?>)
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <?php if (!empty($status['available'])): ?>
                <span class="px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 text-xs font-bold uppercase tracking-wider self-start sm:self-auto">
                    READY
                </span>
            <?php else: ?>
                <span class="px-3 py-1 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300 text-xs font-bold uppercase tracking-wider self-start sm:self-auto">
                    NOT INITIALIZED
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2 Column Action Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Card 1: One-click Build / Rebuild -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg">
                        <i class="fas fa-hammer"></i>
                    </span>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-base">
                            Инициализация / Пересборка стенда
                        </h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Автоматический запуск из браузера</span>
                    </div>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                    Подготовит изолированное хранилище <code class="font-mono text-blue-600"><?= HTML::chars($status['schema']) ?></code>, применит SQL-миграции и наполнит склады, заказы, смены цеха и историю движений по сценарию «Контрактная 3D-печать».
                </p>
            </div>

            <form method="post" action="/demo/build" id="build-form" class="space-y-3">
                <label class="block text-xs text-gray-700 dark:text-gray-300">
                    <span class="block font-semibold mb-1">Объём синтетических данных</span>
                    <select name="profile" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        <?php foreach ((array) $build_profiles as $profile_name => $profile): ?>
                            <option value="<?= HTML::chars($profile_name) ?>" <?= $profile_name === $selected_profile ? 'selected' : '' ?>>
                                <?= HTML::chars($profile['label'].' — '.$profile['orders'].' заказов, '.$profile['history_days'].' дней') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="block mt-1 text-gray-500">Для запуска из браузера рекомендуется «Быстрый».</span>
                </label>

                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="reset" value="1" class="rounded text-blue-600 focus:ring-blue-500" <?= (int) $status['tables'] < 20 ? 'checked' : '' ?>>
                    <span>Пересоздать схему с нуля (чистая сборка)</span>
                </label>

                <button type="submit" id="btn-run-build" class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-play"></i>
                    <span><?= !empty($status['available']) ? 'Пересобрать демо-стенд заново' : ((int) $status['tables'] >= 20 ? 'Продолжить и наполнить стенд' : 'Собрать и запустить демо-стенд') ?></span>
                </button>
            </form>
        </div>

        <!-- Card 2: System Health Diagnostics -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg">
                        <i class="fas fa-stethoscope"></i>
                    </span>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-base">
                            Сквозная диагностика и аудит
                        </h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Проверка всех компонентов и сервисов</span>
                    </div>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                    Проверит целостность связей: остатки WMS, заказы на производство, работу цехового терминала, контрольные акты ОТК, закупки, документы и витрины аналитики.
                </p>
            </div>

            <form method="post" action="/demo/diagnose" id="diag-form">
                <button type="submit" id="btn-run-diag" class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-2 <?= empty($status['available']) ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= empty($status['available']) ? 'disabled' : '' ?>>
                    <i class="fas fa-vial-circle-check"></i>
                    <span>Запустить тестирование и собрать лог</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Diagnostic Results Summary (if available) -->
    <?php if (!empty($last_report['diagnostics'])): 
        $diag = $last_report['diagnostics'];
    ?>
    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-clipboard-check text-emerald-500"></i>
                Результаты последнего сквозного тестирования
            </h3>
            <span class="px-2.5 py-1 rounded-full <?= $diag['success'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800' ?> text-xs font-bold">
                Пройдено: <?= $diag['passed_tests'] ?> / <?= $diag['total_tests'] ?>
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <?php foreach ($diag['tests'] as $t): ?>
            <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700/60 space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-900 dark:text-white truncate"><?= htmlspecialchars($t['name']) ?></span>
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase <?= $t['status'] === 'PASS' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-rose-100 text-rose-800' ?>">
                        <?= $t['status'] ?>
                    </span>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 line-clamp-2"><?= htmlspecialchars($t['details']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Live Execution & Diagnostic Log Terminal -->
    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-terminal text-blue-500"></i>
                    Диагностический лог операций
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Этот лог содержит подробные результаты выполнения всех этапов и готов к передаче разработчику.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-call="copyLogToClipboard" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 transition-colors flex items-center gap-1.5">
                    <i class="fas fa-copy"></i>
                    <span>Скопировать лог</span>
                </button>
                <a href="/demo/log_download" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 transition-colors flex items-center gap-1.5">
                    <i class="fas fa-download"></i>
                    <span>Скачать .log</span>
                </a>
            </div>
        </div>

        <div class="relative">
            <pre id="log-console" class="p-4 rounded-lg bg-gray-950 text-emerald-400 font-mono text-xs overflow-x-auto max-h-96 min-h-[160px] leading-relaxed select-all whitespace-pre-wrap"><?= htmlspecialchars($last_log ?: "Журнал пока пуст.\nНажмите «Собрать и запустить демо-стенд» или «Запустить тестирование и собрать лог» выше.") ?></pre>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('build-form');
    var button = document.getElementById('btn-run-build');
    var log = document.getElementById('log-console');
    if (!form || !window.fetch) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-wait');
        button.querySelector('span').textContent = 'Сборка выполняется…';
        log.textContent = 'Запуск сборки…';

        var observedRunning = false;
        var timer = setInterval(function () {
            fetch('/demo/progress', {headers: {'Accept': 'application/json'}, cache: 'no-store'})
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.log) {
                        log.textContent = data.log;
                        log.scrollTop = log.scrollHeight;
                    }
                    if (data.running) observedRunning = true;
                    var finished = data.log && /СБОРКА .*ЗАВЕРШЕНА/.test(data.log);
                    if (!data.running && (observedRunning || finished)) {
                        clearInterval(timer);
                        window.location.reload();
                    }
                }).catch(function () {});
        }, 1500);

        fetch(form.action + '?format=json', {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.log) log.textContent = data.log;
                clearInterval(timer);
                window.location.reload();
            })
            .catch(function () {
                // Apache мог закрыть долгий HTTP-запрос; backend с
                // ignore_user_abort продолжает работу, polling остаётся.
                log.textContent += '\nСоединение с запросом закрыто; ожидаем завершения фоновой сборки…';
            });
    });
}());

function copyLogToClipboard() {
    var logEl = document.getElementById('log-console');
    if (!logEl) return;
    navigator.clipboard.writeText(logEl.textContent).then(function() {
        alert('Полный диагностический лог скопирован в буфер обмена! Вы можете отправить его разработчику.');
    }).catch(function() {
        alert('Не удалось скопировать лог автоматически. Выделите текст и скопируйте вручную (Ctrl+C).');
    });
}
</script>
