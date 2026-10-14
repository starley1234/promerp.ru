<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * База знаний: типовые вопросы клиентов и готовые ответы менеджеру.
 *
 * Переменные: rows, term, can_manage, messages.
 */
?>
<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">База знаний</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Ответы на частые вопросы: что сказать про сроки, цены и гарантию. Это шпаргалка менеджера,
            а не переписка с клиентом.
        </p>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <form method="get" action="/crm/kb" class="mb-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-[16rem] flex-1">
                <?php echo Component::render('form_field', array('id' => 'kb_q', 'label' => 'Поиск', 'name' => 'q', 'value' => $term, 'hint' => 'вопрос, ответ или категория')); ?>
            </div>
            <button type="submit" class="mt-6 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-700">Найти</button>
        </div>
    </form>

    <?php if ($can_manage): ?>
        <details class="mb-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">Новая статья</summary>
            <form method="post" action="/crm/kb_save" class="mt-4 grid gap-3 md:grid-cols-2">
                <?php echo Component::render('form_field', array('id' => 'kb_category', 'label' => 'Категория', 'name' => 'category', 'value' => 'general', 'hint' => 'например: цены, сроки, гарантия')); ?>
                <?php echo Component::render('form_field', array('id' => 'kb_tags', 'label' => 'Метки', 'name' => 'tags', 'hint' => 'через запятую')); ?>
                <div class="md:col-span-2">
                    <?php echo Component::render('form_field', array('id' => 'kb_question', 'label' => 'Типовой вопрос', 'name' => 'question_pattern', 'type' => 'textarea')); ?>
                </div>
                <div class="md:col-span-2">
                    <?php echo Component::render('form_field', array('id' => 'kb_answer', 'label' => 'Ответ', 'name' => 'answer_text', 'type' => 'textarea', 'required' => TRUE)); ?>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Сохранить статью</button>
                </div>
            </form>
        </details>
    <?php endif; ?>

    <div class="grid gap-4 md:grid-cols-2">
        <?php if ( ! $rows): ?>
            <p class="text-sm text-gray-500 dark:text-gray-400">Статей по заданным условиям нет.</p>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <article class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400"><?php echo HTML::chars($row['category']); ?></div>
                        <?php if ($row['question_pattern']): ?>
                            <h2 class="mt-1 font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($row['question_pattern']); ?></h2>
                        <?php endif; ?>
                    </div>
                    <?php if ($can_manage): ?>
                        <form method="post" action="/crm/kb_delete" data-confirm="Убрать статью?">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                            <button type="submit" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Убрать</button>
                        </form>
                    <?php endif; ?>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200"><?php echo HTML::chars($row['answer_text']); ?></p>
                <?php if ($row['tags']): ?>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <?php foreach ($row['tags'] as $tag): ?>
                            <?php echo Component::render('badge', array('label' => $tag, 'state' => 'neutral')); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($can_manage): ?>
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs text-blue-600 dark:text-blue-400">Изменить</summary>
                        <form method="post" action="/crm/kb_save" class="mt-3 grid gap-2">
                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                            <?php echo Component::render('form_field', array('id' => 'kb_cat_'.$row['id'], 'label' => 'Категория', 'name' => 'category', 'value' => $row['category'])); ?>
                            <?php echo Component::render('form_field', array('id' => 'kb_q_'.$row['id'], 'label' => 'Вопрос', 'name' => 'question_pattern', 'value' => $row['question_pattern'])); ?>
                            <?php echo Component::render('form_field', array('id' => 'kb_a_'.$row['id'], 'label' => 'Ответ', 'name' => 'answer_text', 'type' => 'textarea', 'value' => $row['answer_text'])); ?>
                            <?php echo Component::render('form_field', array('id' => 'kb_t_'.$row['id'], 'label' => 'Метки', 'name' => 'tags', 'value' => implode(', ', $row['tags']))); ?>
                            <button type="submit" class="mt-6 rounded-lg bg-gray-900 px-3 py-1.5 text-xs text-white dark:bg-gray-700">Сохранить</button>
                        </form>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
