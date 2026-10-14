<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php $company = $scenario->company(); ?>

<div class="mx-auto max-w-4xl space-y-6">

    <?php
    $actions = '<a href="'.Route::url('erp811_demo_index').'" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"><i class="fas fa-arrow-left" aria-hidden="true"></i> К стенду</a>';

    if ( ! empty($inside))
    {
        $actions .= '<a href="/analytics" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"><i class="fas fa-chart-pie" aria-hidden="true"></i> Открыть аналитику</a>';
    }

    echo Component::render('page_header', array(
        'crumbs'       => array(array('Демо-стенд', Route::url('erp811_demo_index')), array('Маршрут осмотра', NULL)),
        'title'        => 'Маршрут осмотра',
        'lead'         => $company['name'].': '.count($tour).' шагов по работающей системе. '
            .'Каждый шаг — экран, вопрос к экрану и то, что за ним стоит.',
        'actions_html' => $actions,
    ));
    ?>

    <?php if (empty($inside)): ?>
        <div role="note" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-900/30 dark:border-blue-700 px-4 py-3 text-sm text-blue-900 dark:text-blue-100">
            Ссылки ниже открываются в демо-режиме. Чтобы посмотреть их прямо сейчас, войдите гостем.
            <form method="post" action="<?php echo Route::url('erp811_demo_enter'); ?>" class="inline">
                <button type="submit" class="ml-2 underline hover:no-underline"
                        aria-label="Войти в демонстрационный режим гостем">Войти в демо</button>
            </form>
        </div>
    <?php endif; ?>

    <ol class="space-y-4" aria-label="Шаги маршрута осмотра">
        <?php foreach ($tour as $step): ?>
            <li class="rounded-lg border bg-white dark:bg-gray-800 dark:border-gray-700 p-5">
                <div class="flex items-start gap-4">
                    <div class="shrink-0 w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-semibold">
                        <?php echo (int) $step['no']; ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($step['title']); ?></h2>
                            <a href="<?php echo HTML::chars($step['url']); ?>"
                               class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                <i class="fas fa-up-right-from-square mr-1" aria-hidden="true"></i><?php echo HTML::chars($step['url']); ?>
                                <span class="sr-only">— открыть экран в демо-режиме</span>
                            </a>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-200 mt-2 leading-relaxed"><?php echo HTML::chars($step['what']); ?></p>
                        <?php if ( ! empty($step['ask'])): ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                <i class="fas fa-circle-question mr-1" aria-hidden="true"></i><?php echo HTML::chars($step['ask']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if ( ! empty($counts)): ?>
        <div class="rounded-lg border bg-white dark:bg-gray-800 dark:border-gray-700 p-5 mt-8">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-1">Данные, на которых всё это видно</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Цифры последней сборки стенда — с подписью и единицей измерения.</p>
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <?php foreach ((array) $counts as $key => $value): ?>
                    <?php if ( ! is_scalar($value)) { continue; } ?>
                    <div class="rounded border border-gray-100 dark:border-gray-700 px-3 py-2">
                        <dt class="text-gray-500 dark:text-gray-400 text-xs">
                            <?php echo HTML::chars(isset($labels[$key]) ? $labels[$key] : $key); ?>
                        </dt>
                        <dd class="font-semibold text-gray-900 dark:text-white tabular-nums">
                            <?php echo HTML::chars(number_format((float) $value, is_float($value) ? 2 : 0, ',', ' ')); ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    <?php endif; ?>
</div>
