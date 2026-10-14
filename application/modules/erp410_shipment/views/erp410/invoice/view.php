<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка счёта: позиции, оплата и основания.
 *
 * Оплата принимается частями: остаток пересчитывается сервисом, состояние
 * меняется на «оплачен» только когда закрыта вся сумма.
 */
$outstanding = (float) $invoice->outstanding();
$lines_total = 0.0;

foreach ($lines as $line_row)
{
    $lines_total += (float) $line_row->amount();
}
?>

<div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white font-mono">
                    <?php echo HTML::chars($invoice->number); ?>
                </h1>
                <?php echo Component::render('badge', array('label' => $invoice->status_label(), 'state' => $invoice->status_state())); ?>
                <?php if ($invoice->is_overdue()): ?>
                    <?php echo Component::render('badge', array('label' => 'просрочен', 'state' => 'bad')); ?>
                <?php endif; ?>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php echo HTML::chars($customer ?: 'Клиент не задан'); ?>
                <?php if ($order): ?>
                    · заказ <a href="/sales/orders/view/<?php echo (int) $order->id; ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-mono"><?php echo HTML::chars($order->number); ?></a>
                <?php endif; ?>
                <?php if ($shipment): ?>
                    · отгрузка <a href="/sales/shipments/view/<?php echo (int) $shipment->id; ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 font-mono"><?php echo HTML::chars($shipment->number); ?></a>
                <?php endif; ?>
                <?php if ($invoice->issue_date): ?> · выставлен <?php echo HTML::chars(date('d.m.Y', strtotime((string) $invoice->issue_date))); ?><?php endif; ?>
            </p>
        </div>
        <a href="/sales/invoices"
           class="inline-flex items-center px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> К журналу
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <?php
        echo Component::render('kpi', array('label' => 'Сумма счёта', 'value' => number_format((float) $invoice->amount, 2, '.', ' '), 'unit' => '₽', 'state' => 'info'));
        echo Component::render('kpi', array('label' => 'Оплачено', 'value' => number_format((float) $invoice->paid_amount, 2, '.', ' '), 'unit' => '₽', 'state' => ((float) $invoice->paid_amount > 0) ? 'ok' : 'neutral'));
        echo Component::render('kpi', array(
            'label' => 'Остаток',
            'value' => number_format(max(0, $outstanding), 2, '.', ' '),
            'unit'  => '₽',
            'hint'  => ($outstanding > 0.009) ? 'к оплате' : 'закрыт',
            'state' => ($outstanding > 0.009) ? 'warn' : 'ok',
        ));
        echo Component::render('kpi', array(
            'label' => 'Оплатить до',
            'value' => $invoice->due_date ? date('d.m.Y', strtotime((string) $invoice->due_date)) : '—',
            'unit'  => $invoice->due_date ? '' : 'нет',
            'hint'  => $invoice->is_overdue() ? 'срок прошёл' : 'срок оплаты',
            'state' => $invoice->is_overdue() ? 'bad' : 'neutral',
        ));
        ?>
    </div>

    <div class="flex flex-wrap items-end gap-2 mb-6">
        <?php if ($can_invoice AND (string) $invoice->status === 'draft'): ?>
            <form method="post" action="/sales/invoices/act/issue/<?php echo (int) $invoice->id; ?>" class="inline">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-paper-plane mr-2" aria-hidden="true"></i> Выставить клиенту
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_invoice AND $invoice->is_payable() AND $outstanding > 0.009): ?>
            <form method="post" action="/sales/invoices/act/pay/<?php echo (int) $invoice->id; ?>" class="inline">
                <div class="space-y-1">
                    <label for="pay_amount" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Сумма оплаты, ₽</label>
                    <input id="pay_amount" name="amount" type="number" step="0.01" min="0.01" required
                           value="<?php echo number_format($outstanding, 2, '.', ''); ?>"
                           class="block w-40 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700">
                    <i class="fas fa-ruble-sign mr-2" aria-hidden="true"></i> Принять оплату
                </button>
            </form>
        <?php endif; ?>

        <?php if ($can_invoice AND (string) $invoice->status !== 'cancelled'): ?>
            <form method="post" action="/sales/invoices/act/cancel/<?php echo (int) $invoice->id; ?>" class="inline">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-rose-300 dark:border-rose-700 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                    <i class="fas fa-ban mr-2" aria-hidden="true"></i> Отменить счёт
                </button>
            </form>
        <?php endif; ?>

        <?php if ( ! $can_invoice): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">Изменение счёта доступно роли с правом <span class="font-mono">erp410.invoice</span>.</p>
        <?php endif; ?>
    </div>

    <?php
    $rows = array();

    foreach ($lines as $line)
    {
        $rows[] = array(
            HTML::chars($line->name)
                .($line->code ? '<br><span class="text-xs text-gray-500 dark:text-gray-400 font-mono">'.HTML::chars($line->code).'</span>' : ''),
            number_format((float) $line->qty, 3, '.', ' ').' '.HTML::chars($line->uom_code),
            number_format((float) $line->price, 2, '.', ' '),
            number_format((float) $line->amount(), 2, '.', ' '),
        );
    }

    echo Component::begin('card', array(
        'title'    => 'Позиции счёта',
        'icon'     => 'fas fa-list-ol',
        'subtitle' => number_format($lines_total, 2, '.', ' ').' ₽ по строкам',
        'flush'    => TRUE,
    ));
    echo Component::render('table', array(
        'caption'     => 'Позиции счёта '.$invoice->number,
        'columns'     => array(
            array('label' => 'Позиция'),
            array('label' => 'Количество', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Цена', 'align' => 'right', 'nowrap' => TRUE),
            array('label' => 'Сумма', 'align' => 'right', 'nowrap' => TRUE),
        ),
        'rows'        => $rows,
        'row_header'  => TRUE,
        'empty_title' => 'В счёте нет позиций',
        'empty_hint'  => 'Позиции переносятся из отгрузки или заказа.',
    ));
    echo Component::end();
    ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-4">
        <?php
        $rows = array();

        if ($order)
        {
            $rows[] = array('Заказ', '<a href="/sales/orders/view/'.(int) $order->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($order->number).'</a>');
        }

        if ($shipment)
        {
            $rows[] = array('Отгрузка', '<a href="/sales/shipments/view/'.(int) $shipment->id.'" class="font-mono text-blue-600 hover:text-blue-800 dark:text-blue-400">'.HTML::chars($shipment->number).'</a>');
        }

        $rows[] = array('Клиент', HTML::chars($customer ?: 'не задан'));
        $rows[] = array('Валюта', HTML::chars($invoice->currency));
        $rows[] = array('Выставлен', HTML::chars($invoice->issued_at ? date('d.m.Y H:i', strtotime((string) $invoice->issued_at)) : 'черновик'));
        $rows[] = array('Оплачен', HTML::chars($invoice->paid_at ? date('d.m.Y H:i', strtotime((string) $invoice->paid_at)) : 'нет'));

        echo Component::begin('card', array(
            'title'    => 'Основания',
            'icon'     => 'fas fa-link',
            'subtitle' => 'из чего собран счёт',
            'flush'    => TRUE,
        ));
        echo Component::render('table', array(
            'caption'    => 'Основания счёта '.$invoice->number,
            'columns'    => array(array('label' => 'Показатель'), array('label' => 'Значение')),
            'rows'       => $rows,
            'row_header' => TRUE,
        ));
        echo Component::end();

        $paid_percent = ((float) $invoice->amount > 0)
            ? min(100, round((float) $invoice->paid_amount / (float) $invoice->amount * 100, 1))
            : 100;

        // Ширина полосы — классами, а не inline-стилем: инлайновые стили
        // запрещены контрактом интерфейса (bin/frontend-audit.py), а точный
        // процент читается в подписи для скринридера. Ступени крупные, но
        // крошечную оплату (меньше процента) отделяем от нуля: иначе полоса
        // молча показывала бы четверть при одном рубле из большой суммы.
        if ($paid_percent <= 0)
        {
            $paid_width = 'w-0';
        }
        elseif ($paid_percent < 1)
        {
            $paid_width = 'w-1/12';
        }
        elseif ($paid_percent < 25)
        {
            $paid_width = 'w-1/4';
        }
        elseif ($paid_percent < 50)
        {
            $paid_width = 'w-1/2';
        }
        elseif ($paid_percent < 75)
        {
            $paid_width = 'w-3/4';
        }
        else
        {
            $paid_width = 'w-full';
        }

        echo Component::render('card', array(
            'title' => 'Оплата',
            'icon'  => 'fas fa-ruble-sign',
            'state' => ($outstanding > 0.009) ? 'warn' : 'ok',
            'body'  => '<p class="text-sm text-gray-600 dark:text-gray-300">Оплачено '.$paid_percent.' % от суммы счёта.</p>'
                .'<div class="mt-3 h-2 w-full rounded bg-gray-200 dark:bg-gray-700" role="img" aria-label="Оплачено '.$paid_percent.' процентов">'
                .'<div class="h-2 rounded bg-emerald-500 '.$paid_width.'"></div></div>'
                .'<dl class="mt-3 text-sm space-y-1">'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Сумма</dt><dd>'.number_format((float) $invoice->amount, 2, '.', ' ').' ₽</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Оплачено</dt><dd>'.number_format((float) $invoice->paid_amount, 2, '.', ' ').' ₽</dd></div>'
                .'<div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Остаток</dt><dd>'.number_format(max(0, $outstanding), 2, '.', ' ').' ₽</dd></div>'
                .'</dl>'
                .($invoice->note ? '<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">'.HTML::chars($invoice->note).'</p>' : ''),
        ));
        ?>
    </div>
</div>
