<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/** @var Model_Erp301_Counterparty $counterparty */
$is_new    = ! $counterparty->loaded();
$role_list = $counterparty->loaded() ? $counterparty->role_codes() : array();
$input     = 'w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-3 py-2 border';
$label     = 'block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1';
?>

<div class="mx-auto max-w-4xl">

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        <i class="fas fa-address-card mr-2 text-blue-600"></i>
        <?php echo $is_new ? 'Новый контрагент' : 'Контрагент: '.HTML::chars($counterparty->name); ?>
    </h1>

    <?php echo View::factory('erp301/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>
    <?php echo View::factory('erp301/partials/errors')->set('errors', isset($errors) ? $errors : array()); ?>

    <form method="post" action="/counterparties/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5">
        <?php if ( ! $is_new): ?>
            <input type="hidden" name="id" value="<?php echo (int) $counterparty->id; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="<?php echo $label; ?>" for="name">Наименование <span class="text-red-500">*</span></label>
                <input class="<?php echo $input; ?>" type="text" id="name" name="name" required maxlength="255"
                       value="<?php echo HTML::chars($counterparty->name); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="code">Код</label>
                <input class="<?php echo $input; ?>" type="text" id="code" name="code" maxlength="32"
                       placeholder="авто (CP-0001)" value="<?php echo HTML::chars($counterparty->code); ?>">
            </div>
        </div>

        <div>
            <label class="<?php echo $label; ?>" for="full_name">Полное наименование для документов</label>
            <input class="<?php echo $input; ?>" type="text" id="full_name" name="full_name" maxlength="512"
                   value="<?php echo HTML::chars($counterparty->full_name); ?>">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="<?php echo $label; ?>" for="kind">Организационно-правовая форма</label>
                <select class="<?php echo $input; ?>" id="kind" name="kind">
                    <?php foreach ($kinds as $code => $title): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo $counterparty->kind === $code ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="status">Статус</label>
                <select class="<?php echo $input; ?>" id="status" name="status">
                    <?php foreach ($statuses as $code => $title): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo $counterparty->status === $code ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <fieldset>
            <legend class="<?php echo $label; ?>">Роли контрагента</legend>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($roles as $code => $title): ?>
                    <label class="inline-flex items-center text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="roles[]" value="<?php echo HTML::chars($code); ?>"
                               class="rounded border-gray-300 text-blue-600 mr-2"
                            <?php echo in_array($code, $role_list, TRUE) ? 'checked' : ''; ?>>
                        <?php echo HTML::chars($title); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="<?php echo $label; ?>" for="inn">ИНН</label>
                <input class="<?php echo $input; ?>" type="text" id="inn" name="inn" maxlength="12"
                       value="<?php echo HTML::chars($counterparty->inn); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="kpp">КПП</label>
                <input class="<?php echo $input; ?>" type="text" id="kpp" name="kpp" maxlength="9"
                       value="<?php echo HTML::chars($counterparty->kpp); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="ogrn">ОГРН</label>
                <input class="<?php echo $input; ?>" type="text" id="ogrn" name="ogrn" maxlength="15"
                       value="<?php echo HTML::chars($counterparty->ogrn); ?>">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="<?php echo $label; ?>" for="phone">Телефон</label>
                <input class="<?php echo $input; ?>" type="text" id="phone" name="phone" maxlength="64"
                       value="<?php echo HTML::chars($counterparty->phone); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="email">E-mail</label>
                <input class="<?php echo $input; ?>" type="email" id="email" name="email" maxlength="128"
                       value="<?php echo HTML::chars($counterparty->email); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="site">Сайт</label>
                <input class="<?php echo $input; ?>" type="text" id="site" name="site" maxlength="255"
                       value="<?php echo HTML::chars($counterparty->site); ?>">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="<?php echo $label; ?>" for="country">Страна</label>
                <input class="<?php echo $input; ?>" type="text" id="country" name="country" maxlength="64"
                       value="<?php echo HTML::chars($counterparty->country); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="city">Город</label>
                <input class="<?php echo $input; ?>" type="text" id="city" name="city" maxlength="128"
                       value="<?php echo HTML::chars($counterparty->city); ?>">
            </div>
            <div>
                <label class="<?php echo $label; ?>" for="rating">Оценка (0—5)</label>
                <input class="<?php echo $input; ?>" type="number" id="rating" name="rating" min="0" max="5"
                       value="<?php echo (int) $counterparty->rating; ?>">
            </div>
        </div>

        <div>
            <label class="<?php echo $label; ?>" for="address">Адрес</label>
            <textarea class="<?php echo $input; ?>" id="address" name="address" rows="2"><?php echo HTML::chars($counterparty->address); ?></textarea>
        </div>

        <div>
            <label class="<?php echo $label; ?>" for="note">Заметка</label>
            <textarea class="<?php echo $input; ?>" id="note" name="note" rows="2"><?php echo HTML::chars($counterparty->note); ?></textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="<?php echo $is_new ? '/counterparties/list' : '/counterparties/view/'.(int) $counterparty->id; ?>"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                Отмена
            </a>
            <button type="submit"
                    class="inline-flex items-center px-5 py-2 text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-save mr-2"></i> Сохранить
            </button>
        </div>
    </form>

    <?php if ( ! $is_new): ?>
        <form method="post" action="/counterparties/delete" class="mt-4"
              data-confirm="Переместить контрагента в архив вместе с контактами, реквизитами и договорами?">
            <input type="hidden" name="id" value="<?php echo (int) $counterparty->id; ?>">
            <button type="submit" class="text-sm text-red-600 hover:underline">
                <i class="fas fa-archive mr-1"></i> Поместить в архив
            </button>
        </form>
    <?php endif; ?>
</div>
