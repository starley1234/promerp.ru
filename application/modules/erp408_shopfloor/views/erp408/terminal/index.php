<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-tablet-alt mr-2 text-blue-600"></i> Терминал цеха
        </h1>
        <div class="text-sm text-gray-500 dark:text-gray-400">
            <?php echo $shift ? 'Смена '.HTML::chars($shift['code']).' · '.HTML::chars($shift['label']) : 'Смена не определена'; ?>
            · <?php echo HTML::chars($day); ?>
        </div>
    </div>

    <?php echo View::factory('erp408/partials/flash')->set('messages', $messages); ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <form autocomplete="off" method="get" action="/shopfloor/terminal" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 flex flex-wrap items-end gap-3 text-sm">
            <div class="flex-1 min-w-[12rem]">
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Рабочий центр</label>
                <select name="center_id" class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    <option value="0">Все задания</option>
                    <?php foreach ($centers as $center): ?>
                        <option value="<?php echo (int) $center['id']; ?>" <?php echo (int) $center_id === (int) $center['id'] ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($center['code'].' · '.$center['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">День</label>
                <input type="date" name="day" value="<?php echo HTML::chars($day); ?>"
                       class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>
            <button type="submit" class="px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900">
                <i class="fas fa-sync mr-1"></i> Обновить
            </button>
        </form>

        <form autocomplete="off" method="get" action="/shopfloor/terminal/find" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 text-sm">
            <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Код задания с места</label>
            <div class="flex gap-2">
                <input type="text" name="code" placeholder="ПО-2026-000001, ПО-2026-000001/30 или № операции"
                       class="flex-1 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <input type="hidden" name="center_id" value="<?php echo (int) $center_id; ?>">
                <button type="submit" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-barcode mr-1"></i> Найти
                </button>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Номер-операция ищется как «НОМЕР ЗАКАЗА/ОП».</p>
        </form>
    </div>

    <?php if (empty($tasks)): ?>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-10 text-center text-sm text-gray-500 dark:text-gray-400">
            Заданий нет: на выбранный день открытых операций не осталось.
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($tasks as $operation): ?>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden <?php echo $operation['is_overdue'] ? 'ring-2 ring-red-300 dark:ring-red-700' : ''; ?>">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between gap-2">
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">
                            <?php echo HTML::chars($operation['order_number']); ?> · оп. <?php echo (int) $operation['op_no']; ?>
                        </div>
                        <div class="font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($operation['name']); ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            <?php echo $operation['work_center_label'] ? HTML::chars($operation['work_center_label']) : 'центр не назначен'; ?>
                            · план <?php echo HTML::chars((string) $operation['planned_day']); ?>
                            <?php if ($operation['is_overdue']): ?>
                                <span class="text-red-600"> · просрочено</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="px-2 py-1 rounded-full text-xs whitespace-nowrap <?php echo $operation['status_class']; ?>">
                        <?php echo HTML::chars($operation['status_label']); ?>
                    </span>
                </div>

                <div class="px-5 py-4">
                    <div class="flex items-end justify-between text-sm mb-1">
                        <span class="text-gray-500 dark:text-gray-400">Осталось сделать</span>
                        <span class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($operation['remaining'], 0, '.', ' '); ?></span>
                    </div>
                    <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full mb-1">
                        <div class="h-2 rounded-full bg-green-500" data-progress="<?php echo min(100, (int) $operation['progress']); ?>"></div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        годных <?php echo number_format($operation['qty_ok'], 0, '.', ' '); ?> из <?php echo number_format($operation['qty_planned'], 0, '.', ' '); ?>
                        <?php if ($operation['qty_scrap'] > 0): ?> · брак <?php echo number_format($operation['qty_scrap'], 0, '.', ' '); ?><?php endif; ?>
                        <?php if ($operation['worked_hours'] > 0): ?> · работа <?php echo number_format($operation['worked_hours'], 2, '.', ' '); ?> ч<?php endif; ?>
                        <?php if ($operation['is_downtime']): ?>
                            <div class="text-orange-600 font-semibold">идёт простой: <?php echo HTML::chars((string) $operation['downtime_reason_label']); ?></div>
                        <?php endif; ?>
                    </div>

                    <?php $link = '/shopfloor/operations/act/'; ?>
                    <?php $back = '?back=terminal&center_id='.(int) $center_id; ?>

                    <div class="grid grid-cols-2 gap-2">
                        <?php if (isset($operation['actions']['start'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>start/<?php echo (int) $operation['id'].$back; ?>">
                                <button type="submit" class="w-full px-3 py-3 rounded-md bg-green-600 text-white font-semibold hover:bg-green-700">
                                    <i class="fas fa-play mr-1"></i> Начать
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (isset($operation['actions']['pause'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>pause/<?php echo (int) $operation['id'].$back; ?>">
                                <button type="submit" class="w-full px-3 py-3 rounded-md bg-yellow-500 text-white font-semibold hover:bg-yellow-600">
                                    <i class="fas fa-pause mr-1"></i> Пауза
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (isset($operation['actions']['resume'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>resume/<?php echo (int) $operation['id'].$back; ?>">
                                <button type="submit" class="w-full px-3 py-3 rounded-md bg-blue-600 text-white font-semibold hover:bg-blue-700">
                                    <i class="fas fa-play-circle mr-1"></i> Работаем
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (isset($operation['actions']['downtime_stop'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>downtime_stop/<?php echo (int) $operation['id'].$back; ?>">
                                <button type="submit" class="w-full px-3 py-3 rounded-md bg-orange-500 text-white font-semibold hover:bg-orange-600">
                                    <i class="fas fa-stop-circle mr-1"></i> Простой конец
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($operation['actions']['output']) OR isset($operation['actions']['finish'])): ?>
                        <form autocomplete="off" method="post" action="<?php echo $link; ?><?php echo isset($operation['actions']['finish']) ? 'finish' : 'output'; ?>/<?php echo (int) $operation['id'].$back; ?>" class="mt-3">
                            <div class="flex gap-2">
                                <input type="number" step="1" min="0" name="qty_ok" value="<?php echo (float) $operation['remaining']; ?>"
                                       class="w-1/2 px-3 py-2 text-lg rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                <input type="number" step="1" min="0" name="qty_scrap" placeholder="брак"
                                       class="w-1/2 px-3 py-2 text-lg rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                            </div>
                            <button type="submit" class="w-full mt-2 px-4 py-3 rounded-md bg-gray-900 text-white font-semibold hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900">
                                <i class="fas fa-check mr-1"></i> <?php echo isset($operation['actions']['finish']) ? 'Готово (закрыть)' : 'Записать выработку'; ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (isset($operation['actions']['downtime_start'])): ?>
                        <form autocomplete="off" method="post" action="<?php echo $link; ?>downtime_start/<?php echo (int) $operation['id'].$back; ?>" class="mt-3 flex gap-2">
                            <select name="reason" class="flex-1 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm">
                                <?php foreach ($downtime_reasons as $key => $label): ?>
                                    <option value="<?php echo HTML::chars($key); ?>"><?php echo HTML::chars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="px-3 py-2 rounded-md bg-orange-500 text-white hover:bg-orange-600 text-sm whitespace-nowrap">
                                <i class="fas fa-tools mr-1"></i> Простой
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="/shopfloor/operations/view/<?php echo (int) $operation['id']; ?>" class="block text-center text-xs text-blue-600 hover:text-blue-800 mt-3">
                        Карточка операции и журнал отметок
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
