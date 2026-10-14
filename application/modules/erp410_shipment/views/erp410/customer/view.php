<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка клиента: условия продажи, задолженность, заказы, счета и цены.
 *
 * Один экран отвечает на вопрос менеджера «можно ли отгружать этому клиенту и по
 * какой цене»: лимит с задолженностью стоят рядом, прайс клиента — ниже.
 */
?>

<div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($customer); ?></h1>
                <?php echo Component::render('badge', array(
                    'label' => $profile ? ($profile->is_active() ? 'активен' : 'выключен') : 'условия не заданы',
                    'state' => $profile ? ($profile->is_active() ? 'ok' : 'bad') : 'warn',
                )); ?>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Контрагент #<?php echo (int) $customer_id; ?>
                · оплата: <?php echo HTML::chars($profile ? $profile->payment_label() : 'предоплата'); ?>
                <?php if ($profile AND $profile->credit_days()): ?> · отсрочка <?php echo (int) $profile->credit_days(); ?> дн.<?php endif; ?>
                <?php if ($profile AND $profile->discount()): ?> · скидка <?php echo (float) $profile->discount(); ?> %<?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/sales/customers"
               class="inline-flex items-center px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> К клиентам
            </a>
            <a href="/sales/orders/edit"
               class="inline-flex items-center px-3 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-plus mr-2" aria-hidden="true"></i> Заказ
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <?php
        echo Component::render('kpi', array('label' => 'Лимит отсрочки', 'value' => number_format((float) $credit['limit'], 2, '.', ' '), 'unit' => '₽', 'state' => 'neutral'));
        echo Component::render('kpi', array(
            'label' => 'Задолженность',
            'value' => number_format((float) $credit['exposure'], 2, '.', ' '),
            'unit'  => '₽',
            'hint'  => 'по выставленным счетам',
            'state' => ($credit['limit'] > 0 AND $credit['exposure'] > $credit['limit']) ? 'bad' : 'info',
        ));
        echo Component::render('kpi', array(
            'label' => 'Свободно в лимите',
            'value' => number_format((float) $credit['available'], 2, '.', ' '),
            'unit'  => '₽',
            'hint'  => HTML::chars($credit['reason']),
            'state' => $credit['allowed'] ? 'ok' : 'warn',
        ));
        echo Component::render('kpi', array(
            'label' => 'Заказов открыто',
            'value' => (int) $order_stats['open'],
            'unit'  => 'шт',
            'hint'  => 'на '.number_format((float) $order_stats['amount_open'], 0, '.', ' ').' ₽',
            'state' => ((int) $order_stats['open'] > 0) ? 'info' : 'neutral',
        ));
        ?>
    </div>

    <?php if ($can_manage): ?>
        <div class="mb-6">
            <?php
            echo Component::begin('card', array(
                'title'    => 'Условия продажи',
                'icon'     => 'fas fa-sliders-h',
                'subtitle' => 'отсрочка, лимит, скидка и адрес доставки',
            ));
            ?>
            <form method="post" action="/sales/customers/save">
                <input type="hidden" name="customer_id" value="<?php echo (int) $customer_id; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
                    <?php
                    echo Component::render('form_field', array(
                        'id'    => 'c_payment_terms',
                        'label' => 'Условия оплаты',
                        'name'  => 'payment_terms',
                        'value' => $profile ? (string) $profile->payment_terms : '',
                        'hint'  => 'например: отсрочка 14 дней',
                    ));

                    echo Component::render('form_field', array(
                        'id'    => 'c_credit_days',
                        'label' => 'Отсрочка, дней',
                        'name'  => 'credit_days',
                        'value' => $profile ? (int) $profile->credit_days : 0,
                        'type'  => 'number',
                    ));

                    echo Component::render('form_field', array(
                        'id'    => 'c_credit_limit',
                        'label' => 'Лимит, ₽',
                        'name'  => 'credit_limit',
                        'value' => $profile ? number_format((float) $profile->credit_limit, 2, '.', '') : '0',
                        'type'  => 'number',
                        'hint'  => '0 — продажа только по предоплате',
                    ));

                    echo Component::render('form_field', array(
                        'id'    => 'c_discount',
                        'label' => 'Скидка, %',
                        'name'  => 'discount',
                        'value' => $profile ? (float) $profile->discount : 0,
                        'type'  => 'number',
                    ));

                    echo Component::render('form_field', array(
                        'id'    => 'c_delivery_address',
                        'label' => 'Адрес доставки',
                        'name'  => 'delivery_address',
                        'value' => $profile ? (string) $profile->delivery_address : '',
                    ));

                    echo Component::render('form_field', array(
                        'id'    => 'c_terms',
                        'label' => 'Особые условия',
                        'name'  => 'terms',
                        'value' => $profile ? (string) $profile->terms : '',
                        'type'  => 'textarea',
                    ));
                    ?>
                    <label class="flex items-center gap-2 mt-6 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="is_active" value="1" <?php echo ( ! $profile OR $profile->is_active()) ? 'checked' : ''; ?>>
                        условия действуют
                    </label>
                    <button type="submit" class="mt-6 inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-save mr-2" aria-hidden="true"></i> Сохранить условия
                    </button>
                </div>
            </form>
            <?php
            echo Component::end();
            ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <?php
        $rows = array();

        foreach ($orders as $order)
        {
            $rows[] = array(
                '<a href="/sales/orders/view/'.(int) $order->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($order->number).'</a>',
                $order->ship_date ? HTML::chars(date('d.m.Y', strtotime((string) $order->ship_date))) : '—',
                number_format((float) $order->amount, 2, '.', ' ').' ₽',
                Component::render('badge', array('label' => $order->status_label(), 'state' => $order->status_state())),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Последние заказы',
            'icon'     => 'fas fa-file-invoice',
            'subtitle' => 'до 10 последних',
            'flush'    => TRUE,
            'actions_html' => '<a href="/sales/orders?customer_id='.(int) $customer_id.'" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">все заказы</a>',
        ));
        echo Component::render('table', array(
            'caption'     => 'Последние заказы клиента '.$customer,
            'columns'     => array(array('label' => 'Заказ'), array('label' => 'Отгрузка', 'nowrap' => TRUE), array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Заказов нет',
            'empty_hint'  => 'Первый заказ создаётся кнопкой «Заказ».',
        ));
        echo Component::end();

        $rows = array();

        foreach ($invoices as $invoice)
        {
            $rows[] = array(
                '<a href="/sales/invoices/view/'.(int) $invoice->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($invoice->number).'</a>',
                number_format((float) $invoice->amount, 2, '.', ' ').' ₽',
                number_format((float) $invoice->outstanding(), 2, '.', ' ').' ₽',
                Component::render('badge', array(
                    'label' => $invoice->is_overdue() ? 'просрочен' : $invoice->status_label(),
                    'state' => $invoice->is_overdue() ? 'bad' : $invoice->status_state(),
                )),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Счета к оплате',
            'icon'     => 'fas fa-file-invoice-dollar',
            'subtitle' => 'до 10 последних',
            'flush'    => TRUE,
            'state'    => ($credit['limit'] > 0 AND $credit['exposure'] > $credit['limit']) ? 'bad' : NULL,
            'actions_html' => '<a href="/sales/invoices?customer_id='.(int) $customer_id.'" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">все счета</a>',
        ));
        echo Component::render('table', array(
            'caption'     => 'Счета клиента '.$customer,
            'columns'     => array(array('label' => 'Счёт'), array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Остаток', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Состояние')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Счетов нет',
            'empty_hint'  => 'Счёт выставляется из проведённой отгрузки.',
        ));
        echo Component::end();

        $rows = array();

        $item_names = array();

        foreach ($items as $item)
        {
            $item_names[(int) $item['id']] = $item['code'].' — '.$item['name'];
        }

        foreach ($prices as $price)
        {
            $item_id = (int) $price->item_id;

            $rows[] = array(
                HTML::chars(isset($item_names[$item_id]) ? $item_names[$item_id] : 'Позиция #'.$item_id),
                number_format((float) $price->price, 2, '.', ' ').' ₽',
                (float) $price->discount > 0 ? (float) $price->discount.' %' : '—',
                number_format((float) $price->net_price(), 2, '.', ' ').' ₽',
                Component::render('badge', array('label' => $price->source_label(), 'state' => $price->source_state())),
            );
        }

        echo Component::begin('card', array(
            'title'    => 'Цены клиента',
            'icon'     => 'fas fa-tags',
            'subtitle' => 'договорные цены и скидки',
            'flush'    => TRUE,
            'actions_html' => '<a href="/sales/prices?customer_id='.(int) $customer_id.'" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">весь прайс</a>',
        ));
        echo Component::render('table', array(
            'caption'     => 'Договорные цены клиента '.$customer,
            'columns'     => array(array('label' => 'Позиция'), array('label' => 'Цена', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Скидка', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Итого', 'align' => 'right', 'nowrap' => TRUE), array('label' => 'Источник')),
            'rows'        => $rows,
            'row_header'  => TRUE,
            'empty_title' => 'Договорных цен нет',
            'empty_hint'  => 'Цена клиента записывается на странице прайса.',
        ));
        echo Component::end();
        ?>
    </div>
</div>
