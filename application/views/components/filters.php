<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Панель фильтров — единая форма отбора для списков.
 *
 * Компонент: components/filters
 * Переменные:
 *   action       — адрес формы (обычно текущий список)
 *   method       — get (по умолчанию) или post
 *   body         — поля фильтра (готовый HTML): <label> + <select>/<input>
 *   submit_label — подпись кнопки (по умолчанию «Применить»)
 *   reset_url    — ссылка «Сбросить» (если передан)
 *   hidden       — массив скрытых полей (сохранить контекст списка)
 *
 * Зачем: у всех списков должен быть один блок отбора — одинаковое поведение
 * (кнопка, сброс, сохранение контекста) и одинаковый вид.
 */
$action       = isset($action) ? $action : '';
$method       = isset($method) ? $method : 'get';
$body         = isset($body) ? $body : '';
$submit_label = isset($submit_label) ? $submit_label : 'Применить';
$reset_url    = isset($reset_url) ? $reset_url : NULL;
$hidden       = isset($hidden) ? (array) $hidden : array();

// В простом режиме (Ui_Mode) панель свёрнута и раскрывается сама, когда отбор уже задан.
ob_start();
?>
<form method="<?php echo HTML::chars($method); ?>" action="<?php echo HTML::chars($action); ?>" autocomplete="off"
      class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6"
      role="search" aria-label="Фильтр списка">

    <?php foreach ($hidden as $name => $value): ?>
        <input type="hidden" name="<?php echo HTML::chars($name); ?>" value="<?php echo HTML::chars((string) $value); ?>">
    <?php endforeach; ?>

    <div class="flex flex-wrap items-end gap-4">
        <?php echo $body; ?>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="inline-flex min-h-10 items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                <i class="fas fa-filter mr-1" aria-hidden="true"></i> <?php echo HTML::chars($submit_label); ?>
            </button>

            <?php if ($reset_url): ?>
                <a href="<?php echo HTML::chars($reset_url); ?>"
                   class="inline-flex min-h-10 items-center px-3 py-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                    Сбросить
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php
echo Component::render('more', array(
    'summary' => 'Фильтры',
    'icon'  => 'fas fa-filter',
    'plain' => TRUE,
    'open'  => Component::query_applied(),
    'body'  => ob_get_clean(),
));
