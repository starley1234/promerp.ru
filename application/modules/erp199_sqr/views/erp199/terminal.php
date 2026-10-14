<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Терминал: страница, которую открывает сотрудник в поле.
 *
 * Принцип «глупый клиент — умный сервер»: разметка и скрипт умеют ровно три
 * вещи — отправить код, нарисовать форму, которую придумал сервер, и показать
 * вердикт. Никаких названий модулей, статусов и прав здесь нет: их знает
 * `/qr/api/*`. Поэтому же страница годится и для WebView приложения, и для
 * обычного браузера телефона.
 *
 * Переменные: code, scan_url, scan_action_url, register_url, bind_url,
 * inventory_url, targets_url, geo, unknown_hint, kinds, types.
 */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="theme-color" content="#1d4ed8">
    <title>QR-терминал</title>
    <style>
        :root { color-scheme: light dark; --brand: #1d4ed8; --ok: #059669; --warn: #d97706; --bad: #e11d48; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; font: 16px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f3f4f6; color: #111827; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        h2 { font-size: 16px; margin: 0 0 8px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
        .muted { color: #6b7280; font-size: 13px; }
        .code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 20px; font-weight: 600; }
        .row { display: flex; gap: 8px; flex-wrap: wrap; }
        input, select, textarea, button { font: inherit; }
        input[type=text], input[type=search], select, textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; background: #fff; color: inherit;
        }
        button { border: 0; border-radius: 10px; padding: 10px 16px; background: var(--brand); color: #fff; }
        button.secondary { background: #e5e7eb; color: #111827; }
        button.danger { background: var(--bad); }
        .scenario { width: 100%; text-align: left; background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; margin-bottom: 8px; }
        .badge { display: inline-block; border-radius: 999px; padding: 2px 10px; font-size: 12px; background: #e5e7eb; color: #111827; }
        .badge.ok { background: #d1fae5; color: #065f46; }
        .badge.warn { background: #fef3c7; color: #92400e; }
        .badge.bad { background: #ffe4e6; color: #9f1239; }
        .badge.info { background: #dbeafe; color: #1e40af; }
        .field { margin-bottom: 10px; }
        .field label { display: block; font-size: 14px; margin-bottom: 4px; }
        .thumb { width: 72px; height: 72px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; }
        .hidden { display: none !important; }
        .status-ok { color: var(--ok); }
        .status-bad { color: var(--bad); }
        @media (prefers-color-scheme: dark) {
            body { background: #111827; color: #f9fafb; }
            .card { background: #1f2937; border-color: #374151; }
            .muted { color: #9ca3af; }
            input[type=text], input[type=search], select, textarea { background: #111827; border-color: #4b5563; }
            button.secondary { background: #374151; color: #f9fafb; }
            .scenario { background: #1e3a8a; color: #dbeafe; border-color: #1e40af; }
        }
    </style>
</head>
<body>

<h1><i aria-hidden="true">▣</i> QR-терминал</h1>

<section class="card">
    <div class="row">
        <input id="code" type="search" inputmode="text" autocomplete="off" autocapitalize="characters"
               placeholder="Код наклейки или ссылка" value="<?php echo HTML::chars($code); ?>" aria-label="Код наклейки">
        <button id="scan">Показать</button>
    </div>
    <p class="muted" id="hint">Отсканируйте наклейку приложением или введите код руками.</p>
</section>

<div id="result"></div>

<script>
(function () {
    'use strict';

    // Адреса API приходят из представления: страница не собирает их сама.
    var API = {
        resolve: <?php echo json_encode($scan_url); ?>,
        scan: <?php echo json_encode($scan_action_url); ?>,
        register: <?php echo json_encode($register_url); ?>,
        bind: <?php echo json_encode($bind_url); ?>,
        inventory: <?php echo json_encode($inventory_url); ?>,
        targets: <?php echo json_encode($targets_url); ?>
    };

    var KINDS = <?php echo json_encode($kinds, JSON_UNESCAPED_UNICODE); ?>;
    var TYPES = <?php echo json_encode($types, JSON_UNESCAPED_UNICODE); ?>;
    var WANT_GEO = <?php echo $geo ? 'true' : 'false'; ?>;
    var UNKNOWN_HINT = <?php echo json_encode($unknown_hint, JSON_UNESCAPED_UNICODE); ?>;

    var codeInput = document.getElementById('code');
    var resultBox = document.getElementById('result');
    var hint = document.getElementById('hint');

    function el(tag, attrs, children) {
        var node = document.createElement(tag);

        Object.keys(attrs || {}).forEach(function (key) {
            if (key === 'class') { node.className = attrs[key]; }
            else if (key === 'text') { node.textContent = attrs[key]; }
            else { node.setAttribute(key, attrs[key]); }
        });

        (children || []).forEach(function (child) {
            node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
        });

        return node;
    }

    function api(url, options) {
        options = options || {};
        options.headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }, options.headers || {});
        options.credentials = 'same-origin';

        return fetch(url, options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok || body.status === 'error') {
                    throw new Error(body.message || ('Ошибка запроса: ' + response.status));
                }
                return body.data || body;
            });
        });
    }

    // Координаты — необязательное уточнение: в помещении их может не быть.
    function withGeo() {
        return new Promise(function (resolve) {
            if (!WANT_GEO || !navigator.geolocation) { resolve({}); return; }

            var done = false;
            var finish = function (value) { if (!done) { done = true; resolve(value); } };

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    finish({ geo_lat: position.coords.latitude, geo_lon: position.coords.longitude });
                },
                function () { finish({}); },
                { timeout: 4000, maximumAge: 60000 }
            );

            setTimeout(function () { finish({}); }, 4500);
        });
    }

    function badge(state, label) {
        var map = { ok: 'ok', active: 'ok', new: 'info', unbound: 'info', info: 'info', warn: 'warn', damaged: 'warn', blocked: 'warn', lost: 'bad', broken: 'bad', unknown: 'bad' };
        return el('span', { class: 'badge ' + (map[state] || ''), text: label || state });
    }

    function fieldControl(field) {
        var wrapper = el('div', { class: 'field' });
        var id = 'f_' + field.name;

        wrapper.appendChild(el('label', { for: id, text: field.label || field.name }));

        var control;

        if (field.type === 'select' && field.options) {
            control = el('select', { id: id, name: field.name });

            Object.keys(field.options).forEach(function (value) {
                control.appendChild(el('option', { value: value, text: field.options[value] }));
            });
        } else if (field.type === 'textarea') {
            control = el('textarea', { id: id, name: field.name, rows: 3 });
        } else {
            control = el('input', { id: id, name: field.name, type: 'text' });
        }

        if (field.required) { control.setAttribute('required', 'required'); }

        wrapper.appendChild(control);

        return wrapper;
    }

    function readPhoto(input) {
        return new Promise(function (resolve) {
            if (!input.files || !input.files[0]) { resolve(null); return; }

            var reader = new FileReader();
            reader.onload = function () { resolve(reader.result); };
            reader.onerror = function () { resolve(null); };
            reader.readAsDataURL(input.files[0]);
        });
    }

    function renderScenarioForm(card, scenario) {
        var form = el('form', { class: 'card' });
        form.appendChild(el('h2', { text: scenario.label }));

        if (scenario.description) {
            form.appendChild(el('p', { class: 'muted', text: scenario.description }));
        }

        (scenario.fields || []).forEach(function (field) {
            form.appendChild(fieldControl(field));
        });

        var photoInput = el('input', { type: 'file', accept: 'image/*', capture: 'environment', id: 'photo' });
        var photoWrap = el('div', { class: 'field' });
        photoWrap.appendChild(el('label', { for: 'photo', text: scenario.requires_photo ? 'Фото (обязательно)' : 'Фото (при необходимости)' }));
        photoWrap.appendChild(photoInput);
        form.appendChild(photoWrap);

        var comment = el('div', { class: 'field' });
        comment.appendChild(el('label', { for: 'comment', text: scenario.requires_comment ? 'Комментарий (обязательно)' : 'Комментарий' }));
        comment.appendChild(el('input', { id: 'comment', name: 'comment', type: 'text' }));
        form.appendChild(comment);

        var actions = el('div', { class: 'row' });
        var submit = el('button', { type: 'submit', text: 'Выполнить' });
        var cancel = el('button', { type: 'button', class: 'secondary', text: 'Отмена' });
        actions.appendChild(submit);
        actions.appendChild(cancel);
        form.appendChild(actions);

        var message = el('p', { class: 'muted' });
        form.appendChild(message);

        cancel.addEventListener('click', function () { form.remove(); });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submit.disabled = true;
            message.textContent = 'Отправляем…';

            var payload = { scenario: scenario.code, comment: '' };
            var data = new FormData(form);

            data.forEach(function (value, key) {
                if (value !== '') { payload[key] = value; }
            });

            readPhoto(photoInput).then(function (image) {
                if (image) { payload.image = image; }

                return withGeo().then(function (geo) {
                    payload.token = codeInput.value.trim();

                    return api(API.scan, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(Object.assign(payload, geo))
                    });
                });
            }).then(function (data) {
                message.className = 'status-ok';
                message.textContent = data.note || 'Готово';
                form.remove();
                show(codeInput.value.trim());
            }).catch(function (error) {
                submit.disabled = false;
                message.className = 'status-bad';
                message.textContent = error.message;
            });
        });

        card.appendChild(form);
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function renderUnknown(data) {
        var card = el('section', { class: 'card' });
        card.appendChild(el('h2', { text: 'Наклейка не зарегистрирована' }));
        card.appendChild(el('p', { class: 'muted', text: UNKNOWN_HINT }));

        if (!data.can_register) { resultBox.appendChild(card); return; }

        var form = el('form');

        var nameField = el('div', { class: 'field' });
        nameField.appendChild(el('label', { for: 'reg_name', text: 'Что это?' }));
        nameField.appendChild(el('input', { id: 'reg_name', name: 'name', type: 'text', required: 'required' }));
        form.appendChild(nameField);

        var typeField = el('div', { class: 'field' });
        typeField.appendChild(el('label', { for: 'reg_type', text: 'Тип' }));
        var typeSelect = el('select', { id: 'reg_type', name: 'type' });
        TYPES.forEach(function (type) {
            if (type.allow_local || type.adapter === 'local') {
                typeSelect.appendChild(el('option', { value: type.code, text: type.label }));
            }
        });
        typeField.appendChild(typeSelect);
        form.appendChild(typeField);

        var photoField = el('div', { class: 'field' });
        photoField.appendChild(el('label', { for: 'reg_photo', text: 'Фото предмета' }));
        var photoInput = el('input', { id: 'reg_photo', type: 'file', accept: 'image/*', capture: 'environment' });
        photoField.appendChild(photoInput);
        form.appendChild(photoField);

        var commentField = el('div', { class: 'field' });
        commentField.appendChild(el('label', { for: 'reg_comment', text: 'Комментарий' }));
        commentField.appendChild(el('input', { id: 'reg_comment', name: 'comment', type: 'text' }));
        form.appendChild(commentField);

        var submit = el('button', { type: 'submit', text: 'Завести объект' });
        form.appendChild(submit);

        var message = el('p', { class: 'muted' });
        form.appendChild(message);
        card.appendChild(form);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submit.disabled = true;
            message.textContent = 'Сохраняем…';

            var payload = {
                token: codeInput.value.trim(),
                name: nameField.querySelector('input').value,
                type: typeSelect.value,
                comment: commentField.querySelector('input').value
            };

            readPhoto(photoInput).then(function (image) {
                if (image) { payload.image = image; }

                return withGeo().then(function (geo) {
                    return api(API.register, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(Object.assign(payload, geo))
                    });
                });
            }).then(function (data) {
                // Распознавание могло предложить название — покажем, что вышло.
                message.className = 'status-ok';
                message.textContent = data.note || 'Готово';
                show(codeInput.value.trim());
            }).catch(function (error) {
                submit.disabled = false;
                message.className = 'status-bad';
                message.textContent = error.message;
            });
        });

        resultBox.appendChild(card);
    }

    function renderBindForm(data) {
        var card = el('section', { class: 'card' });
        card.appendChild(el('h2', { text: 'Привязать к предмету в системе' }));
        card.appendChild(el('p', { class: 'muted', text: 'Если наклейка уже существует, но ещё не связана с записью — найдите предмет и привяжите.' }));

        var form = el('form');

        var kindField = el('div', { class: 'field' });
        kindField.appendChild(el('label', { for: 'bind_kind', text: 'Что это' }));
        var kindSelect = el('select', { id: 'bind_kind', name: 'kind' });
        Object.keys(KINDS).forEach(function (kind) {
            kindSelect.appendChild(el('option', { value: kind, text: KINDS[kind] }));
        });
        kindField.appendChild(kindSelect);
        form.appendChild(kindField);

        var queryField = el('div', { class: 'field' });
        queryField.appendChild(el('label', { for: 'bind_q', text: 'Поиск' }));
        queryField.appendChild(el('input', { id: 'bind_q', name: 'q', type: 'text' }));
        form.appendChild(queryField);

        var find = el('button', { type: 'button', class: 'secondary', text: 'Найти' });
        form.appendChild(find);

        var list = el('div');
        form.appendChild(list);

        var submit = el('button', { type: 'submit', text: 'Привязать' });
        form.appendChild(submit);

        var message = el('p', { class: 'muted' });
        form.appendChild(message);

        var selected = null;

        find.addEventListener('click', function () {
            list.textContent = 'Ищем…';
            api(API.targets + '?kind=' + encodeURIComponent(kindSelect.value) + '&q=' + encodeURIComponent(queryField.querySelector('input').value))
                .then(function (data) {
                    list.textContent = '';
                    selected = null;

                    if (!data.results || !data.results.length) {
                        list.appendChild(el('p', { class: 'muted', text: 'Ничего не найдено.' }));
                        return;
                    }

                    data.results.forEach(function (item) {
                        var button = el('button', { type: 'button', class: 'scenario', text: item.label + (item.subtitle ? ' — ' + item.subtitle : '') });
                        button.addEventListener('click', function () {
                            selected = item.id;
                            message.className = 'muted';
                            message.textContent = 'Выбрано: ' + item.label;
                        });
                        list.appendChild(button);
                    });
                })
                .catch(function (error) { message.className = 'status-bad'; message.textContent = error.message; });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!selected) { message.className = 'status-bad'; message.textContent = 'Сначала найдите и выберите предмет.'; return; }

            submit.disabled = true;

            withGeo().then(function (geo) {
                return api(API.bind, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.assign({
                        token: codeInput.value.trim(),
                        kind: kindSelect.value,
                        ref: selected
                    }, geo))
                });
            }).then(function () {
                show(codeInput.value.trim());
            }).catch(function (error) {
                submit.disabled = false;
                message.className = 'status-bad';
                message.textContent = error.message;
            });
        });

        card.appendChild(form);
        resultBox.appendChild(card);
    }

    function renderCard(data) {
        var card = el('section', { class: 'card' });
        var head = el('div', { class: 'row' });
        head.appendChild(el('span', { class: 'code', text: (data.token && data.token.code) || codeInput.value.trim() }));

        if (data.token && data.token.status_label) {
            head.appendChild(badge(data.state, data.token.status_label));
        }

        card.appendChild(head);

        if (data.message) {
            card.appendChild(el('p', { class: 'muted', text: data.message }));
        }

        var target = data.target || (data.object ? { label: data.object.name, subtitle: 'Свободный объект', fields: [] } : null);

        if (target) {
            card.appendChild(el('h2', { text: target.label }));
            if (target.subtitle) { card.appendChild(el('p', { class: 'muted', text: target.subtitle })); }

            (target.fields || []).forEach(function (field) {
                card.appendChild(el('p', { text: field.label + ': ' + field.value }));
            });

            if (target.url) {
                var link = el('a', { href: target.url, text: 'Открыть карточку предмета' });
                card.appendChild(link);
            }
        }

        if (data.object) {
            var attrs = data.object.attributes || {};
            Object.keys(attrs).forEach(function (key) {
                card.appendChild(el('p', { class: 'muted', text: key + ': ' + attrs[key] }));
            });
        }

        // Инвентаризация: если сессия открыта, оператор видит прогресс.
        if (data.inventory) {
            card.appendChild(el('p', { class: 'muted', text: 'Инвентаризация ' + data.inventory.code + ': отмечено ' + data.inventory.marked + ' из ' + data.inventory.expected + ' (' + data.inventory.percent + '%)' }));
        }

        resultBox.appendChild(card);

        if (data.photos && data.photos.length) {
            var photos = el('section', { class: 'card' });
            photos.appendChild(el('h2', { text: 'Фотографии' }));
            var row = el('div', { class: 'row' });

            data.photos.forEach(function (photo) {
                row.appendChild(el('a', { href: photo.url, target: '_blank' }, [el('img', { class: 'thumb', src: photo.url, alt: 'Фото' })]));
            });

            photos.appendChild(row);
            resultBox.appendChild(photos);
        }

        if (data.scenarios && data.scenarios.length) {
            var scenarios = el('section', {});
            scenarios.appendChild(el('h2', { text: 'Что сделать' }));

            data.scenarios.forEach(function (scenario) {
                var button = el('button', { type: 'button', class: 'scenario', text: (scenario.label || scenario.code) + (scenario.requires_photo ? ' (нужно фото)' : '') });
                button.addEventListener('click', function () { renderScenarioForm(scenarios, scenario); });
                scenarios.appendChild(button);
            });

            resultBox.appendChild(scenarios);
        }

        if (data.activity && data.activity.length) {
            var history = el('section', { class: 'card' });
            history.appendChild(el('h2', { text: 'Последние действия' }));

            data.activity.forEach(function (row) {
                history.appendChild(el('p', { class: 'muted', text: (row.created_at || '') + ' — ' + (row.action_type || '') + (row.comment ? ': ' + row.comment : '') }));
            });

            resultBox.appendChild(history);
        }
    }

    function show(code) {
        resultBox.textContent = '';

        if (!code) { hint.textContent = 'Введите код наклейки.'; return; }

        hint.textContent = 'Смотрим…';

        api(API.resolve + '/' + encodeURIComponent(code))
            .then(function (data) {
                hint.textContent = 'Код ' + (data.token && data.token.code ? data.token.code : code) + '.';

                resultBox.textContent = '';

                if (data.state === 'unknown') {
                    resultBox.appendChild(el('p', { class: 'muted', text: 'Наклейки нет в реестре: ' + (data.token && data.token.code ? data.token.code : code) }));
                    renderUnknown(data);
                    return;
                }

                renderCard(data);

                if (data.state === 'unbound' && data.can_register !== false) {
                    renderBindForm(data);
                }
            })
            .catch(function (error) {
                hint.textContent = 'Не получилось.';
                resultBox.textContent = '';
                var card = el('section', { class: 'card' });
                card.appendChild(el('p', { class: 'status-bad', text: error.message }));
                resultBox.appendChild(card);
            });
    }

    document.getElementById('scan').addEventListener('click', function () { show(codeInput.value.trim()); });

    codeInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { event.preventDefault(); show(codeInput.value.trim()); }
    });

    // Код пришёл в адресе — сразу показываем карточку, без лишнего нажатия.
    if (codeInput.value) { show(codeInput.value.trim()); }
}());
</script>
</body>
</html>
