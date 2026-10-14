<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Форма заказа клиента: создание и правка шапки.
 *
 * Позиции набираются сразу при создании (три строки по умолчанию): без
 * JavaScript, чтобы форма работала и при строгой политике безопасности.
 * У существующего заказа шапка правится здесь, а позиции — в карточке заказа,
 * где видны резерв и отгруженное количество.
 */
$is_edit = (bool) $order;
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <i class="fas fa-pen mr-2 text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
                <?php echo $is_edit ? 'Правка заказа '.HTML::chars($order->number) : 'Новый заказ клиента'; ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <?php if ($is_edit): ?>
                    Меняются только условия заказа. Позиции — в карточке заказа.
                <?php else: ?>
                    Цена подставляется из прайса клиента; пустая цена — из базового прайса.
                <?php endif; ?>
            </p>
        </div>
        <a href="<?php echo $is_edit ? '/sales/orders/view/'.(int) $order->id : '/sales/orders'; ?>"
           class="inline-flex items-center px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> <?php echo $is_edit ? 'К заказу' : 'К журналу'; ?>
        </a>
    </div>

    <?php echo View::factory('erp410/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/sales/orders/save" autocomplete="off">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo (int) $order->id; ?>">
        <?php endif; ?>

        <?php
        echo Component::begin('card', array(
            'title'    => 'Клиент и условия',
            'icon'     => 'fas fa-handshake',
            'subtitle' => 'кто покупает, куда везти и на каких условиях',
        ));

        $options = '<option value="">— выберите клиента —</option>';

        foreach ($customers as $customer)
        {
            $selected = ($is_edit AND (int) $order->customer_id === (int) $customer['id']) ? ' selected' : '';

            $options .= '<option value="'.(int) $customer['id'].'"'.$selected.'>'.HTML::chars($customer['name']).'</option>';
        }

        $options_html = '<div class="space-y-1"><label for="f_customer" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Клиент <span class="text-rose-600" aria-hidden="true">*</span></label>'
            .'<select id="f_customer" name="customer_id" required class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">'.$options.'</select></div>';

        $options = '<option value="">— склад по умолчанию —</option>';

        foreach ($warehouses as $warehouse)
        {
            $selected = ($is_edit AND (int) $order->warehouse_id === (int) $warehouse->id) ? ' selected' : '';

            $options .= '<option value="'.(int) $warehouse->id.'"'.$selected.'>'.HTML::chars($warehouse->name).'</option>';
        }

        $warehouse_html = '<div class="space-y-1"><label for="f_warehouse" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Склад отгрузки</label>'
            .'<select id="f_warehouse" name="warehouse_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">'.$options.'</select></div>';
        ?>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 items-start">
            <?php echo $options_html; ?>
            <?php echo $warehouse_html; ?>

            <?php
            echo Component::render('form_field', array(
                'id'    => 'f_order_date',
                'label' => 'Дата заказа',
                'name'  => 'order_date',
                'value' => ($is_edit AND $order->order_date) ? date('Y-m-d', strtotime((string) $order->order_date)) : date('Y-m-d'),
                'type'  => 'date',
            ));

            echo Component::render('form_field', array(
                'id'    => 'f_ship_date',
                'label' => 'Дата отгрузки',
                'name'  => 'ship_date',
                'value' => ($is_edit AND $order->ship_date) ? date('Y-m-d', strtotime((string) $order->ship_date)) : '',
                'type'  => 'date',
                'hint'  => 'срок, к которому товар должен уйти клиенту',
            ));

            echo Component::render('form_field', array(
                'id'    => 'f_payment_terms',
                'label' => 'Условия оплаты',
                'name'  => 'payment_terms',
                'value' => $is_edit ? (string) $order->payment_terms : '',
                'hint'  => 'например: отсрочка 14 дней',
            ));

            echo Component::render('form_field', array(
                'id'    => 'f_delivery_address',
                'label' => 'Адрес доставки',
                'name'  => 'delivery_address',
                'value' => $is_edit ? (string) $order->delivery_address : '',
            ));

            echo Component::render('form_field', array(
                'id'    => 'f_note',
                'label' => 'Примечание',
                'name'  => 'note',
                'value' => $is_edit ? (string) $order->note : '',
                'type'  => 'textarea',
            ));
            ?>
        </div>
        <?php
        echo Component::end();
        ?>

        <?php if ( ! $is_edit): ?>
            <?php
            $item_options = '<option value="">— выберите позицию —</option>';

            foreach ($items as $item)
            {
                $item_options .= '<option value="'.(int) $item['id'].'">'.HTML::chars($item['code'].' — '.$item['name']).'</option>';
            }

            echo Component::begin('card', array(
                'title'    => 'Позиции заказа',
                'icon'     => 'fas fa-list-ol',
                'subtitle' => 'до трёх строк сразу; остальные добавятся в карточке заказа',
            ));
            ?>

            <div class="space-y-3">
                <?php for ($row = 0; $row < 3; $row++): ?>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        <div class="md:col-span-5 space-y-1">
                            <label for="f_item_<?php echo $row; ?>" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Позиция</label>
                            <select id="f_item_<?php echo $row; ?>" name="item_id[]"
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <?php echo $item_options; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <?php echo Component::render('form_field', array('id' => 'f_qty_'.$row, 'label' => 'Кол-во', 'name' => 'qty[]', 'value' => '', 'type' => 'number')); ?>
                        </div>
                        <div class="md:col-span-2">
                            <?php echo Component::render('form_field', array('id' => 'f_price_'.$row, 'label' => 'Цена, ₽', 'name' => 'price[]', 'value' => '', 'type' => 'number')); ?>
                        </div>
                        <div class="md:col-span-3">
                            <?php echo Component::render('form_field', array('id' => 'f_discount_'.$row, 'label' => 'Скидка, %', 'name' => 'line_discount[]', 'value' => '', 'type' => 'number')); ?>
                        </div>
                    </div>
                <?php endfor; ?>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Пустое количество считается единицей; пустая цена — берётся из прайса клиента.
                </p>
            </div>
            <?php
            echo Component::end();
            ?>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-save mr-2" aria-hidden="true"></i>
                <?php echo $is_edit ? 'Сохранить заказ' : 'Создать заказ'; ?>
            </button>
            <a href="<?php echo $is_edit ? '/sales/orders/view/'.(int) $order->id : '/sales/orders'; ?>"
               class="inline-flex items-center px-4 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                Отмена
            </a>
        </div>
    </form>
</div>
