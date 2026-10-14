/* Accessible remote entity picker. The visible label and submitted internal id never diverge. */
(function () {
    'use strict';

    function init(root) {
        var search = root.querySelector('[data-reference-search]');
        var value = root.querySelector('[data-reference-value]');
        var results = root.querySelector('[data-reference-results]');
        var clear = root.querySelector('[data-reference-clear]');
        var status = root.querySelector('[data-reference-status]');
        var selectedLabel = search.value;
        var options = [];
        var active = -1;
        var timer;
        var request;
        var min = Number(root.dataset.minChars || 2);

        function close() {
            results.classList.add('hidden');
            search.setAttribute('aria-expanded', 'false');
            search.removeAttribute('aria-activedescendant');
            active = -1;
        }

        function setActive(index) {
            if (!options.length) return;
            active = (index + options.length) % options.length;
            options.forEach(function (node, i) {
                node.setAttribute('aria-selected', i === active ? 'true' : 'false');
                node.classList.toggle('bg-blue-50', i === active);
                node.classList.toggle('dark:bg-blue-900/40', i === active);
            });
            search.setAttribute('aria-activedescendant', options[active].id);
            options[active].scrollIntoView({ block: 'nearest' });
        }

        function choose(item) {
            value.value = String(item.id);
            search.value = item.label;
            selectedLabel = item.label;
            clear.hidden = false;
            close();
            value.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function render(items) {
            results.textContent = '';
            options = [];
            items.forEach(function (item, index) {
                var option = document.createElement('button');
                option.type = 'button';
                option.id = results.id + '_option_' + index;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.className = 'block w-full rounded-md px-3 py-2 text-left hover:bg-blue-50 dark:hover:bg-blue-900/40';
                var label = document.createElement('span');
                label.className = 'block text-sm font-medium text-gray-900 dark:text-white';
                label.textContent = item.label;
                option.appendChild(label);
                if (item.context) {
                    var context = document.createElement('span');
                    context.className = 'block truncate text-xs text-gray-500 dark:text-gray-400';
                    context.textContent = item.context;
                    option.appendChild(context);
                }
                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(item);
                });
                results.appendChild(option);
                options.push(option);
            });
            if (!options.length) {
                var empty = document.createElement('p');
                empty.className = 'px-3 py-2 text-sm text-gray-500';
                empty.textContent = 'Ничего не найдено';
                results.appendChild(empty);
            }
            status.textContent = options.length ? 'Найдено вариантов: ' + options.length : 'Вариантов не найдено';
            results.classList.remove('hidden');
            search.setAttribute('aria-expanded', 'true');
        }

        function load() {
            var query = search.value.trim();
            if (query.length < min) {
                close();
                status.textContent = 'Введите ещё ' + (min - query.length) + ' символов';
                return;
            }
            if (request) request.abort();
            request = new AbortController();
            status.textContent = 'Идёт поиск';
            var separator = root.dataset.url.indexOf('?') === -1 ? '?' : '&';
            fetch(root.dataset.url + separator + 'q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json' }, signal: request.signal
            }).then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            }).then(function (data) {
                render(Array.isArray(data.items) ? data.items : []);
            }).catch(function (error) {
                if (error.name === 'AbortError') return;
                close();
                status.textContent = 'Не удалось загрузить варианты';
            });
        }

        search.addEventListener('input', function () {
            if (search.value !== selectedLabel) {
                value.value = '';
                clear.hidden = true;
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(load, 250);
        });
        search.addEventListener('focus', function () { if (search.value.trim().length >= min) load(); });
        search.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') { event.preventDefault(); setActive(active + 1); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); setActive(active - 1); }
            else if (event.key === 'Enter' && active >= 0) { event.preventDefault(); options[active].dispatchEvent(new MouseEvent('mousedown')); }
            else if (event.key === 'Escape') close();
        });
        search.addEventListener('blur', function () {
            window.setTimeout(function () {
                close();
                if (value.value === '') search.value = '';
            }, 150);
        });
        clear.addEventListener('click', function () {
            value.value = '';
            search.value = '';
            selectedLabel = '';
            clear.hidden = true;
            close();
            search.focus();
            value.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function boot() {
        document.querySelectorAll('[data-smart-reference]').forEach(init);
    }
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
}());
