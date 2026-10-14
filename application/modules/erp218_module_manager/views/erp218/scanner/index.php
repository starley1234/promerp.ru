<div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6"><?php echo __('Сканирование системы'); ?></h1>

    <div class="bg-white dark:bg-gray-800 shadow-md rounded-xl p-6 border border-gray-200 dark:border-gray-700">
        <div class="mb-4">
            <p class="text-gray-700 dark:text-gray-300 mb-2">
                <?php echo __('Путь для сканирования модулей:'); ?> <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-sm font-mono"><?php echo HTML::chars($scan_path); ?></code>
            </p>
            <p class="text-gray-700 dark:text-gray-300">
                <?php echo __('Последнее сканирование:'); ?> 
                <?php if ($last_scan): ?>
                    <span class="font-medium"><?php echo date('d.m.Y H:i', strtotime($last_scan)); ?></span>
                <?php else: ?>
                    <span class="font-medium"><?php echo __('Никогда'); ?></span>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <button id="runScannerBtn" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <i class="fas fa-sync-alt mr-3"></i>
                <?php echo __('Запустить сканирование'); ?>
            </button>
            <button id="runBatchAnalyzeBtn" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md shadow-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <i class="fas fa-brain mr-3"></i>
                <?php echo __('Запустить LLM анализ для всех'); ?>
            </button>
        </div>

        <div id="scanResult" class="hidden p-4 rounded-md text-sm" role="alert">
            <p class="font-medium mb-1" id="scanResultMessage"></p>
            <ul class="list-disc list-inside" id="scanResultDetails"></ul>
        </div>
        
        <div id="analyzeResult" class="hidden p-4 rounded-md text-sm" role="alert">
            <p class="font-medium mb-1" id="analyzeResultMessage"></p>
            <ul class="list-disc list-inside" id="analyzeResultDetails"></ul>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const scanResult = $('#scanResult');
    const scanResultMessage = $('#scanResultMessage');
    const scanResultDetails = $('#scanResultDetails');

    const analyzeResult = $('#analyzeResult');
    const analyzeResultMessage = $('#analyzeResultMessage');
    const analyzeResultDetails = $('#analyzeResultDetails');

    function showMessage(element, message, type, details = []) {
        element.removeClass('hidden')
               .removeClass('bg-green-100 bg-red-100 bg-blue-100 dark:bg-green-900/30 dark:bg-red-900/30 dark:bg-blue-900/30')
               .addClass(type === 'success' ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300');
        element.find('p').text(message);
        element.find('ul').empty();
        details.forEach(detail => {
            element.find('ul').append(`<li>${detail}</li>`);
        });
    }

    $('#runScannerBtn').on('click', function() {
        $(this).prop('disabled', true).find('i').removeClass('fa-sync-alt').addClass('fa-spinner fa-spin');
        scanResult.addClass('hidden');

        $.ajax({
            url: '/erp218_scanner/run',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    showMessage(scanResult, response.message, 'success', [
                        `<?php echo __('Найдено новых модулей:'); ?> ${response.found}`,
                        `<?php echo __('Добавлено в реестр:'); ?> ${response.added}`
                    ]);
                } else {
                    showMessage(scanResult, response.message || '<?php echo __('Произошла ошибка при сканировании.'); ?>', 'error');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '<?php echo __('Неизвестная ошибка сервера.'); ?>';
                showMessage(scanResult, errorMsg, 'error');
            },
            complete: function() {
                $('#runScannerBtn').prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-sync-alt');
            }
        });
    });

    $('#runBatchAnalyzeBtn').on('click', function() {
        $(this).prop('disabled', true).find('i').removeClass('fa-brain').addClass('fa-spinner fa-spin');
        analyzeResult.addClass('hidden');

        $.ajax({
            url: '/erp218_scanner/batch_analyze',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'ok') {
                    showMessage(analyzeResult, `<?php echo __('LLM анализ завершен.'); ?>`, 'success', [
                        `<?php echo __('Обработано модулей:'); ?> ${response.processed}`
                    ]);
                } else {
                    showMessage(analyzeResult, response.message || '<?php echo __('Произошла ошибка при LLM анализе.'); ?>', 'error');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '<?php echo __('Неизвестная ошибка сервера.'); ?>';
                showMessage(analyzeResult, errorMsg, 'error');
            },
            complete: function() {
                $('#runBatchAnalyzeBtn').prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-brain');
            }
        });
    });
});
</script>