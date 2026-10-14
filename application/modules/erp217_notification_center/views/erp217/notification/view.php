<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="<?php echo Route::url('default', array('controller' => 'erp217_notification', 'action' => 'index')); ?>" class="p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-arrow-left text-gray-500"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Детали уведомления #<?php echo $item->id; ?></h1>
                <p class="text-sm text-gray-500">Создано: <?php echo date('d.m.Y H:i:s', strtotime($item->created_at)); ?></p>
            </div>
        </div>
        <div class="flex space-x-3">
            <?php if ($item->status == 'failed'): ?>
                <a href="<?php echo Route::url('default', array('controller' => 'erp217_notification', 'action' => 'retry', 'id' => $item->id)); ?>" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition-colors flex items-center">
                    <i class="fas fa-redo mr-2"></i> Переотправить
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Основная информация -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                    <h3 class="font-bold flex items-center">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i> Содержимое сообщения
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Тема / Заголовок</label>
                        <div class="text-lg font-medium text-gray-900 dark:text-white">
                            <?php echo HTML::chars($item->subject ?: 'Без темы'); ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase mb-1">Данные (Payload)</label>
                        <pre class="p-4 bg-gray-900 text-green-400 rounded-lg text-sm overflow-x-auto font-mono"><?php 
                            $payload = $item->get_payload();
                            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); 
                        ?></pre>
                    </div>
                </div>
            </div>

            <!-- Логи выполнения -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                    <h3 class="font-bold flex items-center">
                        <i class="fas fa-history mr-2 text-purple-500"></i> История попыток и логи
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead>
                            <tr class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/50">
                                <th scope="col" class="px-6 py-3">Время</th>
                                <th scope="col" class="px-6 py-3">Код</th>
                                <th scope="col" class="px-6 py-3">Ответ сервиса</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (count($logs) > 0): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="text-sm">
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                            <?php echo date('d.m.Y H:i:s', strtotime($log->created_at)); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 font-mono text-xs">
                                                <?php echo $log->response_code ?: 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                            <div class="max-w-md truncate" title="<?php echo HTML::chars($log->response_body); ?>">
                                                <?php echo HTML::chars($log->response_body); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-8 text-center text-gray-400 italic">Логи отсутствуют</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Боковая панель статуса -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-400 uppercase mb-4">Статус доставки</h3>
                
                <div class="flex items-center mb-6">
                    <?php 
                        $status_color = 'gray';
                        if ($item->status == 'pending') $status_color = 'amber';
                        if ($item->status == 'sent') $status_color = 'green';
                        if ($item->status == 'failed') $status_color = 'red';
                    ?>
                    <div class="w-3 h-3 rounded-full bg-<?php echo $status_color; ?>-500 mr-3"></div>
                    <span class="text-xl font-bold uppercase tracking-wider text-<?php echo $status_color; ?>-600">
                        <?php echo $item->status; ?>
                    </span>
                </div>

                <div class="space-y-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Канал:</span>
                        <span class="font-medium dark:text-white"><?php echo ucfirst($item->channel); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Получатель:</span>
                        <span class="font-medium dark:text-white"><?php echo $item->recipient->username; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Назначение:</span>
                        <span class="font-medium dark:text-white"><?php echo $item->destination; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Попыток:</span>
                        <span class="font-medium dark:text-white"><?php echo $item->attempts; ?> / 5</span>
                    </div>
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                        <div class="text-xs text-gray-400 mb-1">Последняя ошибка:</div>
                        <div class="text-sm text-red-500 italic">
                            <?php echo $item->last_error ?: 'Ошибок не зафиксировано'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-6 border border-blue-100 dark:border-blue-900/30">
                <h4 class="text-blue-800 dark:text-blue-300 font-bold mb-2 flex items-center">
                    <i class="fas fa-lightbulb mr-2"></i> Справка
                </h4>
                <p class="text-sm text-blue-700 dark:text-blue-400 leading-relaxed">
                    Если сообщение находится в статусе <b>failed</b>, система автоматически попробует отправить его еще раз (максимум 5 попыток). Вы также можете инициировать повторную отправку вручную.
                </p>
            </div>
        </div>
    </div>
</div>