<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Прайс продажи: базовая цена позиции и договорные цены клиентов.
 *
 * Цена не хранится в карточке товара — она история: у каждой записи есть
 * источник (прайс, заказ, отгрузка, счёт) и дата. Экран показывает, откуда
 * возьмётся цена при добавлении позиции, и позволяет записать новую.
 */
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-tags mr-2 text-blue-600 dark:text-blue-400" aria-hidden="true"></i> Прайс продажи
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Записей <?php echo (int) $stats['total']; ?>: базовых <?php echo (int) $stats['base']; ?>,
                договорных <?php echo (int) $stats['contract']; ?>.
                <?php if ($customer_id): ?>Показаны цены для выбранного клиента.<?php endif; ?>
            </p>
        </div>
    </div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php
    $body = '<div class="space-y-1"><label for="filter_customer" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Клиент</label>'
        .'<select id="filter_customer" name="customer_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">'
        .'<option value="">базовый прайс</option>';

    foreach ($customers as $customer)
    {
        $body .= '<option value="'.(int) $customer['id'].'"'.(((int) $customer_id === (int) $customer['id']) ? ' selected' : '').'>'.HTML::chars($customer['name']).'</option>';
    }

    $body .= '</select></div>';

    $body .= '<div class="space-y-1"><label for="filter_item" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Позиция для истории цен</label>'
        .'<select id="filter_item" name="item_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">'
        .'<option value="">последние записи всех позиций</option>';

    foreach ($items as $item)
    {
        $body .= '<option value="'.(int) $item['id'].'"'.(((int) $item_id === (int) $item['id']) ? ' selected' : '').'>'.HTML::chars($item['code'].' — '.$item['name']).'</option>';
    }

    $body .= '</select></div>';

    echo Component::render('filters', array(
        'action'       => '/sales/prices',
        'body'         => $body,
        'reset_url'    => '/sales/prices',
        'submit_label' => 'Показать',
    ));
    ?>

    <?php
    $table_rows = array();

    foreach ($rows as $row)
    {
        $item = $row['item'];
        $price = $row['price'];

        $has_price = ! empty($price['found']);

        $table_rows[] = array(
            'class' => $has_price ? NULL : 'bg-amber-50/60 dark:bg-amber-900/10',
            'cells' => array(
                '<span class="font-mono text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($item['code']).'</span><br>'.HTML::chars($item['name']),
                number_format((float) $price['price'], 2, '.', ' ').' ₽',
                (float) $price['discount'] > 0 ? (float) $price['discount'].' %' : '—',
                number_format((float) $price['net_price'], 2, '.', ' ').' ₽',
                $has_price
                    ? Component::render('badge', array(
                        'label' => $price['source_label'],
                        'state' => ! empty($price['customer_id']) ? 'info' : (($price['source'] === 'history') ? 'ok' : 'neutral'),
                        'title' => $price['basis'],
                    ))
                    : Component::render('badge', array('label' => 'цена не задана', 'state' => 'warn', 'title' => $price['basis'])),
            ),
        );
    }

    echo Component::begin('card', array(
        'title'    => 'Цены позиций',
        'icon'     => 'fas fa-ruble-sign',
        'subtitle' => $customer_id ? 'с учётом цены клиента' : 'базовый прайс',
        'flush'    => TRUE,
    ));
    echo Component::render('table', array(
        'caption'     => 'Цены позиций каталога',
        'columns'     => array(
            array('label' => 'Позиция'),
            array('label' => 'Цена', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Скидка', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Итого', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Основание'),
        ),
        'rows'        => $table_rows,
        'row_header'  => TRUE,
        'sticky'      => TRUE,
        'empty_title' => 'Номенклатура пуста',
        'empty_hint'  => 'Позиции приходят из реестра номенклатуры (erp227).',
    ));
    echo Component::end();
    ?>

    <?php if ($can_manage): ?>
        <div class="mt-4">
            <?php
            echo Component::begin('card', array(
                'title'    => 'Записать цену',
                'icon'     => 'fas fa-plus',
                'subtitle' => 'новая запись в истории цен; старая не удаляется',
            ));
            ?>
            <form method="post" action="/sales/prices/save">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 items-start">
                    <div class="space-y-1">
                        <label for="price_item" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Позиция <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <select id="price_item" name="item_id" required
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo (int) $item['id']; ?>"><?php echo HTML::chars($item['code'].' — '.$item['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label for="price_customer" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Клиент</label>
                        <select id="price_customer" name="customer_id"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">базовая цена (для всех)</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo (int) $customer['id']; ?>"><?php echo HTML::chars($customer['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php
                    echo Component::render('form_field', array('id' => 'price_value', 'label' => 'Цена, ₽', 'name' => 'price', 'value' => '', 'type' => 'number', 'required' => TRUE));
                    echo Component::render('form_field', array('id' => 'price_discount', 'label' => 'Скидка, %', 'name' => 'discount', 'value' => '', 'type' => 'number'));
                    echo Component::render('form_field', array('id' => 'price_valid', 'label' => 'Действует с', 'name' => 'valid_from', 'value' => date('Y-m-d'), 'type' => 'date'));
                    ?>
                    <button type="submit" class="mt-6 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-save mr-2" aria-hidden="true"></i> Записать
                    </button>
                </div>
            </form>
            <?php
            echo Component::end();
            ?>
        </div>
    <?php endif; ?>

    <?php
    $rows = array();

    foreach ($history as $entry)
    {
        $rows[] = array(
            HTML::chars(date('d.m.Y H:i', strtotime((string) $entry->created_at))),
            $entry->customer_id ? 'Клиент #'.(int) $entry->customer_id : 'Базовая цена',
            number_format((float) $entry->price, 2, '.', ' ').' ₽',
            (float) $entry->discount > 0 ? (float) $entry->discount.' %' : '—',
            number_format((float) $entry->net_price(), 2, '.', ' ').' ₽',
            Component::render('badge', array('label' => $entry->source_label(), 'state' => $entry->source_state())),
        );
    }

    echo Component::begin('card', array(
        'title'    => 'История цен',
        'icon'     => 'fas fa-history',
        'subtitle' => $item_id ? 'по выбранной позиции' : 'выберите позицию в фильтре',
        'flush'    => TRUE,
    ));
    echo Component::render('table', array(
        'caption'     => 'История записей прайса',
        'columns'     => array(
            array('label' => 'Когда'),
            array('label' => 'Кому'),
            array('label' => 'Цена', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Скидка', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Итого', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Источник'),
        ),
        'rows'        => $rows,
        'row_header'  => TRUE,
        'empty_title' => 'История пуста',
        'empty_hint'  => 'Выберите позицию в фильтре или запишите первую цену.',
    ));
    echo Component::end();
    ?>
</div>
