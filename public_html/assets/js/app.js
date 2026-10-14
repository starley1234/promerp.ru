/* PromERP UI behaviour. Deliberately event-driven: views only provide data-* values. */
(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(function () {
        var html = document.documentElement;
        var body = document.body;

        // --- Theme Management (Day / Night Toggle) ---
        function applyTheme(isDark, persist) {
            html.classList.toggle('dark', isDark);
            html.style.colorScheme = isDark ? 'dark' : 'light';
            if (body) body.classList.toggle('dark', isDark);

            if (persist !== false) {
                try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (e) {}
            }

            document.querySelectorAll('.theme-toggle-btn').forEach(function (btn) {
                btn.setAttribute('aria-label', isDark ? 'Включить светлую тему' : 'Включить тёмную тему');
                btn.setAttribute('title', isDark ? 'Включить светлую тему' : 'Включить тёмную тему');
                btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            });

            document.dispatchEvent(new CustomEvent('promerp:themechange', {
                detail: { dark: isDark }
            }));
        }

        // theme-init.js уже выбрал тему до первой отрисовки. Здесь синхронизируем
        // native-контролы и доступные подписи, не создавая лишнюю настройку.
        applyTheme(html.classList.contains('dark'), false);

        // Handle clicks on any theme toggle button (header or sidebar)
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.theme-toggle-btn, #theme-toggle-btn') : null;
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                var currentIsDark = html.classList.contains('dark');
                applyTheme(!currentIsDark);
            }
        });

        // --- Sidebar Management (Desktop Collapsing & Mobile Drawer) ---
        var desktopToggleBtn = document.getElementById('sidebar-toggle-desktop');
        var mobileDrawer = document.getElementById('mobile-sidebar-drawer');
        var mobilePanel = document.getElementById('mobile-sidebar-panel');
        var mobileToggleBtn = document.getElementById('mobile-sidebar-toggle');
        var mobileCloseBtn = document.getElementById('sidebar-close-mobile');
        var backdrop = document.getElementById('sidebar-backdrop');

        // --- Sidebar tooltip (свёрнутое меню) ---
        // Подписи пунктов прячутся, и остаётся колонка иконок: без подсказки
        // непонятно, куда ведёт пункт. Список пунктов прокручивается и обрезал
        // бы подсказку, поэтому элемент один и позиционируется по месту пункта.
        var sidebarTooltip = document.getElementById('sidebar-tooltip');

        function hideSidebarTooltip() {
            if (sidebarTooltip) {
                sidebarTooltip.classList.remove('is-visible');
                sidebarTooltip.setAttribute('aria-hidden', 'true');
            }
        }

        function showSidebarTooltip(item) {
            if (!sidebarTooltip || !html.classList.contains('sidebar-is-collapsed')) return;
            if (window.matchMedia && !window.matchMedia('(min-width: 768px)').matches) return;

            var label = item.getAttribute('data-nav-title');
            if (!label) return;

            var rect = item.getBoundingClientRect();

            sidebarTooltip.textContent = label;
            sidebarTooltip.style.left = Math.round(rect.right + 10) + 'px';
            sidebarTooltip.style.top = Math.round(rect.top + (rect.height - 26) / 2) + 'px';
            sidebarTooltip.classList.add('is-visible');
            sidebarTooltip.setAttribute('aria-hidden', 'false');
        }

        function bindSidebarTooltips() {
            document.querySelectorAll('#main-sidebar .sidebar-nav-item[data-nav-title]').forEach(function (item) {
                item.addEventListener('mouseenter', function () { showSidebarTooltip(item); });
                item.addEventListener('focus', function () { showSidebarTooltip(item); });
                item.addEventListener('mouseleave', hideSidebarTooltip);
                item.addEventListener('blur', hideSidebarTooltip);
            });
        }

        bindSidebarTooltips();

        function toggleSidebarCollapse() {
            var isCollapsed = html.classList.toggle('sidebar-is-collapsed');
            try { localStorage.setItem('promerp_sidebar_collapsed', isCollapsed ? '1' : '0'); } catch (e) {}
            if (desktopToggleBtn) {
                desktopToggleBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                desktopToggleBtn.setAttribute('title', isCollapsed ? 'Развернуть меню' : 'Свернуть меню');
                desktopToggleBtn.setAttribute('aria-label', isCollapsed ? 'Развернуть меню' : 'Свернуть меню');
            }
            // Подсказка относится к свёрнутому виду: при переключении прячем.
            hideSidebarTooltip();
        }

        if (desktopToggleBtn) {
            desktopToggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleSidebarCollapse();
            });
        }

        window.addEventListener('scroll', hideSidebarTooltip, true);
        window.addEventListener('resize', hideSidebarTooltip);

        function openMobileSidebar() {
            if (!mobileDrawer || !mobilePanel) return;
            mobileDrawer.classList.remove('hidden');
            window.setTimeout(function () {
                mobilePanel.classList.remove('-translate-x-full');
            }, 10);
            if (mobileToggleBtn) mobileToggleBtn.setAttribute('aria-expanded', 'true');
        }

        function closeMobileSidebar() {
            if (!mobileDrawer || !mobilePanel) return;
            mobilePanel.classList.add('-translate-x-full');
            window.setTimeout(function () {
                mobileDrawer.classList.add('hidden');
            }, 250);
            if (mobileToggleBtn) mobileToggleBtn.setAttribute('aria-expanded', 'false');
        }

        if (mobileToggleBtn) mobileToggleBtn.addEventListener('click', function (e) { e.preventDefault(); openMobileSidebar(); });
        if (mobileCloseBtn) mobileCloseBtn.addEventListener('click', function (e) { e.preventDefault(); closeMobileSidebar(); });
        if (backdrop) backdrop.addEventListener('click', closeMobileSidebar);

        // --- Command Palette (Ctrl+K) ---
        var palette = document.getElementById('command-palette');
        var paletteButton = document.getElementById('command-palette-button');
        var paletteInput = document.getElementById('command-palette-input');
        var paletteItems = Array.prototype.slice.call(document.querySelectorAll('.command-palette-item'));
        var paletteBackdrop = document.getElementById('command-palette-backdrop');

        function closePalette() {
            if (!palette) return;
            palette.classList.add('hidden');
            if (paletteInput) paletteInput.value = '';
            paletteItems.forEach(function (item) { item.classList.remove('hidden'); });
        }

        function openPalette() {
            if (!palette) return;
            palette.classList.remove('hidden');
            if (paletteInput) {
                window.setTimeout(function () { paletteInput.focus(); }, 20);
            }
        }

        if (paletteButton) paletteButton.addEventListener('click', function (e) { e.preventDefault(); openPalette(); });
        if (paletteBackdrop) paletteBackdrop.addEventListener('click', closePalette);

        if (paletteInput) {
            paletteInput.addEventListener('input', function () {
                var query = paletteInput.value.trim().toLocaleLowerCase('ru-RU');
                paletteItems.forEach(function (item) {
                    var searchData = item.dataset.search || '';
                    item.classList.toggle('hidden', query !== '' && searchData.indexOf(query) === -1);
                });
            });

            paletteInput.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closePalette();
                if (event.key === 'Enter') {
                    var first = paletteItems.find(function (item) { return !item.classList.contains('hidden'); });
                    if (first) window.location.href = first.href;
                }
            });
        }

        // --- User Dropdown Menu ---
        var userButton = document.getElementById('userDropdownButton');
        var userMenu = document.getElementById('userDropdownMenu');
        var userArrow = document.getElementById('dropdownArrow');

        function closeUserMenu() {
            if (!userMenu || userMenu.classList.contains('hidden')) return;
            userMenu.classList.add('opacity-0', 'scale-95');
            if (userButton) userButton.setAttribute('aria-expanded', 'false');
            if (userArrow) userArrow.classList.remove('rotate-180');
            window.setTimeout(function () { userMenu.classList.add('hidden'); }, 150);
        }

        if (userButton && userMenu) {
            userButton.addEventListener('click', function (event) {
                event.stopPropagation();
                var isHidden = userMenu.classList.contains('hidden');
                if (!isHidden) {
                    closeUserMenu();
                    return;
                }
                userMenu.classList.remove('hidden');
                userButton.setAttribute('aria-expanded', 'true');
                window.setTimeout(function () { userMenu.classList.remove('opacity-0', 'scale-95'); }, 10);
                if (userArrow) userArrow.classList.add('rotate-180');
            });
        }

        // --- Safe dynamic presentation helpers (replace inline style attributes) ---
        document.querySelectorAll('[data-ui-color]').forEach(function (node) {
            var color = String(node.dataset.uiColor || '').trim();
            if (!/^#[0-9a-f]{3,8}$/i.test(color)) return;
            var mode = node.dataset.colorMode || 'color';
            if (mode === 'background') node.style.backgroundColor = color;
            else if (mode === 'soft-background') node.style.backgroundColor = color + (color.length === 7 ? '20' : '');
            else if (mode === 'border') node.style.borderColor = color;
            else if (mode === 'border-left') node.style.borderLeftColor = color;
            else if (mode === 'badge') {
                node.style.color = color;
                node.style.borderColor = color + (color.length === 7 ? '40' : '');
                node.style.backgroundColor = color + (color.length === 7 ? '20' : '');
            } else node.style.color = color;
        });
        document.querySelectorAll('[data-indent]').forEach(function (node) {
            var indent = Math.max(0, Math.min(240, Number(node.dataset.indent) || 0));
            node.style.paddingLeft = indent + 'px';
        });
        document.querySelectorAll('[data-margin-indent]').forEach(function (node) {
            var indent = Math.max(0, Math.min(240, Number(node.dataset.marginIndent) || 0));
            node.style.marginLeft = indent + 'px';
        });

        // --- Progress bars helper ---
        document.querySelectorAll('[data-progress], [data-progress-height]').forEach(function (node) {
            var value = Number(node.dataset.progress || node.dataset.progressHeight);
            if (!Number.isFinite(value)) return;
            value = Math.max(0, Math.min(100, value));
            if (node.dataset.progress !== undefined) node.style.width = value + '%';
            if (node.dataset.progressHeight !== undefined) node.style.height = value + '%';
        });

        // --- Global Keyboard & Click Handlers ---
        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                palette && palette.classList.contains('hidden') ? openPalette() : closePalette();
            }
            if (event.key === 'Escape') {
                closePalette();
                closeUserMenu();
                closeMobileSidebar();
            }
        });

        document.addEventListener('click', function (event) {
            var confirmTarget = event.target.closest ? event.target.closest('[data-confirm]') : null;
            if (confirmTarget && !window.confirm(confirmTarget.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
            if (userMenu && userButton && !userMenu.contains(event.target) && !userButton.contains(event.target)) {
                closeUserMenu();
            }
        }, true);

        // --- Global Data-Action delegation ---
        function resolveCall(name) {
            var parts = name.split('.');
            var context = window;
            for (var index = 0; index < parts.length - 1; index++) {
                context = context ? context[parts[index]] : null;
            }
            return {context:context, fn:context ? context[parts[parts.length - 1]] : null};
        }

        function handleAction(event) {
            var target = event.target.closest ? event.target.closest('[data-action], [data-call], [data-remove-selector]') : null;
            if (!target) return;
            var action = target.dataset.action;
            if (action === 'submit-form') {
                event.preventDefault();
                if (target.form) target.form.requestSubmit ? target.form.requestSubmit() : target.form.submit();
                return;
            }
            if (action === 'submit-target') {
                event.preventDefault();
                var submitTarget = document.querySelector(target.dataset.target || '');
                if (submitTarget) submitTarget.requestSubmit ? submitTarget.requestSubmit() : submitTarget.submit();
                return;
            }
            if (action === 'print') {
                event.preventDefault();
                window.print();
                return;
            }
            if (action === 'back') {
                event.preventDefault();
                window.history.back();
                return;
            }
            if (action === 'click-target') {
                event.preventDefault();
                var clickTarget = document.querySelector(target.dataset.target || '');
                if (clickTarget) clickTarget.click();
                return;
            }
            if (action === 'show-target' || action === 'hide-target') {
                event.preventDefault();
                var visibilityTarget = document.querySelector(target.dataset.target || '');
                if (visibilityTarget) visibilityTarget.classList.toggle('hidden', action === 'hide-target');
                return;
            }
            if (action === 'copy-target' || action === 'copy-text') {
                event.preventDefault();
                var copied = target.dataset.copyText || '';
                if (action === 'copy-target') {
                    var copyTarget = document.querySelector(target.dataset.target || '');
                    copied = copyTarget ? (copyTarget.textContent || copyTarget.value || '') : '';
                }
                if (navigator.clipboard) navigator.clipboard.writeText(copied);
                window.showNotification('Скопировано в буфер обмена', 'success', 1800);
                return;
            }
            if (action === 'set-previous-value') {
                event.preventDefault();
                if (target.previousElementSibling) target.previousElementSibling.value = target.dataset.value || '';
                return;
            }
            if (action === 'mirror-next') {
                if (target.nextElementSibling) target.nextElementSibling.value = target.value;
                return;
            }
            if (action === 'mirror-target') {
                var mirrorTarget = document.querySelector(target.dataset.target || '');
                if (mirrorTarget) mirrorTarget.value = target.value;
                return;
            }
            if (action === 'reload') {
                event.preventDefault();
                window.location.reload();
                return;
            }
            if (action === 'request') {
                event.preventDefault();
                fetch(target.dataset.url, {method:target.dataset.method || 'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
                    .then(function(response){return response.json().then(function(data){return {ok:response.ok,data:data};});})
                    .then(function(result){
                        if (!result.ok || result.data.success === false) throw new Error(result.data.error || result.data.message || 'Ошибка запроса');
                        window.showNotification(target.dataset.success || 'Готово','success');
                        if (target.dataset.reload === '1') window.setTimeout(function(){window.location.reload();},600);
                    })
                    .catch(function(error){window.showNotification(error.message || 'Ошибка запроса','error');});
                return;
            }
            if (action === 'navigate-value') {
                window.location.href = target.value;
                return;
            }
            if (action === 'remove-parent') {
                event.preventDefault();
                if (target.parentElement) target.parentElement.remove();
                return;
            }
            if (target.dataset.removeSelector) {
                event.preventDefault();
                var removable = target.closest(target.dataset.removeSelector);
                if (removable) removable.remove();
                return;
            }
            if (target.dataset.call) {
                var callable = resolveCall(target.dataset.call);
                if (typeof callable.fn === 'function') {
                    event.preventDefault();
                    var context = callable.context === window ? target : callable.context;
                    if (target.dataset.callArgs) {
                        try {
                            var args = JSON.parse(target.dataset.callArgs);
                            if (!Array.isArray(args)) args = [args];
                            // Existing legacy handlers commonly expect (id, element).
                            callable.fn.apply(context, args.concat([target, event]));
                        } catch (error) {
                            window.showNotification('Не удалось выполнить действие: неверные параметры интерфейса', 'error');
                        }
                    } else {
                        // Event-first keeps form handlers submitSomething(event) compatible.
                        callable.fn.call(context, event);
                    }
                }
            }
        }

        document.addEventListener('click', handleAction, true);
        document.addEventListener('change', handleAction, true);
        document.addEventListener('submit', handleAction, true);

        // Подтверждение действия (data-confirm) проверяем и на отправке формы:
        // диалог нужен не только при клике кнопкой, но и по Enter в поле.
        document.addEventListener('submit', function (event) {
            var target = event.target.closest ? event.target.closest('[data-confirm]') : null;
            if (target && !window.confirm(target.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);

        // --- Toast Notifications ---
        window.showNotification = function (message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;
            var container = document.getElementById('notification-container');
            if (!container) return;
            var toast = document.createElement('div');
            toast.className = 'notification ' + type + ' shadow-lg';
            var icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle',
                warning: 'fa-triangle-exclamation'
            };
            var icon = document.createElement('i');
            icon.className = 'fas ' + (icons[type] || icons.info) + ' mr-3 text-lg';
            icon.setAttribute('aria-hidden', 'true');
            var text = document.createElement('span');
            text.className = 'flex-1 text-sm font-medium';
            text.textContent = String(message);
            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'ml-4 hover:opacity-70 text-lg leading-none';
            close.setAttribute('aria-label', 'Закрыть уведомление');
            close.textContent = '×';
            toast.append(icon, text, close);
            container.appendChild(toast);
            window.setTimeout(function () { toast.classList.add('show'); }, 10);
            var remove = function () {
                toast.classList.remove('show');
                window.setTimeout(function () { toast.remove(); }, 250);
            };
            var timer = window.setTimeout(remove, duration);
            close.addEventListener('click', function () {
                window.clearTimeout(timer);
                remove();
            });
        };

        // --- Failure-isolated lazy fragments (do not block the page response) ---
        function initBookmark(button) {
            if (!button || button.dataset.ready === '1') return;
            button.dataset.ready = '1';
            var icon = button.querySelector('[data-bookmark-icon]');
            var setSaved = function (saved) {
                if (icon) {
                    icon.classList.toggle('fa-regular', !saved);
                    icon.classList.toggle('fa-solid', saved);
                    icon.classList.toggle('text-blue-600', saved);
                }
                var label = saved ? 'Удалить текущую страницу из закладок' : 'Сохранить текущую страницу в закладки';
                button.title = label;
                button.setAttribute('aria-label', label);
                button.setAttribute('aria-pressed', saved ? 'true' : 'false');
            };
            var currentUrl = window.location.href;
            fetch(button.dataset.statusUrl + '?url=' + encodeURIComponent(currentUrl), {headers:{'Accept':'application/json'}})
                .then(function(response){return response.ok ? response.json() : null;})
                .then(function(data){if(data && data.status === 'success') setSaved(Boolean(data.is_saved));})
                .catch(function(){});
            button.addEventListener('click', function () {
                var data = new URLSearchParams({url:currentUrl,title:document.title,description:(document.querySelector('meta[name="description"]') || {}).content || ''});
                fetch(button.dataset.toggleUrl, {method:'POST',headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:data.toString()})
                    .then(function(response){return response.ok ? response.json() : null;})
                    .then(function(result){if(result && result.status === 'success') setSaved(result.action === 'saved');})
                    .catch(function(){window.showNotification('Закладка временно недоступна','warning');});
            });
        }
        document.querySelectorAll('[data-lazy-fragment]').forEach(function (slot) {
            fetch(slot.dataset.lazyFragment, {headers:{'X-Requested-With':'XMLHttpRequest'}})
                .then(function(response){return response.ok ? response.text() : '';})
                .then(function(markup){
                    if (!markup) return;
                    var documentFragment = document.createRange().createContextualFragment(markup);
                    slot.replaceWith(documentFragment);
                    initBookmark(document.querySelector('[data-bookmark-toggle]'));
                })
                .catch(function(){});
        });

        // --- Demo mode indicator ---
        // Кнопка в шапке: объясняет режим и даёт выйти. Меню закрывается по
        // клику вне и по Escape — как остальные выпадающие меню шапки.
        var demoButton = document.getElementById('demo-mode-button');
        var demoMenu = document.getElementById('demo-mode-menu');

        if (demoButton && demoMenu) {
            var closeDemoMenu = function () {
                demoMenu.classList.add('hidden');
                demoButton.setAttribute('aria-expanded', 'false');
            };

            demoButton.addEventListener('click', function (event) {
                event.stopPropagation();
                var isHidden = demoMenu.classList.contains('hidden');
                demoMenu.classList.toggle('hidden', !isHidden);
                demoButton.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            });

            document.addEventListener('click', function (event) {
                if (!demoMenu.contains(event.target) && !demoButton.contains(event.target)) {
                    closeDemoMenu();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closeDemoMenu();
            });
        }

        // --- Connection status (цех: сеть пропадает чаще, чем в офисе) ---
        // Пока связь есть, индикатор скрыт: он нужен в момент потери, а не
        // как постоянная надпись «всё хорошо».
        (function () {
            var indicator = document.getElementById('connection-status');
            if (!indicator) return;

            var show = function (offline) {
                indicator.hidden = !offline;
            };

            show(navigator.onLine === false);

            window.addEventListener('offline', function () { show(true); });
            window.addEventListener('online', function () { show(false); });
        }());

        // --- Unread Notifications Badge Polling ---
        if (body && body.dataset.notifications === '1') {
            var updateBadge = function () {
                fetch('/erp217_api/unread_count', { headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) {
                        var badge = document.getElementById('notification-badge');
                        if (!badge) return;
                        var count = data && Number(data.count) > 0 ? Number(data.count) : 0;
                        badge.textContent = count > 99 ? '99+' : String(count);
                        badge.classList.toggle('hidden', count === 0);
                    })
                    .catch(function () {});
            };
            updateBadge();
            window.setInterval(updateBadge, 300000);
        }
    });
}());
