<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Лента работы: записи по всем сделкам и клиентам с отбором.
 *
 * Переменные: rows, total, page, per_page, filters, types, statuses, url, messages.
 */
$query = function (array $extra = array()) use ($filters, $url) {
    $params = array_filter(array_merge(array(
        'q'      => $filters['q'],
        'type'   => $filters['type'],
        'status' => $filters['status'],
        'author' => $filters['author'],
    ), $extra), function ($value) {
        return $value !== NULL AND $value !== '';
    });

    return $url.http_build_query($params);
};

$pages = (int) ceil($total / max(1, $per_page));
?>
<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Лента работы</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Кто, когда и что сделал по продажам. Всего записей: <span class="font-semibold text-gray-900 dark:text-white"><?php echo (int) $total; ?></span>.
        </p>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <form method="get" action="/crm/journal" class="mb-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-start gap-3">
            <?php echo Component::render('form_field', array('id' => 'j_q', 'label' => 'Поиск', 'name' => 'q', 'value' => $filters['q'])); ?>
            <div class="space-y-1">
                <label for="j_type" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Вид</label>
                <select id="j_type" name="type" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Любой</option>
                    <?php foreach ($types as $code => $label): ?>
                        <option value="<?php echo HTML::chars($code); ?>"<?php echo $filters['type'] === $code ? ' selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label for="j_status" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">Состояние</label>
                <select id="j_status" name="status" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Любое</option>
                    <?php foreach ($statuses as $code => $label): ?>
                        <option value="<?php echo HTML::chars($code); ?>"<?php echo $filters['status'] === $code ? ' selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="mt-6 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-700">Показать</button>
        </div>
    </form>

    <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php if ( ! $rows): ?>
                <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Записей по заданным условиям нет.</p>
            <?php endif; ?>
            <?php foreach ($rows as $entry): ?>
                <article class="px-5 py-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <?php echo Component::render('badge', array('label' => isset($types[$entry['type']]) ? $types[$entry['type']] : $entry['type'], 'state' => 'neutral')); ?>
                        <span><?php echo HTML::chars($entry['created_at']); ?></span>
                        <?php if ($entry['author_name']): ?><span>· <?php echo HTML::chars($entry['author_name']); ?></span><?php endif; ?>
                        <?php if ($entry['deal_id']): ?>
                            <span>· <a href="/crm/deal/<?php echo (int) $entry['deal_id']; ?>" class="text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($entry['deal_number']); ?></a></span>
                        <?php endif; ?>
                        <?php if ($entry['client_name']): ?>
                            <span>· <a href="/crm/client/<?php echo (int) $entry['counterparty_id']; ?>" class="text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($entry['client_name']); ?></a></span>
                        <?php endif; ?>
                        <?php if ($entry['status'] !== 'completed'): ?>
                            <?php echo Component::render('badge', array('label' => isset($statuses[$entry['status']]) ? $statuses[$entry['status']] : $entry['status'], 'state' => $entry['is_overdue'] ? 'bad' : 'warn')); ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($entry['subject']): ?><div class="mt-1 font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($entry['subject']); ?></div><?php endif; ?>
                    <?php if ($entry['content']): ?><div class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200"><?php echo HTML::chars($entry['content']); ?></div><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <?php echo Component::render('pagination', array(
        'page'  => $page,
        'pages' => $pages,
        'total' => $total,
        'url'   => function ($page) use ($query) { return $query(array('page' => $page)); },
    )); ?>
</div>
