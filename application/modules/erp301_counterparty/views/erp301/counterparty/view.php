<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/** @var Model_Erp301_Counterparty $counterparty */
$input = 'w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm px-3 py-2 border';
$label = 'block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1';
?>

<div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <?php echo HTML::chars($counterparty->name); ?>
                <span class="ml-2 align-middle">
                    <?php echo View::factory('erp301/partials/status_badge')
                        ->set('status', $counterparty->status)
                        ->set('labels', Model_Erp301_Counterparty::$statuses); ?>
                </span>
            </h1>
            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                <span class="font-mono"><?php echo HTML::chars($counterparty->code); ?></span>
                · <?php echo HTML::chars($counterparty->kind_label()); ?>
                · <?php echo HTML::chars($counterparty->roles_label()); ?>
                <?php if ($counterparty->inn): ?>· ИНН <?php echo HTML::chars($counterparty->inn); ?><?php endif; ?>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/counterparties/edit/<?php echo (int) $counterparty->id; ?>"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-pen mr-2"></i> Изменить
            </a>
            <a href="/counterparties/list"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                К списку
            </a>
        </div>
    </div>

    <?php echo View::factory('erp301/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Общие сведения -->
        <div class="lg:col-span-2 space-y-6">

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
                <h2 class="font-medium text-gray-900 dark:text-white mb-4"><i class="fas fa-info-circle mr-2 text-gray-400"></i> Общие сведения</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="<?php echo $label; ?>">Полное наименование</dt>
                        <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($counterparty->full_name ?: '—'); ?></dd>
                    </div>
                    <div>
                        <dt class="<?php echo $label; ?>">Оценка надёжности</dt>
                        <dd class="text-gray-900 dark:text-white"><?php echo (int) $counterparty->rating; ?> / 5</dd>
                    </div>
                    <div>
                        <dt class="<?php echo $label; ?>">КПП / ОГРН</dt>
                        <dd class="text-gray-900 dark:text-white font-mono"><?php echo HTML::chars(trim(($counterparty->kpp ?: '—').' / '.($counterparty->ogrn ?: '—'))); ?></dd>
                    </div>
                    <div>
                        <dt class="<?php echo $label; ?>">Телефон / e-mail</dt>
                        <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars(trim(($counterparty->phone ?: '—').' / '.($counterparty->email ?: '—'))); ?></dd>
                    </div>
                    <div>
                        <dt class="<?php echo $label; ?>">Адрес</dt>
                        <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($counterparty->address ?: '—'); ?></dd>
                    </div>
                    <div>
                        <dt class="<?php echo $label; ?>">Сайт</dt>
                        <dd class="text-gray-900 dark:text-white">
                            <?php if ($counterparty->site): ?>
                                <a class="text-blue-600 hover:underline" target="_blank" rel="noopener" href="<?php echo HTML::chars($counterparty->site); ?>"><?php echo HTML::chars($counterparty->site); ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                    </div>
                    <?php if ($counterparty->note): ?>
                        <div class="md:col-span-2">
                            <dt class="<?php echo $label; ?>">Заметка</dt>
                            <dd class="text-gray-700 dark:text-gray-200"><?php echo nl2br(HTML::chars($counterparty->note)); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php if ( ! empty($printable['lines'])): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-medium">Реквизиты для документов:</span>
                        <?php echo HTML::chars(implode('; ', $printable['lines'])); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Договоры -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="font-medium text-gray-900 dark:text-white"><i class="fas fa-file-signature mr-2 text-gray-400"></i> Договоры (<?php echo count($contracts); ?>)</h2>
                    <a href="/counterparties/contracts/edit?counterparty_id=<?php echo (int) $counterparty->id; ?>"
                       class="text-sm text-blue-600 hover:underline"><i class="fas fa-plus mr-1"></i> Добавить договор</a>
                </div>
                <?php if ( ! count($contracts)): ?>
                    <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Договоры не заведены.</p>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Номер</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Вид</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Срок</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium text-gray-600 dark:text-gray-200">Сумма</th>
                            <th scope="col" class="px-5 py-3 text-left font-medium text-gray-600 dark:text-gray-200">Статус</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium text-gray-600 dark:text-gray-200">Действия</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($contracts as $contract): ?>
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="font-mono text-gray-900 dark:text-white">№ <?php echo HTML::chars($contract->number); ?></div>
                                    <?php if ($contract->title): ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($contract->title); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300"><?php echo HTML::chars($contract->kind_label()); ?></td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                    <?php echo HTML::chars($contract->valid_from ?: '—'); ?> — <?php echo HTML::chars($contract->valid_to ?: '—'); ?>
                                    <?php if ($contract->expires_soon()): ?>
                                        <div class="text-xs text-orange-600 dark:text-orange-400"><i class="fas fa-clock mr-1"></i>истекает</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-right text-gray-900 dark:text-white whitespace-nowrap"><?php echo HTML::chars($contract->amount_label()); ?></td>
                                <td class="px-5 py-3">
                                    <?php echo View::factory('erp301/partials/status_badge')
                                        ->set('status', $contract->status)
                                        ->set('labels', Model_Erp301_Contract::$statuses); ?>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="/counterparties/contracts/edit/<?php echo (int) $contract->id; ?>" title="Изменить" class="text-gray-500 hover:text-blue-600 mr-3"><i class="fas fa-pen"></i></a>
                                    <?php if ( ! in_array($contract->status, array('terminated', 'expired'), TRUE)): ?>
                                        <form method="post" action="/counterparties/contract_terminate" class="inline"
                                              data-confirm="Расторгнуть договор?">
                                            <input type="hidden" name="id" value="<?php echo (int) $contract->id; ?>">
                                            <button type="submit" title="Расторгнуть" class="text-gray-500 hover:text-red-600"><i class="fas fa-ban"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Контакты -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="font-medium text-gray-900 dark:text-white"><i class="fas fa-user-friends mr-2 text-gray-400"></i> Контактные лица (<?php echo count($contacts); ?>)</h2>
                </div>
                <?php if (count($contacts)): ?>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="text-gray-900 dark:text-white">
                                        <?php echo HTML::chars($contact->name); ?>
                                        <?php if ($contact->is_primary): ?>
                                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">основной</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($contact->summary()); ?></div>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <form method="post" action="/counterparties/contact_delete" class="inline"
                                          data-confirm="Удалить контакт?">
                                        <input type="hidden" name="__html" value="1">
                                        <input type="hidden" name="counterparty_id" value="<?php echo (int) $counterparty->id; ?>">
                                        <input type="hidden" name="contact_id" value="<?php echo (int) $contact->id; ?>">
                                        <button type="submit" class="text-gray-500 hover:text-red-600" title="Удалить"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Контактные лица не заведены.</p>
                <?php endif; ?>

                <form method="post" action="/counterparties/contact_save" class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    <input type="hidden" name="__html" value="1">
                    <input type="hidden" name="counterparty_id" value="<?php echo (int) $counterparty->id; ?>">
                    <div>
                        <label class="<?php echo $label; ?>" for="contact_name">Ф. И. О. <span class="text-red-500">*</span></label>
                        <input class="<?php echo $input; ?>" type="text" id="contact_name" name="name" required>
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>" for="contact_position">Должность</label>
                        <input class="<?php echo $input; ?>" type="text" id="contact_position" name="position">
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>" for="contact_phone">Телефон</label>
                        <input class="<?php echo $input; ?>" type="text" id="contact_phone" name="phone">
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>" for="contact_email">E-mail</label>
                        <input class="<?php echo $input; ?>" type="email" id="contact_email" name="email">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="inline-flex items-center text-xs text-gray-600 dark:text-gray-300">
                            <input type="checkbox" name="is_primary" value="1" class="rounded border-gray-300 text-blue-600 mr-1"> основной
                        </label>
                        <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-gray-700 hover:bg-gray-800">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Реквизиты -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="font-medium text-gray-900 dark:text-white"><i class="fas fa-university mr-2 text-gray-400"></i> Реквизиты (<?php echo count($requisites); ?>)</h2>
                </div>

                <?php if (count($requisites)): ?>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <?php foreach ($requisites as $requisite): ?>
                            <li class="px-5 py-3">
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($requisite->kind_label()); ?></div>
                                <div class="text-gray-900 dark:text-white"><?php echo HTML::chars($requisite->summary() ?: '—'); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Реквизиты не заведены.</p>
                <?php endif; ?>

                <form method="post" action="/counterparties/requisite_save" class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                    <input type="hidden" name="__html" value="1">
                    <input type="hidden" name="counterparty_id" value="<?php echo (int) $counterparty->id; ?>">
                    <div>
                        <label class="<?php echo $label; ?>" for="req_kind">Вид реквизитов</label>
                        <select class="<?php echo $input; ?>" id="req_kind" name="kind">
                            <?php foreach (Model_Erp301_Requisite::$kinds as $code => $title): ?>
                                <option value="<?php echo HTML::chars($code); ?>"><?php echo HTML::chars($title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>" for="req_bank">Банк</label>
                        <input class="<?php echo $input; ?>" type="text" id="req_bank" name="bank_name">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="<?php echo $label; ?>" for="req_bik">БИК</label>
                            <input class="<?php echo $input; ?>" type="text" id="req_bik" name="bik" maxlength="9">
                        </div>
                        <div>
                            <label class="<?php echo $label; ?>" for="req_account">Счёт</label>
                            <input class="<?php echo $input; ?>" type="text" id="req_account" name="account" maxlength="20">
                        </div>
                    </div>
                    <div>
                        <label class="<?php echo $label; ?>" for="req_address">Адрес</label>
                        <textarea class="<?php echo $input; ?>" id="req_address" name="address" rows="2"></textarea>
                    </div>
                    <button type="submit" class="w-full inline-flex justify-center items-center px-3 py-2 text-sm font-medium rounded-md text-white bg-gray-700 hover:bg-gray-800">
                        <i class="fas fa-plus mr-2"></i> Добавить реквизиты
                    </button>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5 text-sm text-gray-500 dark:text-gray-400">
                <div class="font-medium text-gray-700 dark:text-gray-200 mb-2"><i class="fas fa-code mr-2"></i> API</div>
                <div class="font-mono text-xs break-all">/counterparties/api/card/<?php echo (int) $counterparty->id; ?></div>
                <div class="font-mono text-xs break-all mt-1">/counterparties/api/suggest?term=…</div>
            </div>
        </div>
    </div>
</div>
