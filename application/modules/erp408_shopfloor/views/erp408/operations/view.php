<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Операция <?php echo (int) $operation['op_no']; ?> · <?php echo HTML::chars($operation['name']); ?>
            </h1>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Заказ
                <a href="/production/orders/view/<?php echo (int) $operation['order_id']; ?>" class="text-blue-600 hover:text-blue-800">
                    <?php echo HTML::chars($operation['order_number']); ?>
                </a>
                · <?php echo HTML::chars($operation['order_title']); ?>
                · <span class="px-2 py-0.5 rounded-full text-xs <?php echo $operation['status_class']; ?>"><?php echo HTML::chars($operation['status_label']); ?></span>
            </div>
        </div>

        <div class="flex items-center gap-2 text-sm">
            <a href="/shopfloor/operations" class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-list mr-1"></i> Все операции
            </a>
            <?php if ($can_terminal): ?>
                <a href="/shopfloor/terminal?center_id=<?php echo (int) $operation['work_center_id']; ?>"
                   class="px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900">
                    <i class="fas fa-tablet-alt mr-1"></i> Терминал
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php echo View::factory('erp408/partials/flash')->set('messages', $messages); ?>

    <?php $link = '/shopfloor/operations/act/'; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">План</div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo number_format($operation['qty_planned'], 0, '.', ' '); ?></div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Годных</div>
                        <div class="text-xl font-semibold text-green-700 dark:text-green-400"><?php echo number_format($operation['qty_ok'], 0, '.', ' '); ?></div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Брак</div>
                        <div class="text-xl font-semibold <?php echo $operation['qty_scrap'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>">
                            <?php echo number_format($operation['qty_scrap'], 0, '.', ' '); ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Осталось</div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo number_format($operation['remaining'], 0, '.', ' '); ?></div>
                    </div>
                </div>

                <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full mt-4">
                    <div class="h-2 rounded-full <?php echo $operation['progress'] >= 100 ? 'bg-green-500' : 'bg-blue-500'; ?>"
                         data-progress="<?php echo min(100, (int) $operation['progress']); ?>"></div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mt-5 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Рабочий центр</div>
                        <div class="text-gray-900 dark:text-gray-100">
                            <?php echo $operation['work_center_label'] ? HTML::chars($operation['work_center_label']) : '— не назначен'; ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">День плана</div>
                        <div class="text-gray-900 dark:text-gray-100"><?php echo HTML::chars((string) $operation['planned_day']); ?></div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Время работы</div>
                        <div class="text-gray-900 dark:text-gray-100"><?php echo number_format($operation['worked_hours'], 2, '.', ' '); ?> ч</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Простой</div>
                        <div class="<?php echo $operation['is_downtime'] ? 'text-orange-600 font-semibold' : 'text-gray-900 dark:text-gray-100'; ?>">
                            <?php echo number_format($operation['downtime_hours'], 2, '.', ' '); ?> ч
                            <?php if ($operation['is_downtime']): ?>
                                <div class="text-xs">идёт с <?php echo HTML::chars(substr((string) $operation['downtime_started_at'], 11, 5)); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($operation['description']): ?>
                    <div class="mt-4 text-sm text-gray-600 dark:text-gray-300"><?php echo nl2br(HTML::chars($operation['description'])); ?></div>
                <?php endif; ?>

                <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    Источник: <?php echo HTML::chars($operation['source_label']); ?>
                    <?php if ($operation['norm_cost'] > 0): ?> · нормативная стоимость <?php echo number_format($operation['norm_cost'], 2, '.', ' '); ?><?php endif; ?>
                    <?php if ($operation['actual_start']): ?> · начало <?php echo HTML::chars($operation['actual_start']); ?><?php endif; ?>
                    <?php if ($operation['actual_end']): ?> · окончание <?php echo HTML::chars($operation['actual_end']); ?><?php endif; ?>
                </div>
            </div>

            <?php if ($can_terminal AND $operation['is_open']): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-3">Работа в цехе</h2>

                    <div class="flex flex-wrap gap-2 mb-5">
                        <?php if (isset($operation['actions']['start'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>start/<?php echo (int) $operation['id']; ?>">
                                <button type="submit" class="px-4 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">
                                    <i class="fas fa-play mr-1"></i> Начать
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (isset($operation['actions']['pause'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>pause/<?php echo (int) $operation['id']; ?>">
                                <button type="submit" class="px-4 py-2 rounded-md bg-yellow-500 text-white hover:bg-yellow-600">
                                    <i class="fas fa-pause mr-1"></i> Пауза
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (isset($operation['actions']['resume'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>resume/<?php echo (int) $operation['id']; ?>">
                                <button type="submit" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                    <i class="fas fa-play-circle mr-1"></i> Продолжить
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (isset($operation['actions']['downtime_stop'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>downtime_stop/<?php echo (int) $operation['id']; ?>">
                                <button type="submit" class="px-4 py-2 rounded-md bg-orange-500 text-white hover:bg-orange-600">
                                    <i class="fas fa-stop-circle mr-1"></i> Закончить простой
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php if (isset($operation['actions']['output'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>output/<?php echo (int) $operation['id']; ?>" class="border border-gray-200 dark:border-gray-700 rounded-md p-4">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Выработка</div>
                                <div class="flex gap-2 mb-2">
                                    <input type="number" step="0.001" min="0" name="qty_ok" placeholder="годных"
                                           class="w-1/2 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                    <input type="number" step="0.001" min="0" name="qty_scrap" placeholder="брак"
                                           class="w-1/2 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                </div>
                                <input type="text" name="comment" placeholder="комментарий"
                                       class="w-full px-3 py-2 mb-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                <button type="submit" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                    <i class="fas fa-plus mr-1"></i> Записать
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (isset($operation['actions']['downtime_start'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>downtime_start/<?php echo (int) $operation['id']; ?>" class="border border-gray-200 dark:border-gray-700 rounded-md p-4">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Начать простой</div>
                                <select name="reason" class="w-full px-3 py-2 mb-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                    <?php foreach ($downtime_reasons as $key => $label): ?>
                                        <option value="<?php echo HTML::chars($key); ?>"><?php echo HTML::chars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="comment" placeholder="что случилось"
                                       class="w-full px-3 py-2 mb-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                <button type="submit" class="px-4 py-2 rounded-md bg-orange-500 text-white hover:bg-orange-600">
                                    <i class="fas fa-tools mr-1"></i> Отметить простой
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (isset($operation['actions']['finish'])): ?>
                            <form autocomplete="off" method="post" action="<?php echo $link; ?>finish/<?php echo (int) $operation['id']; ?>" class="border border-gray-200 dark:border-gray-700 rounded-md p-4">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Завершить операцию</div>
                                <div class="flex gap-2 mb-2">
                                    <input type="number" step="0.001" min="0" name="qty_ok" value="<?php echo (float) $operation['remaining']; ?>"
                                           class="w-1/2 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                    <input type="number" step="0.001" min="0" name="qty_scrap" placeholder="брак"
                                           class="w-1/2 px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Количество годных по умолчанию — остаток плана.</p>
                                <button type="submit" class="px-4 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">
                                    <i class="fas fa-check mr-1"></i> Завершить
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Журнал отметок</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Кто и когда нажимал кнопки в терминале — записи не правятся и не удаляются.</p>
                </div>

                <?php if (empty($logs)): ?>
                    <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">Отметок нет.</p>
                <?php else: ?>
                    <?php
                    $log_rows=array();foreach($logs as $log){$details=HTML::chars($log['label']).(!empty($log['comment'])?'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($log['comment']).'</div>':'');$log_rows[]=array(HTML::chars((string)$log['at']),HTML::chars($log['kind_label']),$log['user_id']?'#'.(int)$log['user_id']:'—',$details);}
                    echo Component::render('table',array('caption'=>'Неизменяемый журнал отметок операции','columns'=>array(array('label'=>'Когда','nowrap'=>TRUE),array('label'=>'Отметка'),array('label'=>'Кто','priority'=>3),array('label'=>'Детали')),'rows'=>$log_rows));
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-6">
            <?php if ($can_manage): ?>
                <?php if (isset($operation['actions']['assign']) OR $operation['is_open']): ?>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                        <h2 class="font-semibold text-gray-900 dark:text-white mb-3">Назначение</h2>

                        <form autocomplete="off" method="post" action="<?php echo $link; ?>assign/<?php echo (int) $operation['id']; ?>" class="space-y-3 text-sm">
                            <div>
                                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Рабочий центр</label>
                                <select name="work_center_id" class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                    <?php foreach ($centers as $center): ?>
                                        <option value="<?php echo (int) $center['id']; ?>" <?php echo (int) $operation['work_center_id'] === (int) $center['id'] ? 'selected' : ''; ?>>
                                            <?php echo HTML::chars($center['code'].' · '.$center['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Смена</label>
                                <select name="shift_id" class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                    <option value="">— не менять —</option>
                                    <?php foreach ($shifts as $shift): ?>
                                        <option value="<?php echo (int) $shift['id']; ?>" <?php echo (int) $operation['shift_id'] === (int) $shift['id'] ? 'selected' : ''; ?>>
                                            <?php echo HTML::chars($shift['code'].' · '.$shift['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">День</label>
                                <input type="date" name="planned_day" value="<?php echo HTML::chars((string) $operation['planned_day']); ?>"
                                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                            </div>
                            <button type="submit" class="w-full px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                <i class="fas fa-calendar-check mr-1"></i> Назначить
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-3">Правка операции</h2>
                    <a href="/shopfloor/operations/edit/<?php echo (int) $operation['id']; ?>"
                       class="block text-center px-4 py-2 mb-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm">
                        <i class="fas fa-pen mr-1"></i> Изменить план и нормы
                    </a>

                    <?php if (isset($operation['actions']['cancel'])): ?>
                        <form autocomplete="off" method="post" action="<?php echo $link; ?>cancel/<?php echo (int) $operation['id']; ?>" class="mb-2">
                            <input type="text" name="reason" placeholder="причина отмены"
                                   class="w-full px-3 py-2 mb-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm">
                            <button type="submit" class="w-full px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700 text-sm">
                                <i class="fas fa-ban mr-1"></i> Отменить операцию
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (isset($operation['actions']['delete'])): ?>
                        <form autocomplete="off" method="post" action="<?php echo $link; ?>delete/<?php echo (int) $operation['id']; ?>"
                              data-confirm="Удалить операцию из маршрута?">
                            <button type="submit" class="w-full px-4 py-2 rounded-md border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300 text-sm">
                                <i class="fas fa-trash mr-1"></i> Удалить (ошибка мастера)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
