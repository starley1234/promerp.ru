<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-address-book mr-2 text-blue-600"></i> Контрагенты
        </h1>
        <a href="/counterparties/edit"
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> Добавить контрагента
        </a>
    </div>

    <?php echo View::factory('erp301/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php echo Component::render('kpi', array('label' => 'Всего контрагентов', 'value' => (int) $stats['total'], 'unit' => 'шт.', 'state' => 'info', 'url' => '/counterparties/list')); ?>
        <?php echo Component::render('kpi', array('label' => 'Поставщики', 'value' => isset($stats['by_role']['supplier']) ? (int) $stats['by_role']['supplier'] : 0, 'unit' => 'шт.', 'state' => 'neutral', 'url' => '/counterparties/list?role=supplier')); ?>
        <?php echo Component::render('kpi', array('label' => 'Покупатели', 'value' => isset($stats['by_role']['customer']) ? (int) $stats['by_role']['customer'] : 0, 'unit' => 'шт.', 'state' => 'neutral', 'url' => '/counterparties/list?role=customer')); ?>
        <?php echo Component::render('kpi', array('label' => 'Действующие договоры', 'value' => (int) $stats['active_contracts'], 'unit' => 'шт.', 'state' => 'ok', 'url' => '/counterparties/contracts')); ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php
        Component::begin('card', array('title' => 'По статусам', 'icon' => 'fas fa-tags', 'flush' => TRUE));
        ?>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <?php foreach (Model_Erp301_Counterparty::$statuses as $code => $label): ?>
                <li>
                    <a class="flex items-center justify-between px-5 py-3 text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50"
                       href="/counterparties/list?status=<?php echo HTML::chars($code); ?>">
                        <span><?php echo HTML::chars($label); ?></span>
                        <span class="font-mono text-gray-900 dark:text-white"><?php echo isset($stats['by_status'][$code]) ? (int) $stats['by_status'][$code] : 0; ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        Component::end();

        $expiring_rows = array();

        foreach ($expiring as $contract)
        {
            $expiring_rows[] = array(
                '<a class="text-blue-600 hover:underline font-mono" href="/counterparties/view/'.(int) $contract->counterparty_id.'">№ '.HTML::chars($contract->number).'</a>'
                    .'<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($contract->title).'</div>',
                'до '.HTML::chars($contract->valid_to),
            );
        }

        Component::begin('card', array(
            'title'    => 'Договоры истекают в течение 30 дней ('.(int) $stats['expiring'].')',
            'icon'     => 'fas fa-clock',
            'flush'    => TRUE,
        ));
        echo Component::render('table', array(
            'caption'     => 'Договоры с истекающим сроком',
            'columns'     => array(
                array('label' => 'Договор'),
                array('label' => 'Срок', 'align' => 'right', 'nowrap' => TRUE),
            ),
            'rows'        => $expiring_rows,
            'empty_title' => 'Нет договоров с истекающим сроком',
        ));
        Component::end();
        ?>
    </div>

    <div class="mt-6 flex flex-wrap gap-3 text-sm">
        <a href="/counterparties/list" class="text-blue-600 hover:underline"><i class="fas fa-list mr-1"></i> Все контрагенты</a>
        <a href="/counterparties/contracts" class="text-blue-600 hover:underline"><i class="fas fa-file-signature mr-1"></i> Все договоры</a>
        <a href="/counterparties/api/search" class="text-blue-600 hover:underline"><i class="fas fa-code mr-1"></i> JSON API</a>
    </div>
</div>
