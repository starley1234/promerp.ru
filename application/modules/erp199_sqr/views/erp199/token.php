<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка наклейки: что за предмет, к чему привязана, что с ней делали.
 *
 * Здесь же привязка к цели вручную (когда наклейку наклеили, а в системе её
 * ещё ни к чему не отнесли) и смена состояния — потеряна, повреждена, в архив.
 *
 * Переменные: token, target, photos, activity, states, kinds, can_bind,
 * can_manage, messages.
 */
$target_url = ($target AND ! empty($target['url'])) ? $target['url'] : NULL;
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="/qr/tokens" class="text-sm text-blue-700 hover:underline dark:text-blue-400">← Все наклейки</a>
            <h1 class="mt-1 font-mono text-2xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($token['code']); ?></h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <?php echo Component::render('badge', array('label' => Service_Erp199_Tokens::instance()->status_label($token['status']), 'state' => Service_Erp199_Tokens::instance()->state_of($token['status']))); ?>
                <?php if ($token['label_note']): ?><span class="ml-2"><?php echo HTML::chars($token['label_note']); ?></span><?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="/qr/scan/<?php echo HTML::chars($token['code']); ?>" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                <i class="fas fa-mobile-screen" aria-hidden="true"></i> Открыть как в терминале
            </a>
            <a href="/qr/label?code=<?php echo urlencode($token['code']); ?>" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                <i class="fas fa-print" aria-hidden="true"></i> Этикетка
            </a>
        </div>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <div class="grid gap-6 lg:grid-cols-3">

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Предмет</h2>
            </header>
            <div class="p-5 text-sm">
                <?php if ($target): ?>
                    <div class="text-base font-medium text-gray-900 dark:text-white">
                        <?php if ($target_url): ?>
                            <a href="<?php echo HTML::chars($target_url); ?>" class="text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($target['label']); ?></a>
                        <?php else: ?>
                            <?php echo HTML::chars($target['label']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($target['subtitle']); ?></div>
                    <dl class="mt-3 space-y-2">
                        <?php foreach ($target['fields'] as $field): ?>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500 dark:text-gray-400"><?php echo HTML::chars($field['label']); ?></dt>
                                <dd class="text-right text-gray-900 dark:text-white"><?php echo HTML::chars((string) $field['value']); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php elseif ($token['target_kind']): ?>
                    <p class="text-sm text-rose-600 dark:text-rose-400">
                        Наклейка ссылается на «<?php echo HTML::chars(Arr::get($kinds, $token['target_kind'], $token['target_kind'])); ?>»,
                        но записи в системе больше нет. Проверьте, не удалена ли она.
                    </p>
                <?php else: ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Наклейка ни к чему не привязана.</p>
                <?php endif; ?>

                <dl class="mt-4 space-y-1 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <div><dt class="inline">Партия:</dt> <dd class="inline"><?php echo HTML::chars((string) $token['batch']); ?></dd></div>
                    <div><dt class="inline">Выпущена:</dt> <dd class="inline"><?php echo HTML::chars((string) $token['created_at']); ?></dd></div>
                    <div><dt class="inline">Сканирований:</dt> <dd class="inline"><?php echo (int) $token['scans_count']; ?></dd></div>
                    <div><dt class="inline">Последнее:</dt> <dd class="inline"><?php echo HTML::chars((string) $token['last_scan_at']); ?></dd></div>
                    <div class="break-all"><dt class="inline">UUID:</dt> <dd class="inline font-mono"><?php echo HTML::chars((string) $token['uuid']); ?></dd></div>
                </dl>
            </div>
        </section>

        <?php if ($can_bind): ?>
            <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Привязать к цели</h2>
                </header>
                <div class="p-5">
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                        Цель ищется в модуле-владельце: объект реестра, партия, заказ, сотрудник.
                        Свободные объекты этого модуля привязываются отдельно — в поле или из карточки объекта.
                    </p>
                    <form method="post" action="/qr/bind" class="space-y-3">
                        <input type="hidden" name="token" value="<?php echo HTML::chars($token['code']); ?>">
                        <div>
                            <label for="bind_kind" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Вид цели</label>
                            <select id="bind_kind" name="kind" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <?php foreach ($kinds as $code => $label): ?>
                                    <option value="<?php echo HTML::chars($code); ?>" <?php echo $token['target_kind'] === $code ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php echo Component::render('form_field', array('id' => 'bind_ref', 'label' => 'Код, номер или id', 'name' => 'ref', 'required' => TRUE, 'hint' => 'например номер партии LOT-000123 или код объекта')); ?>
                        <?php echo Component::render('form_field', array('id' => 'bind_comment', 'label' => 'Комментарий', 'name' => 'comment')); ?>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Привязать</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($can_manage): ?>
            <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Состояние наклейки</h2>
                </header>
                <div class="p-5">
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                        Пометьте наклейку, если она потерялась или повреждена: терминал сразу покажет это
                        оператору и не даст выполнить сценарий.
                    </p>
                    <form method="post" action="/qr/status" class="space-y-3">
                        <input type="hidden" name="token" value="<?php echo HTML::chars($token['code']); ?>">
                        <div>
                            <label for="status_value" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Новое состояние</label>
                            <select id="status_value" name="status" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <?php foreach ($states as $code => $label): ?>
                                    <option value="<?php echo HTML::chars($code); ?>" <?php echo $token['status'] === $code ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php echo Component::render('form_field', array('id' => 'status_comment', 'label' => 'Причина', 'name' => 'comment', 'hint' => 'почему наклейку снимают с учёта')); ?>
                        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600">Сохранить</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php if ($photos): ?>
        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Фотографии</h2>
            <div class="flex flex-wrap gap-3">
                <?php foreach ($photos as $photo): ?>
                    <a href="<?php echo HTML::chars($photo['url']); ?>" target="_blank" class="block">
                        <img src="<?php echo HTML::chars($photo['url']); ?>" alt="Фото <?php echo HTML::chars($photo['purpose']); ?>"
                             class="h-28 w-28 rounded-lg border border-gray-200 object-cover dark:border-gray-700" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="mt-6 rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">История наклейки</h2>
        </header>
        <?php
        $rows = array();

        foreach ($activity as $row)
        {
            $details = array();

            foreach ($row['payload'] as $key => $value)
            {
                if (is_scalar($value) AND $value !== '')
                {
                    $details[] = $key.'='.HTML::chars((string) $value);
                }
            }

            $rows[] = array(
                '<span class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars((string) $row['created_at']).'</span>',
                HTML::chars(Arr::get(Service_Erp199_Tokens::instance()->actions(), (string) $row['action_type'], (string) $row['action_type'])),
                $row['scenario_code'] ? '<span class="font-mono text-xs">'.HTML::chars($row['scenario_code']).'</span>' : '—',
                $row['result'] ? Component::render('badge', array('label' => (string) $row['result'], 'state' => $row['result'] === 'ok' ? 'ok' : 'neutral')) : '—',
                HTML::chars((string) $row['comment']).($details ? '<div class="text-xs text-gray-500 dark:text-gray-400">'.implode(' · ', $details).'</div>' : ''),
                $row['user_id'] ? (int) $row['user_id'] : '—',
            );
        }

        echo Component::render('table', array(
            'caption'     => 'История действий по наклейке',
            'columns'     => array(
                array('label' => 'Когда', 'priority' => 2),
                array('label' => 'Действие'),
                array('label' => 'Сценарий', 'priority' => 3),
                array('label' => 'Результат'),
                array('label' => 'Что записано'),
                array('label' => 'Кто', 'priority' => 3),
            ),
            'rows'        => $rows,
            'sticky'      => TRUE,
            'empty_title' => 'Действий ещё не было',
            'empty_hint'  => 'Отсканируйте наклейку — запись появится здесь.',
        ));
        ?>
    </section>
</div>
