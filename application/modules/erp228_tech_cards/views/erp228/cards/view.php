<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <?php echo View::factory('erp228/partials/flash')->set('messages', $messages); ?>

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($card->title); ?></h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo Model_Erp228_TechCard::$status_colors[$card->status]; ?>-100 text-<?php echo Model_Erp228_TechCard::$status_colors[$card->status]; ?>-800 dark:bg-<?php echo Model_Erp228_TechCard::$status_colors[$card->status]; ?>-900/30 dark:text-<?php echo Model_Erp228_TechCard::$status_colors[$card->status]; ?>-300">
                    <?php echo HTML::chars($card->status_label()); ?>
                </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                #<?php echo (int) $card->id; ?> · <?php echo HTML::chars($card->uuid); ?> ·
                <?php echo HTML::chars($card->production_label()); ?>
            </p>
            <?php if ($card->error_msg): ?>
                <p class="text-sm text-red-600 dark:text-red-400 mt-2">
                    <i class="fas fa-exclamation-circle mr-1"></i><?php echo HTML::chars($card->error_msg); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap gap-2">
            <form method="post" action="/tech_cards/generate">
                <input type="hidden" name="__html" value="1">
                <input type="hidden" name="id" value="<?php echo (int) $card->id; ?>">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                    <i class="fas fa-magic mr-2"></i>
                    <?php echo $document ? 'Перегенерировать' : 'Сгенерировать техпроцесс'; ?>
                </button>
            </form>
            <a href="/tech_cards/editor/<?php echo (int) $card->id; ?>"
               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-pen mr-2"></i> Правка
            </a>
            <form method="post" action="/tech_cards/delete"
                  data-confirm="Переместить техкарту в архив?">
                <input type="hidden" name="id" value="<?php echo (int) $card->id; ?>">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-red-600 dark:text-red-400 text-sm font-medium rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                    <i class="fas fa-archive mr-2"></i> В архив
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Операции техпроцесса</h2>
                    <?php if ($document): ?>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            <?php echo count($operations); ?> операций ·
                            <?php echo Model_Erp228_TechDocument::human_duration($document->duration_total()); ?> ·
                            <?php echo number_format($document->cost_total(), 2, ',', ' '); ?> ₽
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ( ! $document): ?>
                    <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">
                        Техпроцесс ещё не сформирован — нажмите «Сгенерировать техпроцесс».
                    </p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                    <caption class="sr-only">Таблица данных</caption>
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700/40 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    <th scope="col" class="px-5 py-3 w-14">#</th>
                                    <th scope="col" class="px-5 py-3">Операция</th>
                                    <th scope="col" class="px-5 py-3">Время</th>
                                    <th scope="col" class="px-5 py-3 text-right">Стоимость</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($operations as $i => $operation): ?>
                                    <tr>
                                        <td class="px-5 py-3 text-sm text-gray-400"><?php echo $i + 1; ?></td>
                                        <td class="px-5 py-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($operation->op_name); ?></div>
                                            <?php if ($operation->op_description): ?>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?php echo HTML::chars($operation->op_description); ?></div>
                                            <?php endif; ?>
                                            <?php if ($operation->params_inline()): ?>
                                                <div class="text-xs text-gray-400 mt-1"><?php echo HTML::chars($operation->params_inline()); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            <?php echo Model_Erp228_TechDocument::human_duration($operation->duration_sec); ?>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-900 dark:text-white text-right whitespace-nowrap">
                                            <?php echo number_format((float) $operation->cost_amount, 2, ',', ' '); ?> ₽
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($document AND $document->content_markdown): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Описание техпроцесса</h2>
                    </div>
                    <pre class="px-5 py-4 text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap break-words font-sans"><?php echo HTML::chars($document->content_markdown); ?></pre>
                </div>
            <?php endif; ?>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Исходный код изделия</h2>
                </div>
                <pre class="px-5 py-4 text-xs text-gray-700 dark:text-gray-200 overflow-x-auto"><?php echo HTML::chars($card->source_code); ?></pre>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Параметры</h2>
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Тип производства</dt>
                        <dd class="text-gray-900 dark:text-white text-right"><?php echo HTML::chars($card->production_label()); ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Тип объекта</dt>
                        <dd class="text-gray-900 dark:text-white text-right">
                            <?php echo HTML::chars(isset(Model_Erp228_TechCard::$target_types[$card->target_type]) ? Model_Erp228_TechCard::$target_types[$card->target_type] : $card->target_type); ?>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Создана</dt>
                        <dd class="text-gray-900 dark:text-white text-right"><?php echo HTML::chars($card->created_at); ?></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Изменена</dt>
                        <dd class="text-gray-900 dark:text-white text-right"><?php echo HTML::chars($card->updated_at); ?></dd>
                    </div>
                </dl>

                <?php if ($params = $card->decode_params()): ?>
                    <h3 class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mt-4 mb-2">Параметры производства</h3>
                    <ul class="text-sm space-y-1">
                        <?php foreach ($params as $key => $value): ?>
                            <li class="flex justify-between gap-3">
                                <span class="text-gray-500 dark:text-gray-400"><?php echo HTML::chars($key); ?></span>
                                <span class="text-gray-900 dark:text-white text-right"><?php echo HTML::chars(is_array($value) ? implode(', ', $value) : $value); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Версии техпроцесса</h2>
                <ul class="text-sm space-y-3">
                    <?php foreach ($versions as $version): ?>
                        <li class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-gray-900 dark:text-white">
                                    #<?php echo (int) $version->id; ?>
                                    <?php if ($version->is_actual): ?>
                                        <span class="ml-1 text-xs text-green-600 dark:text-green-400">актуальная</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo HTML::chars($version->llm_model ? $version->llm_model : 'модель не указана'); ?>
                                    · <?php echo (int) $version->gen_time_ms; ?> мс
                                </div>
                            </div>
                            <span class="text-xs text-gray-400 whitespace-nowrap"><?php echo HTML::chars($version->created_at); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (count($versions) === 0): ?>
                        <li class="text-gray-500 dark:text-gray-400">Версий пока нет.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
