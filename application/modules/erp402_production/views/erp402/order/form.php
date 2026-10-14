<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php $edit = (bool) $order->id; ?>

<div class="mx-auto max-w-4xl">

    <div class="mb-6">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            <a href="/production/orders" class="hover:text-blue-600">Заказы</a> /
            <?php echo $edit ? 'изменение заказа' : 'новый заказ'; ?>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
            <?php echo $edit ? HTML::chars($order->number) : 'Новый производственный заказ'; ?>
        </h1>
    </div>

    <?php echo View::factory('erp402/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php if ( ! empty($errors)): ?>
        <div class="border rounded-md px-4 py-3 text-sm mb-4 bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200">
            <div class="font-medium mb-1">Проверьте заполнение формы:</div>
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $field => $messages): ?>
                    <?php foreach ((array) $messages as $message): ?>
                        <li><?php echo HTML::chars($message); ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/production/orders/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 space-y-4">
        <input type="hidden" name="id" value="<?php echo $edit ? (int) $order->id : 0; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="title">Изделие / наименование заказа *</label>
                <input type="text" name="title" id="title" required maxlength="255"
                       value="<?php echo HTML::chars($order->title); ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="tech_card_id">Технологическая карта</label>
                <select name="tech_card_id" id="tech_card_id" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                    <option value="">— не выбрана —</option>
                    <?php foreach ($tech_cards as $card): ?>
                        <option value="<?php echo (int) $card->id; ?>" <?php echo ((int) $order->tech_card_id === (int) $card->id) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($card->title.' ('.$card->status.')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ( ! $edit): ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        После сохранения нажмите «Состав из техкарты» — потребность заполнится из актуального техпроцесса.
                    </p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="item_id">Изделие (номенклатура реестра)</label>
                <select name="item_id" id="item_id" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                    <option value="">— не выбрана —</option>
                    <?php foreach ($items as $position): ?>
                        <option value="<?php echo (int) $position['id']; ?>" <?php echo ((int) $order->item_id === (int) $position['id']) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($position['code'].' · '.$position['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="qty">План выпуска *</label>
                <input type="number" name="qty" id="qty" step="0.001" min="0.001" required
                       value="<?php echo HTML::chars((string) ($order->qty !== NULL ? $order->qty : 1)); ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="uom_code">Единица измерения</label>
                <select name="uom_code" id="uom_code" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                    <?php foreach ($uoms as $code => $label): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo ((string) $order->uom_code === (string) $code OR ( ! $order->uom_code AND $code === 'pcs')) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="priority">Приоритет</label>
                <select name="priority" id="priority" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                    <?php foreach ($priorities as $value => $label): ?>
                        <option value="<?php echo (int) $value; ?>" <?php echo ((int) $order->priority === (int) $value OR ( ! $order->priority AND $value === 5)) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="warehouse_id">Склад материалов</label>
                <select name="warehouse_id" id="warehouse_id" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                    <option value="">— не выбран —</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo (int) $warehouse->id; ?>" <?php echo ((int) $order->warehouse_id === (int) $warehouse->id) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($warehouse->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="output_warehouse_id">Склад готовой продукции</label>
                <select name="output_warehouse_id" id="output_warehouse_id" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                    <option value="">— не выбран —</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo (int) $warehouse->id; ?>" <?php echo ((int) $order->output_warehouse_id === (int) $warehouse->id) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($warehouse->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="target_date">Срок изготовления</label>
                <input type="date" name="target_date" id="target_date"
                       value="<?php echo HTML::chars((string) $order->target_date); ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="customer_id">Заказчик (контрагент)</label>
                <input type="number" name="customer_id" id="customer_id" min="0"
                       value="<?php echo $order->customer_id ? (int) $order->customer_id : ''; ?>"
                       class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1" for="note">Примечание</label>
                <textarea name="note" id="note" rows="3"
                          class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm"><?php echo HTML::chars((string) $order->note); ?></textarea>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-4 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> <?php echo $edit ? 'Сохранить' : 'Создать заказ'; ?>
            </button>
            <a href="<?php echo $edit ? '/production/orders/view/'.(int) $order->id : '/production/orders'; ?>"
               class="px-4 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                Отмена
            </a>
        </div>
    </form>
</div>
