<?php defined('SYSPATH') or die('No direct script access.'); ?>
<div  id="api-test-app">
    <div class="max-w-8xl mx-auto">

        <div class="mb-8 border-b border-gray-200 pb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 mb-2">API Demo — визуальный тест</h1>
                <p class="text-lg text-slate-600">Интерактивная проверка REST API модуля MDM / Digital Twin (ERP230)</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm text-slate-600 flex items-center gap-2">
                    <span class="font-medium">Базовый URL</span>
                    <input type="text" id="api-base" value="/erp230_api" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm font-mono w-44 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </label>
                <button type="button" id="btn-run-suite" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow transition">
                    <i class="fas fa-play mr-2"></i> Полный прогон
                </button>
                <button type="button" id="btn-clear-log" class="inline-flex items-center px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg transition">
                    <i class="fas fa-eraser mr-2"></i> Очистить
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8" id="summary-cards">
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">Всего шагов</div>
                <div class="text-2xl font-bold text-slate-800" id="sum-total">—</div>
            </div>
            <div class="bg-white rounded-xl border border-emerald-200 p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wider text-emerald-600 mb-1">Passed</div>
                <div class="text-2xl font-bold text-emerald-700" id="sum-passed">—</div>
            </div>
            <div class="bg-white rounded-xl border border-rose-200 p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wider text-rose-600 mb-1">Failed</div>
                <div class="text-2xl font-bold text-rose-700" id="sum-failed">—</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wider text-slate-500 mb-1">Вердикт</div>
                <div class="text-lg font-bold text-slate-800" id="sum-verdict">ожидание</div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <h2 class="font-bold text-slate-800 text-sm uppercase tracking-wide">
                            <i class="fas fa-list-check mr-2 text-blue-600"></i>Сценарии suite
                        </h2>
                        <span class="text-xs text-slate-400 font-mono">/erp230_ApiTest</span>
                    </div>
                    <ul class="divide-y divide-slate-100 max-h-[28rem] overflow-y-auto" id="suite-steps">
                        <li class="px-4 py-3 text-sm text-slate-400">Нажмите «Полный прогон»</li>
                    </ul>
                </div>

                <div class="bg-slate-900 rounded-xl p-4 shadow-xl">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-indigo-400 font-mono text-xs uppercase">Контекст (_ctx)</h3>
                        <button type="button" id="btn-copy-ctx" class="text-slate-400 hover:text-white text-xs"><i class="fas fa-copy"></i></button>
                    </div>
                    <pre id="ctx-panel" class="text-gray-300 font-mono text-xs leading-relaxed overflow-x-auto max-h-48">{}</pre>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200">
                        <h2 class="font-bold text-slate-800 text-sm uppercase tracking-wide">
                            <i class="fas fa-terminal mr-2 text-indigo-600"></i>Интерактивный вызов
                        </h2>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="grid md:grid-cols-6 gap-3">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Метод</label>
                                <select id="req-method" class="w-full border border-slate-300 rounded-lg px-2 py-2 text-sm font-mono bg-white focus:ring-2 focus:ring-blue-500">
                                    <option>GET</option>
                                    <option>POST</option>
                                    <option>PATCH</option>
                                    <option>DELETE</option>
                                </select>
                            </div>
                            <div class="md:col-span-5">
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Путь (относительно base)</label>
                                <input type="text" id="req-path" value="dictionary/sources" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500" placeholder="object/{id}">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Query string</label>
                            <input type="text" id="req-query" value="" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500" placeholder="q=test&amp;type=text">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">JSON body</label>
                            <textarea id="req-body" rows="6" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500" placeholder='{"type_id":"test_part","org_id":"TEST_LAB","master_code":"DEMO-001"}'></textarea>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-send" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow transition">
                                <i class="fas fa-paper-plane mr-2"></i> Отправить
                            </button>
                            <span id="req-status" class="inline-flex items-center px-3 py-2 text-sm font-mono text-slate-500"></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <h2 class="font-bold text-slate-800 text-sm uppercase tracking-wide">
                            <i class="fas fa-code mr-2 text-emerald-600"></i>Ответ
                        </h2>
                        <div class="flex gap-2">
                            <button type="button" id="btn-copy-resp" class="text-xs text-slate-500 hover:text-slate-800"><i class="fas fa-copy mr-1"></i>Копировать</button>
                        </div>
                    </div>
                    <pre id="resp-panel" class="p-4 text-sm font-mono text-slate-700 overflow-x-auto max-h-[32rem] leading-relaxed bg-slate-50">// ответ появится здесь</pre>
                </div>
            </div>
        </div>

        <section class="mb-8">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center mr-4 shadow-lg">
                    <i class="fas fa-bolt"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">Быстрые пресеты</h2>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3" id="presets"></div>
        </section>

        <section class="mb-8">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-slate-700 text-white rounded-lg flex items-center justify-center mr-4 shadow-lg">
                    <i class="fas fa-scroll"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">Журнал запросов</h2>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">Время</th>
                                <th scope="col" class="px-4 py-3 text-left">Метод</th>
                                <th scope="col" class="px-4 py-3 text-left">URL</th>
                                <th scope="col" class="px-4 py-3 text-left">HTTP</th>
                                <th scope="col" class="px-4 py-3 text-left">ms</th>
                                <th scope="col" class="px-4 py-3 text-left"></th>
                            </tr>
                        </thead>
                        <tbody id="log-body" class="divide-y divide-slate-100">
                            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Пусто</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="bg-slate-100 rounded-lg p-4 text-center">
            <p class="text-xs text-slate-500 uppercase tracking-widest">
                Suite: <span class="font-bold text-slate-700">/erp230_ApiTest</span>
                · API: <span class="font-bold text-slate-700">/erp230_api/*</span> и <span class="font-bold text-slate-700">/mdm/*</span>
                · Роутинг extended: baselines | structure | type | codes
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var presets = [
        { name: 'Dictionary sources', method: 'GET', path: 'dictionary/sources', body: '' },
        { name: 'Type list', method: 'GET', path: 'type/list', body: '' },
        { name: 'Type schema test_part', method: 'GET', path: 'type/schema/test_part', body: '' },
        { name: 'Search text', method: 'GET', path: 'search', query: 'q=WINNER&type=text', body: '' },
        { name: 'Create object', method: 'POST', path: 'object', body: '{\n  "type_id": "test_part",\n  "org_id": "TEST_LAB",\n  "master_code": "DEMO-" + Date.now()\n}' },
        { name: 'Set properties', method: 'POST', path: 'properties/{id}', body: '{\n  "source_id": "plm",\n  "items": {\n    "name": "Demo Part",\n    "weight": { "value": 10.2, "uom": "kg" },\n    "color": "red"\n  }\n}' },
        { name: 'Activate state', method: 'POST', path: 'object/{id}/state', body: '{\n  "state": "active"\n}' },
        { name: 'Get object', method: 'GET', path: 'object/{id}', query: 'with=properties', body: '' },
        { name: 'Get properties', method: 'GET', path: 'properties/{id}', body: '' },
        { name: 'Attribute history', method: 'GET', path: 'attribute_history/{id}', query: 'key=weight', body: '' },
        { name: 'Create link', method: 'POST', path: 'structure/create_link', body: '{\n  "parent_id": "{winner}",\n  "child_id": "{child}",\n  "link_type": "ebom",\n  "qty": 2\n}' },
        { name: 'BOM', method: 'GET', path: 'structure/bom/{id}', query: 'type=ebom', body: '' },
        { name: 'BOM tree', method: 'GET', path: 'structure/bom_tree/{id}', query: 'type=ebom&depth=5', body: '' },
        { name: 'Where used', method: 'GET', path: 'structure/where_used/{id}', query: 'type=ebom', body: '' },
        { name: 'Telemetry', method: 'POST', path: 'telemetry/{id}', body: '{\n  "source_id": "sensor",\n  "readings": {\n    "temperature": { "value": 55.4, "uom": "C" }\n  }\n}' },
        { name: 'Twin', method: 'GET', path: 'twin/{id}', body: '' },
        { name: 'Twin state at', method: 'GET', path: 'twin/{id}/state', query: 'at=' + encodeURIComponent(new Date().toISOString()), body: '' },
        { name: 'Baseline snapshot', method: 'POST', path: 'baselines/snapshot', body: '{\n  "object_id": "{id}",\n  "code": "V1"\n}' },
        { name: 'Baseline list', method: 'GET', path: 'baselines/list/{id}', body: '' },
        { name: 'Baseline verify', method: 'GET', path: 'baselines/verify/{id}', body: '' },
        { name: 'Codes check', method: 'GET', path: 'codes/check', query: 'master_code=DEMO&type_id=test_part&org_id=TEST_LAB', body: '' },
        { name: 'Codes resolve', method: 'GET', path: 'codes/resolve', query: 'master_code=DEMO&type_id=test_part&org_id=TEST_LAB', body: '' },
        { name: 'Xref resolve', method: 'GET', path: 'resolve', query: 'source=test_src&remote=XR-1', body: '' },
        { name: 'Consolidate', method: 'POST', path: 'consolidate', body: '{\n  "source_id": "test_src",\n  "remote_id": "CONS-DEMO",\n  "payload": {\n    "type_id": "test_part",\n    "org_id": "TEST_LAB",\n    "master_code": "CONS-DEMO",\n    "properties": {\n      "name": { "value": "Consolidated Part" },\n      "weight": { "value": 5.5, "uom": "kg" }\n    }\n  }\n}' },
        { name: 'Merge', method: 'POST', path: 'object/{loser}/merge', body: '{\n  "into": "{winner}"\n}' },
        { name: 'Delete link', method: 'DELETE', path: 'structure/delete_link/{link_id}', body: '' }
    ];

    var ctx = {};
    var log = [];

    function $(id) { return document.getElementById(id); }

    function base() {
        return ($('api-base').value || '/erp230_api').replace(/\/+$/, '');
    }

    function pretty(v) {
        try { return JSON.stringify(v, null, 2); } catch (e) { return String(v); }
    }

    function applyCtx(str) {
        if (!str) return str;
        return String(str)
            .replace(/\{winner\}/g, ctx.winner || '{winner}')
            .replace(/\{loser\}/g, ctx.loser || '{loser}')
            .replace(/\{child\}/g, ctx.child || '{child}')
            .replace(/\{link_id\}/g, ctx.link_id || '{link_id}')
            .replace(/\{baseline_id\}/g, ctx.baseline_id || '{baseline_id}')
            .replace(/\{id\}/g, ctx.winner || ctx.child || ctx.id || '{id}');
    }

    function expandBodyTemplate(raw) {
        if (!raw || !String(raw).trim()) return null;
        var s = applyCtx(raw).replace(/"DEMO-" \+ Date\.now\(\)/g, '"DEMO-' + Date.now() + '"');
        try {
            return JSON.parse(s);
        } catch (e) {
            // allow templates with JS-like concat already replaced
            throw new Error('Невалидный JSON body: ' + e.message);
        }
    }

    function statusBadge(ok) {
        if (ok === true) return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800"><i class="fas fa-check mr-1"></i>ok</span>';
        if (ok === false) return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800"><i class="fas fa-times mr-1"></i>fail</span>';
        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">n/a</span>';
    }

    function httpClass(code) {
        if (code >= 200 && code < 300) return 'text-emerald-700 font-semibold';
        if (code >= 400 && code < 500) return 'text-amber-700 font-semibold';
        if (code >= 500) return 'text-rose-700 font-semibold';
        return 'text-slate-600';
    }

    function extractCtxFromSuite(data) {
        if (!data || typeof data !== 'object') return;
        if (data['2_create']) {
            if (data['2_create'].winner) ctx.winner = data['2_create'].winner;
            if (data['2_create'].loser) ctx.loser = data['2_create'].loser;
            if (data['2_create'].child) ctx.child = data['2_create'].child;
        }
        if (data['4_structure'] && data['4_structure'].link_id) ctx.link_id = data['4_structure'].link_id;
        if (data['6_snapshot'] && data['6_snapshot'].baseline_id) ctx.baseline_id = data['6_snapshot'].baseline_id;
        if (data['12_consolidate'] && data['12_consolidate'].object_id) ctx.cons_id = data['12_consolidate'].object_id;
        $('ctx-panel').textContent = pretty(ctx);
    }

    function renderSuite(data) {
        var ul = $('suite-steps');
        ul.innerHTML = '';
        var keys = Object.keys(data || {}).filter(function (k) { return k !== '_summary'; });
        keys.forEach(function (key) {
            var step = data[key] || {};
            var ok = step.ok;
            var li = document.createElement('li');
            li.className = 'px-4 py-3 hover:bg-slate-50 cursor-pointer transition';
            li.innerHTML =
                '<div class="flex items-start justify-between gap-2">' +
                '<div class="min-w-0">' +
                '<div class="font-mono text-sm font-semibold text-slate-800 truncate">' + key + '</div>' +
                '<div class="text-xs text-slate-500 mt-0.5 truncate">' + (step.error ? step.error : (ok === true ? 'success' : (ok === false ? 'failed' : 'info'))) + '</div>' +
                '</div>' + statusBadge(ok) + '</div>';
            li.addEventListener('click', function () {
                $('resp-panel').textContent = pretty(step);
                $('req-status').textContent = 'suite:' + key;
            });
            ul.appendChild(li);
        });

        var sum = data._summary || {};
        $('sum-total').textContent = keys.filter(function (k) { return k !== '0_driver_tx'; }).length;
        $('sum-passed').textContent = sum.passed != null ? sum.passed : '—';
        $('sum-failed').textContent = sum.failed != null ? sum.failed : '—';
        var verdict = sum.verdict || '—';
        var el = $('sum-verdict');
        el.textContent = verdict;
        el.className = 'text-lg font-bold ' + (verdict === 'ALL GREEN' ? 'text-emerald-700' : (sum.failed ? 'text-rose-700' : 'text-slate-800'));
    }

    function pushLog(entry) {
        log.unshift(entry);
        if (log.length > 100) log.pop();
        var tb = $('log-body');
        if (!log.length) {
            tb.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Пусто</td></tr>';
            return;
        }
        tb.innerHTML = log.map(function (e, idx) {
            return '<tr class="hover:bg-slate-50">' +
                '<td class="px-4 py-2 text-slate-500 whitespace-nowrap">' + e.time + '</td>' +
                '<td class="px-4 py-2 font-mono font-semibold">' + e.method + '</td>' +
                '<td class="px-4 py-2 font-mono text-xs max-w-xs truncate" title="' + e.url.replace(/"/g, '&quot;') + '">' + e.url + '</td>' +
                '<td class="px-4 py-2 ' + httpClass(e.http) + '">' + e.http + '</td>' +
                '<td class="px-4 py-2 text-slate-500">' + e.ms + '</td>' +
                '<td class="px-4 py-2"><button type="button" data-log="' + idx + '" class="text-blue-600 hover:underline text-xs log-view">view</button></td>' +
                '</tr>';
        }).join('');
        tb.querySelectorAll('.log-view').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(btn.getAttribute('data-log'), 10);
                var e = log[i];
                if (!e) return;
                $('req-method').value = e.method;
                $('req-path').value = e.path || '';
                $('req-query').value = e.query || '';
                $('req-body').value = e.bodyText || '';
                $('resp-panel').textContent = pretty(e.response);
                $('req-status').textContent = e.http + ' · ' + e.ms + 'ms';
            });
        });
    }

    function apiCall(method, path, query, bodyObj) {
        var q = query ? String(query).replace(/^\?/, '') : '';
        var url = base() + '/' + String(path).replace(/^\//, '');
        if (q) url += (url.indexOf('?') >= 0 ? '&' : '?') + q;

        var opts = {
            method: method,
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        };
        var bodyText = '';
        if (bodyObj !== null && bodyObj !== undefined && method !== 'GET') {
            bodyText = typeof bodyObj === 'string' ? bodyObj : JSON.stringify(bodyObj);
            opts.headers['Content-Type'] = 'application/json';
            opts.body = bodyText;
        }

        var t0 = performance.now();
        return fetch(url, opts).then(function (res) {
            return res.text().then(function (text) {
                var parsed;
                try { parsed = JSON.parse(text); } catch (e) { parsed = { raw: text }; }
                var ms = Math.round(performance.now() - t0);
                var entry = {
                    time: new Date().toLocaleTimeString(),
                    method: method,
                    url: url,
                    path: path,
                    query: q,
                    bodyText: bodyText,
                    http: res.status,
                    ms: ms,
                    response: parsed
                };
                pushLog(entry);
                return entry;
            });
        }).catch(function (err) {
            var ms = Math.round(performance.now() - t0);
            var entry = {
                time: new Date().toLocaleTimeString(),
                method: method,
                url: url,
                path: path,
                query: q,
                bodyText: bodyText,
                http: 0,
                ms: ms,
                response: { status: 'error', message: String(err) }
            };
            pushLog(entry);
            return entry;
        });
    }

    function runSuite() {
        $('btn-run-suite').disabled = true;
        $('suite-steps').innerHTML = '<li class="px-4 py-3 text-sm text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Выполняется полный прогон…</li>';
        $('sum-verdict').textContent = 'running…';

        var t0 = performance.now();
        fetch('/erp230_ApiTest', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                return res.text().then(function (text) {
                    var data;
                    try { data = JSON.parse(text); } catch (e) { data = { _parse_error: true, raw: text, http: res.status }; }
                    var ms = Math.round(performance.now() - t0);
                    pushLog({
                        time: new Date().toLocaleTimeString(),
                        method: 'GET',
                        url: '/erp230_ApiTest',
                        path: '(suite)',
                        query: '',
                        bodyText: '',
                        http: res.status,
                        ms: ms,
                        response: data
                    });
                    if (data && !data._parse_error) {
                        extractCtxFromSuite(data);
                        renderSuite(data);
                        $('resp-panel').textContent = pretty(data);
                        $('req-status').textContent = res.status + ' · ' + ms + 'ms · suite';
                    } else {
                        $('suite-steps').innerHTML = '<li class="px-4 py-3 text-sm text-rose-600">Ошибка разбора ответа suite</li>';
                        $('resp-panel').textContent = pretty(data);
                    }
                });
            })
            .catch(function (err) {
                $('suite-steps').innerHTML = '<li class="px-4 py-3 text-sm text-rose-600">' + String(err) + '</li>';
                $('sum-verdict').textContent = 'ERROR';
            })
            .finally(function () {
                $('btn-run-suite').disabled = false;
            });
    }

    function sendRequest() {
        var method = $('req-method').value;
        var path = applyCtx(($('req-path').value || '').trim());
        var query = applyCtx(($('req-query').value || '').trim());
        var bodyRaw = ($('req-body').value || '').trim();
        var bodyObj = null;

        try {
            if (bodyRaw) bodyObj = expandBodyTemplate(bodyRaw);
        } catch (e) {
            $('req-status').textContent = String(e.message || e);
            $('resp-panel').textContent = String(e.message || e);
            return;
        }

        $('btn-send').disabled = true;
        $('req-status').textContent = 'sending…';
        apiCall(method, path, query, bodyObj).then(function (entry) {
            $('resp-panel').textContent = pretty(entry.response);
            $('req-status').textContent = entry.http + ' · ' + entry.ms + 'ms';

            // auto-harvest ids
            var b = entry.response || {};
            var oid = (b.object && b.object.id) || b.object_id || b.id;
            if (oid && method === 'POST' && /\/object\/?$/.test(base() + '/' + path.replace(/^\//, '').split('?')[0].replace(/object.*/, 'object'))) {
                ctx.id = oid;
            }
            if (oid && path.indexOf('object') === 0 && method === 'POST' && path.replace(/\/+$/, '') === 'object') {
                ctx.id = oid;
                if (!ctx.winner) ctx.winner = oid;
            }
            if (b.link_id) ctx.link_id = b.link_id;
            if (b.baseline_id) ctx.baseline_id = b.baseline_id;
            if (b.object && b.object.id) ctx.id = b.object.id;
            if (b.object_id) ctx.id = b.object_id;
            $('ctx-panel').textContent = pretty(ctx);
        }).finally(function () {
            $('btn-send').disabled = false;
        });
    }

    function renderPresets() {
        var box = $('presets');
        box.innerHTML = presets.map(function (p, i) {
            return '<button type="button" data-preset="' + i + '" class="text-left bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-xl p-3 shadow-sm transition group">' +
                '<div class="flex items-center gap-2 mb-1">' +
                '<span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 group-hover:bg-blue-100 group-hover:text-blue-700">' + p.method + '</span>' +
                '<span class="text-sm font-semibold text-slate-800 truncate">' + p.name + '</span>' +
                '</div>' +
                '<div class="text-xs font-mono text-slate-400 truncate">' + p.path + '</div>' +
                '</button>';
        }).join('');
        box.querySelectorAll('[data-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var p = presets[parseInt(btn.getAttribute('data-preset'), 10)];
                $('req-method').value = p.method;
                $('req-path').value = p.path;
                $('req-query').value = p.query || '';
                var body = p.body || '';
                if (body.indexOf('Date.now()') !== -1) {
                    body = '{\n  "type_id": "test_part",\n  "org_id": "TEST_LAB",\n  "master_code": "DEMO-' + Date.now() + '"\n}';
                }
                $('req-body').value = body;
            });
        });
    }

    $('btn-run-suite').addEventListener('click', runSuite);
    $('btn-send').addEventListener('click', sendRequest);
    $('btn-clear-log').addEventListener('click', function () {
        log = [];
        pushLog; // no-op keep fn
        $('log-body').innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Пусто</td></tr>';
        $('resp-panel').textContent = '// ответ появится здесь';
        $('req-status').textContent = '';
        $('suite-steps').innerHTML = '<li class="px-4 py-3 text-sm text-slate-400">Нажмите «Полный прогон»</li>';
        $('sum-total').textContent = '—';
        $('sum-passed').textContent = '—';
        $('sum-failed').textContent = '—';
        $('sum-verdict').textContent = 'ожидание';
        $('sum-verdict').className = 'text-lg font-bold text-slate-800';
    });
    $('btn-copy-resp').addEventListener('click', function () {
        if (navigator.clipboard) navigator.clipboard.writeText($('resp-panel').textContent || '');
    });
    $('btn-copy-ctx').addEventListener('click', function () {
        if (navigator.clipboard) navigator.clipboard.writeText($('ctx-panel').textContent || '');
    });
    $('req-path').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); sendRequest(); }
    });

    renderPresets();
    $('ctx-panel').textContent = pretty(ctx);
})();
</script>