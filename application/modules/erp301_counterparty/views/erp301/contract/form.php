<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/** @var Model_Erp301_Contract $contract */
$is_new = ! $contract->loaded();
$input  = 'w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-3 py-2 border';
$label  = 'block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1';
?>

<div class="mx-auto max-w-3xl">

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
        <i class="fas fa-file-signature mr-2 text-blue-600"></i>
        <?php echo $is_new ? 'Новый договор' : 'Договор № '.HTML::chars($contract->number); ?>
    </h1>

    <?php if ($counterparty): ?>
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            Контрагент:
            <a class="text-blue-600 hover:underline" href="/counterparties/view/<?php echo (int) $counterparty->id; ?>">
                <?php echo HTML::chars($counterparty->name); ?>
            </a>
        </div>
    <?php else: ?>
        <div class="mb-6 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200">
            Договор создаётся из карточки контрагента — выберите контрагента в <a class="underline" href="/counterparties/list">списке</a>.
        </div>
    <?php endif; ?>

    <?php echo View::factory('erp301/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>
    <?php echo View::factory('erp301/partials/errors')->set('errors', isset($errors) ? $errors : array()); ?>

    <form method="post" action="/counterparties/contract_save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5">
        <?php if ( ! $is_new): ?>
            <input type="hidden" name="id" value="<?php echo (int) $contract->id; ?>">
        <?php endif; ?>
        <input type="hidden" name="counterparty_id" value="<?php echo (int) $counterparty_id; ?>">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="<?php echo $label; ?>" for="number">Номер <span class="text-red-500">*</span></label>
                <input class="<?php echo $input; ?>" type="text" id="number" name="number" required maxlength="64"
                       value="<?php echo HTML::chars($contract->number); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="kind">Вид договора</label>
                <select class="<?php echo $input; ?>" id="kind" name="kind">
                    <?php foreach ($kinds as $code => $title): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo $contract->kind === $code ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="status">Статус</label>
                <select class="<?php echo $input; ?>" id="status" name="status">
                    <?php foreach ($statuses as $code => $title): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo $contract->status === $code ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="<?php echo $label; ?>" for="title">Предмет договора</label>
            <input class="<?php echo $input; ?>" type="text" id="title" name="title" maxlength="255"
                   value="<?php echo HTML::chars($contract->title); ?>">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="<?php echo $label; ?>" for="signed_at">Дата подписания</label>
                <input class="<?php echo $input; ?>" type="date" id="signed_at" name="signed_at"
                       value="<?php echo HTML::chars($contract->signed_at); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="valid_from">Действует с</label>
                <input class="<?php echo $input; ?>" type="date" id="valid_from" name="valid_from"
                       value="<?php echo HTML::chars($contract->valid_from); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="valid_to">Действует по</label>
                <input class="<?php echo $input; ?>" type="date" id="valid_to" name="valid_to"
                       value="<?php echo HTML::chars($contract->valid_to); ?>">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="<?php echo $label; ?>" for="amount">Сумма</label>
                <input class="<?php echo $input; ?>" type="text" id="amount" name="amount"
                       placeholder="1 250 000,00" value="<?php echo HTML::chars($contract->amount); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="currency">Валюта</label>
                <input class="<?php echo $input; ?>" type="text" id="currency" name="currency" maxlength="3"
                       value="<?php echo HTML::chars($contract->currency ?: 'RUB'); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="payment_terms">Условия оплаты</label>
                <input class="<?php echo $input; ?>" type="text" id="payment_terms" name="payment_terms" maxlength="255"
                       value="<?php echo HTML::chars($contract->payment_terms); ?>">
            </div>
        </div>

        <div>
            <label class="<?php echo $label; ?>" for="note">Заметка</label>
            <textarea class="<?php echo $input; ?>" id="note" name="note" rows="2"><?php echo HTML::chars($contract->note); ?></textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="<?php echo $counterparty_id ? '/counterparties/view/'.(int) $counterparty_id : '/counterparties/contracts'; ?>"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">Отмена</a>
            <button type="submit" <?php echo $counterparty_id ? '' : 'disabled'; ?>
                    class="inline-flex items-center px-5 py-2 text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                <i class="fas fa-save mr-2"></i> Сохранить
            </button>
        </div>
    </form>
</div>
