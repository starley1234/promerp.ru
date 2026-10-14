<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-clipboard-check mr-2 text-green-600"></i> Контроль <?php echo HTML::chars($inspection->number); ?>
                <span class="ml-2 px-2 py-0.5 rounded text-xs align-middle <?php echo $inspection->status_class(); ?>"><?php echo HTML::chars($inspection->status_label()); ?></span>
                <?php if ($inspection->decision): ?>
                    <span class="ml-1 px-2 py-0.5 rounded text-xs align-middle <?php echo $inspection->decision_class(); ?>"><?php echo HTML::chars($inspection->decision_label()); ?></span>
                <?php endif; ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php echo HTML::chars($inspection->kind_label()); ?> ·
                <?php echo HTML::chars($inspection->source_label()); ?>
                <?php if ($inspection->source_ref): ?> (<?php echo HTML::chars($inspection->source_ref); ?>)<?php endif; ?> ·
                заведён <?php echo HTML::chars(substr((string) $inspection->created_at, 0, 16)); ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <?php if ($can_inspect AND $inspection->is_editable()): ?>
                <a href="/quality/inspections/edit/<?php echo (int) $inspection->id; ?>"
                   class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-pen mr-1"></i> Правка
                </a>
            <?php endif; ?>

            <?php if ($can_inspect AND (string) $inspection->status === 'draft'): ?>
                <form method="post" action="/quality/inspections/act/start/<?php echo (int) $inspection->id; ?>">
                    <button type="submit" class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                        <i class="fas fa-play mr-1"></i> Начать проверку
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($can_decide AND (float) $inspection->defect_qty <= 0 AND ! in_array((string) $inspection->status, array('closed', 'cancelled'), TRUE)): ?>
                <form method="post" action="/quality/inspections/act/pass/<?php echo (int) $inspection->id; ?>">
                    <button type="submit" class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700">
                        <i class="fas fa-check mr-1"></i> Партия годная
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($can_decide AND (string) $inspection->status === 'decided'): ?>
                <form method="post" action="/quality/inspections/act/close/<?php echo (int) $inspection->id; ?>">
                    <button type="submit" class="px-3 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-900">
                        <i class="fas fa-lock mr-1"></i> Закрыть
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($can_decide AND in_array((string) $inspection->status, array('draft', 'registered'), TRUE)): ?>
                <form method="post" action="/quality/inspections/act/cancel/<?php echo (int) $inspection->id; ?>">
                    <button type="submit" class="px-3 py-2 text-red-600 text-sm rounded-md hover:bg-red-50 dark:hover:bg-red-900/30">
                        <i class="fas fa-times mr-1"></i> Отменить
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php echo View::factory('erp405/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 lg:col-span-2">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-4">Партия</h2>
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Позиция</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($inspection->item_name !== '' ? $inspection->item_name : '—'); ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Количество</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo (float) $inspection->qty; ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Брак</dt>
                    <dd class="<?php echo ((float) $inspection->defect_qty > 0) ? 'text-red-600 font-medium' : 'text-gray-900 dark:text-white'; ?>">
                        <?php echo (float) $inspection->defect_qty; ?>
                        <?php if ((float) $inspection->qty > 0): ?>
                            <span class="text-xs text-gray-500 dark:text-gray-400">(<?php echo $inspection->defect_share(); ?> %)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Годных</dt>
                    <dd class="text-green-600 font-medium"><?php echo $inspection->good_qty(); ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Склад партии</dt>
                    <dd class="text-gray-900 dark:text-white">
                        <?php echo HTML::chars(isset($warehouses[(int) $inspection->warehouse_id]) ? $warehouses[(int) $inspection->warehouse_id] : '—'); ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">На карантине</dt>
                    <dd class="text-blue-600 font-medium"><?php echo (float) $inspection->quarantine_qty; ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Итог</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($inspection->result_label()); ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Решение</dt>
                    <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($inspection->decision_label()); ?></dd>
                </div>
            </dl>

            <?php if ((string) $inspection->note !== ''): ?>
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300"><?php echo nl2br(HTML::chars((string) $inspection->note)); ?></p>
            <?php endif; ?>

            <?php if ((string) $inspection->decision_comment !== ''): ?>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Комментарий к решению:</span>
                    <?php echo nl2br(HTML::chars((string) $inspection->decision_comment)); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-4">Движения по партии</h2>
            <?php if (count($decisions) === 0): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">Решений и движений пока нет.</p>
            <?php else: ?>
                <ul class="space-y-3 text-sm">
                    <?php foreach ($decisions as $decision): ?>
                        <li class="border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                            <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($decision->kind_label()); ?>: <?php echo (float) $decision->qty; ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo HTML::chars(substr((string) $decision->created_at, 0, 16)); ?>
                                <?php if ($decision->movement_ref): ?> · движение #<?php echo HTML::chars($decision->movement_ref); ?><?php endif; ?>
                            </div>
                            <?php if ((string) $decision->comment !== ''): ?>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars((string) $decision->comment); ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-white">Несоответствия</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Дефект сам ставит итог «брак»: контролёру не нужно помнить о второй кнопке.</p>
            </div>

            <?php if (count($defects) === 0): ?>
                <div class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Дефектов нет.</div>
            <?php else: ?>
                <?php
                $defect_rows = array();
                foreach ($defects as $defect)
                {
                    $code = '<span class="font-mono text-gray-900 dark:text-white">'.HTML::chars($defect->code!==''?$defect->code:'—').'</span><br><span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($defect->severity_label()).'</span>';
                    $cause = '<span class="rounded px-2 py-0.5 text-xs '.$defect->cause_class().'">'.HTML::chars($defect->cause_label()).'</span>';
                    $action = '';
                    if ($can_inspect AND $inspection->is_editable()) $action='<form method="post" action="/quality/inspections/defect/remove/'.(int)$defect->id.'"><button type="submit" class="text-xs text-red-600 hover:text-red-800">убрать</button></form>';
                    $defect_rows[] = array($code,'<span class="font-medium text-red-600">'.number_format((float)$defect->qty,3,'.',' ').'</span>',$cause,$action);
                }
                echo Component::render('table',array('caption'=>'Несоответствия контроля качества','columns'=>array(array('label'=>'Код'),array('label'=>'Количество','align'=>'right'),array('label'=>'Причина'),array('label'=>'Действия','align'=>'right')),'rows'=>$defect_rows,'row_header'=>TRUE));
                ?>
            <?php endif; ?>

            <?php if ($can_inspect AND $inspection->is_editable()): ?>
                <form method="post" action="/quality/inspections/defect/add/<?php echo (int) $inspection->id; ?>"
                      class="border-t border-gray-200 dark:border-gray-700 p-4 grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Код дефекта</label>
                        <select name="defect_code_id" class="w-full border rounded-md px-2 py-2 dark:bg-gray-700 dark:text-gray-100">
                            <option value="">без кода</option>
                            <?php foreach ($codes as $id => $label): ?>
                                <option value="<?php echo (int) $id; ?>"><?php echo HTML::chars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Кол-во</label>
                        <input type="number" step="0.001" min="0.001" name="qty" required
                               class="w-full border rounded-md px-2 py-2 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Причина</label>
                        <select name="cause" class="w-full border rounded-md px-2 py-2 dark:bg-gray-700 dark:text-gray-100">
                            <?php foreach (Model_Erp405_Defect::$causes as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo HTML::chars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            <i class="fas fa-plus mr-1"></i> Дефект
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="space-y-4">

            <?php if ($can_decide AND (float) $inspection->defect_qty > 0): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Карантин</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Физически изолировать партию на склад карантина: пока она там, контроль не закрывается.
                    </p>

                    <?php if (empty($quarantine_warehouses)): ?>
                        <p class="text-xs text-amber-600">Склада типа «карантин» нет — заведите его в модуле склада (erp401).</p>
                    <?php else: ?>
                        <form method="post" action="/quality/inspections/quarantine/<?php echo (int) $inspection->id; ?>" class="grid grid-cols-3 gap-2 text-sm">
                            <div class="col-span-2">
                                <select name="warehouse_id" class="w-full border rounded-md px-2 py-2 dark:bg-gray-700 dark:text-gray-100">
                                    <?php foreach ($quarantine_warehouses as $id => $name): ?>
                                        <option value="<?php echo (int) $id; ?>"><?php echo HTML::chars($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="number" step="0.001" min="0.001" name="qty"
                                   value="<?php echo max(0, (float) $inspection->defect_qty - (float) $inspection->quarantine_qty); ?>"
                                   class="border rounded-md px-2 py-2 dark:bg-gray-700 dark:text-gray-100">
                            <button type="submit" class="col-span-3 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Изолировать
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($inspection->is_quarantined()): ?>
                        <form method="post" action="/quality/inspections/release/<?php echo (int) $inspection->id; ?>" class="grid grid-cols-3 gap-2 text-sm mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="col-span-2">
                                <select name="warehouse_id" class="w-full border rounded-md px-2 py-2 dark:bg-gray-700 dark:text-gray-100">
                                    <option value="">вернуть на склад партии</option>
                                    <?php foreach ($warehouses as $id => $name): ?>
                                        <option value="<?php echo (int) $id; ?>" <?php echo ((int) $inspection->warehouse_id === (int) $id) ? 'selected' : ''; ?>>
                                            <?php echo HTML::chars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="number" step="0.001" min="0.001" name="qty" value="<?php echo (float) $inspection->quarantine_qty; ?>"
                                   class="border rounded-md px-2 py-2 dark:bg-gray-700 dark:text-gray-100">
                            <button type="submit" class="col-span-3 px-3 py-2 bg-white dark:bg-gray-700 border border-blue-300 text-blue-700 dark:text-blue-300 rounded-md hover:bg-blue-50 dark:hover:bg-gray-600">
                                Выпустить из карантина
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($can_decide AND (string) $inspection->result === 'failed'): ?>
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Решение по браку</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                        Решение исполняется складом: «в утиль» — перемещение на склад утиля, а не пометка в таблице.
                    </p>

                    <form method="post" action="/quality/inspections/decide/<?php echo (int) $inspection->id; ?>" class="space-y-3 text-sm">
                        <div>
                            <select name="decision" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                                <?php foreach (Model_Erp405_Inspection::$decisions as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ((string) $inspection->decision === $key) ? 'selected' : ''; ?>>
                                        <?php echo HTML::chars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Склад утиля (для решения «в утиль»)</label>
                            <select name="warehouse_id" class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100">
                                <option value="">первый склад типа «утиль»</option>
                                <?php foreach ($scrap_warehouses as $id => $name): ?>
                                    <option value="<?php echo (int) $id; ?>"><?php echo HTML::chars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <textarea name="comment" rows="2" placeholder="Комментарий к решению"
                                      class="w-full border rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100"><?php echo HTML::chars((string) $inspection->decision_comment); ?></textarea>
                        </div>

                        <button type="submit" class="w-full px-3 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-900">
                            Принять решение
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
