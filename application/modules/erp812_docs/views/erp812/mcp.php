<?php
// Кнопка шапки — как на остальных страницах сервиса
$button_secondary = 'inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
?>
<div class="space-y-6">
    <?php echo Component::render('page_header', array(
        'crumbs'       => array(array('Документация', '/docs'), array('Model Context Protocol (MCP)', NULL)),
        'title'        => 'MCP: инструменты для AI-агентов',
        'lead'         => 'Каталог инструментов Model Context Protocol и спецификация для подключения агентов.',
        'meta'         => count($tools).' инструментов',
        'actions_html' => '<a href="/erp812_api/mcp" target="_blank" rel="noopener" class="'.$button_secondary.'"><i class="fas fa-code" aria-hidden="true"></i> JSON-схема (API)</a>',
    )); ?>


    <!-- Dual Mode Switcher Banner -->
    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <span class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg shrink-0">
                    <i class="fas fa-robot"></i>
                </span>
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Model Context Protocol (MCP) в PromERP</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-2xl">
                        PromERP предоставляет двусторонний интерфейс: визуальный интерактивный справочник для аналитиков и строгий JSON-RPC протокол для автономных LLM-агентов (Claude Desktop, Cursor, Custom Agents).
                    </p>
                </div>
            </div>

            <!-- Tab Switcher -->
            <div class="flex p-1 bg-gray-100 dark:bg-gray-700 rounded-lg shrink-0">
                <button type="button" id="tab-btn-human" data-call="switchView" data-call-args='["human"]' class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all bg-blue-600 text-white shadow-sm">
                    <i class="fas fa-user-gear mr-1"></i> Для человека
                </button>
                <button type="button" id="tab-btn-machine" data-call="switchView" data-call-args='["machine"]' class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-all">
                    <i class="fas fa-microchip mr-1"></i> Для машины (AI)
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: Для человека (Интерактивный каталог с поиском и визуализацией)     -->
    <!-- ========================================================================= -->
    <div id="view-human" class="space-y-6">
        <!-- Filter & Search Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="relative flex-1 max-w-md">
                <i class="fas fa-search absolute left-3.5 top-3 text-gray-400 text-sm"></i>
                <input type="text" 
                       id="mcp-filter-input" 
                       placeholder="Поиск инструмента по названию, модулю или действию..." 
                       class="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-gray-500 dark:text-gray-400">Модулей с поддержкой MCP:</span>
                <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 font-bold text-gray-800 dark:text-gray-200"><?= count($modules_with_tools) ?></span>
            </div>
        </div>

        <!-- Tools Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5" id="mcp-tools-container">
            <?php foreach ($tools as $tool): ?>
            <div class="mcp-tool-card p-5 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm hover:border-blue-400 dark:hover:border-blue-500 transition-all flex flex-col justify-between space-y-4"
                 data-search="<?= strtolower(htmlspecialchars($tool['name'] . ' ' . $tool['module_name'] . ' ' . ($tool['description'] ?? ''))) ?>">
                <div>
                    <!-- Tool Header -->
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-bold shrink-0">
                                <i class="fas fa-bolt"></i>
                            </span>
                            <div>
                                <h3 class="font-mono font-bold text-gray-900 dark:text-white text-sm">
                                    <?= htmlspecialchars($tool['name']) ?>
                                </h3>
                                <a href="/docs/modules/<?= $tool['module_name'] ?>" class="text-[11px] text-blue-600 dark:text-blue-400 hover:underline">
                                    <?= htmlspecialchars($tool['module_title']) ?>
                                </a>
                            </div>
                        </div>
                        <?php if ( ! empty($tool['mutating'])): ?>
                            <span class="px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 text-[10px] font-bold">
                                MUTATING
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300 text-[10px] font-bold">
                                READONLY
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Tool Description -->
                    <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed mb-3">
                        <?= htmlspecialchars($tool['description'] ?? 'Назначение инструмента не описано.') ?>
                    </p>

                    <!-- Parameters Schema -->
                    <?php if ( ! empty($tool['parameters'])): ?>
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                            <span>Схема входных параметров (JSON-Schema):</span>
                            <button type="button" data-call="copySnippet" data-call-args="<?php echo HTML::chars(json_encode(array(json_encode($tool['parameters'], JSON_UNESCAPED_UNICODE)), JSON_UNESCAPED_UNICODE)); ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                Копировать схему
                            </button>
                        </div>
                        <pre class="p-3 rounded-lg bg-gray-950 text-emerald-400 font-mono text-[11px] overflow-x-auto max-h-40 leading-relaxed"><?= json_encode($tool['parameters'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Example JSON-RPC Request -->
                <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60">
                    <details class="group text-xs">
                        <summary class="cursor-pointer font-semibold text-blue-600 dark:text-blue-400 hover:underline flex items-center justify-between">
                            <span>Пример JSON-RPC запроса</span>
                            <i class="fas fa-chevron-down group-open:rotate-180 transition-transform"></i>
                        </summary>
                        <div class="mt-2 p-3 rounded-lg bg-gray-950 text-gray-200 font-mono text-[11px] overflow-x-auto">
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "<?= htmlspecialchars($tool['name']) ?>",
    "arguments": {}
  },
  "id": 1
}
                        </div>
                    </details>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: Для машины (Машинная спецификация, манифесты и интеграция)         -->
    <!-- ========================================================================= -->
    <div id="view-machine" class="hidden space-y-6">
        <!-- Machine Endpoints Summary -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-network-wired text-blue-500"></i>
                Спецификация интеграции MCP для AI-агентов
            </h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <caption class="sr-only">Спецификация интеграции MCP для AI-агентов: параметры, значения, описание</caption>
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 uppercase text-[10px]">
                            <th scope="col" class="py-2.5 pr-4 text-left font-bold">Параметр</th>
                            <th scope="col" class="py-2.5 pr-4 text-left font-bold">Значение</th>
                            <th scope="col" class="py-2.5 text-left font-bold">Описание</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-300 font-mono text-[11px]">
                        <tr>
                            <td class="py-2.5 pr-4 font-bold text-blue-600 dark:text-blue-400">Протокол</td>
                            <td class="py-2.5 pr-4">JSON-RPC 2.0</td>
                            <td class="py-2.5 font-sans text-gray-500 dark:text-gray-400">Model Context Protocol Specification</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 pr-4 font-bold text-blue-600 dark:text-blue-400">RPC Endpoint</td>
                            <td class="py-2.5 pr-4"><code>/mcp/api/jsonrpc</code> или <code>/mcp</code></td>
                            <td class="py-2.5 font-sans text-gray-500 dark:text-gray-400">Единая точка входа для вызова инструментов</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 pr-4 font-bold text-blue-600 dark:text-blue-400">Tools Manifest</td>
                            <td class="py-2.5 pr-4"><a href="/erp812_api/mcp" target="_blank" class="text-blue-500 hover:underline">/erp812_api/mcp</a></td>
                            <td class="py-2.5 font-sans text-gray-500 dark:text-gray-400">Машинно-читаемый каталог всех инструментов в формате JSON</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 pr-4 font-bold text-blue-600 dark:text-blue-400">Авторизация</td>
                            <td class="py-2.5 pr-4">Bearer Token / Session Cookie</td>
                            <td class="py-2.5 font-sans text-gray-500 dark:text-gray-400">Передаётся в заголовке <code>Authorization: Bearer &lt;token&gt;</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Claude Desktop / Cursor Config Sample -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-file-code text-blue-500"></i>
                    Конфигурация для Claude Desktop / Cursor (claude_desktop_config.json)
                </h4>
                <button type="button" data-call="copyConfig" class="text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline">
                    Копировать config
                </button>
            </div>
            <pre id="mcp-config-snippet" class="p-4 rounded-lg bg-gray-950 text-emerald-400 font-mono text-xs overflow-x-auto leading-relaxed">
{
  "mcpServers": {
    "promerp": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-fetch",
        "http://127.0.0.1:8080/mcp/api/jsonrpc"
      ],
      "env": {
        "PROMERP_AUTH_TOKEN": "mcp_token_here"
      }
    }
  }
}</pre>
        </div>

        <!-- Curl Example -->
        <div class="p-6 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-3">
            <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-terminal text-blue-500"></i>
                Пример прямого вызова через cURL
            </h4>
            <pre class="p-4 rounded-lg bg-gray-950 text-gray-200 font-mono text-xs overflow-x-auto leading-relaxed">
curl -X POST http://127.0.0.1:8080/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_MCP_TOKEN" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "warehouse.stock_balance",
      "arguments": {
        "warehouse_id": "WH-MAT"
      }
    },
    "id": "req-001"
  }'</pre>
        </div>
    </div>
</div>

<script>
function switchView(mode) {
    var vHuman = document.getElementById('view-human');
    var vMachine = document.getElementById('view-machine');
    var bHuman = document.getElementById('tab-btn-human');
    var bMachine = document.getElementById('tab-btn-machine');

    if (mode === 'human') {
        vHuman.classList.remove('hidden');
        vMachine.classList.add('hidden');
        bHuman.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all bg-blue-600 text-white shadow-sm';
        bMachine.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-all';
    } else {
        vHuman.classList.add('hidden');
        vMachine.classList.remove('hidden');
        bMachine.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all bg-blue-600 text-white shadow-sm';
        bHuman.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-all';
    }
}

function copySnippet(jsonStr, btn) {
    try {
        var formatted = JSON.stringify(JSON.parse(jsonStr), null, 2);
        navigator.clipboard.writeText(formatted).then(function() {
            var oldText = btn.textContent;
            btn.textContent = 'Скопировано!';
            setTimeout(function() { btn.textContent = oldText; }, 2000);
        });
    } catch(e) {
        navigator.clipboard.writeText(jsonStr);
    }
}

function copyConfig() {
    var snippet = document.getElementById('mcp-config-snippet');
    if (snippet) {
        navigator.clipboard.writeText(snippet.textContent.trim()).then(function() {
            alert('Конфигурация MCP скопирована в буфер обмена!');
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('mcp-filter-input');
    var cards = document.querySelectorAll('.mcp-tool-card');

    if (input) {
        input.addEventListener('input', function() {
            var query = input.value.toLowerCase().trim();
            cards.forEach(function(card) {
                var search = card.getAttribute('data-search') || '';
                if (!query || search.includes(query)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
});
</script>
