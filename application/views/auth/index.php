<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Управление пользователями (только для роли admin).
 * Ожидает: $users — ORM-коллекция Model_User
 * (см. Controller_Erp100_Auth::action_index()).
 */
$users = isset($users) ? $users : [];
?>
<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Пользователи системы</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Всего учётных записей: <?php echo count($users); ?>
            </p>
        </div>
        <div class="flex gap-2">
        <a href="/access/users" class="inline-flex items-center border border-blue-300 text-blue-700 dark:text-blue-300 px-4 py-2 rounded-lg text-sm font-medium"><i class="fas fa-shield-halved mr-2"></i>Роли и области</a>
        <a href="<?php echo URL::site('auth/register'); ?>"
           class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-user-plus mr-2"></i> Добавить пользователя
        </a></div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Пользователи системы</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Пользователь</th>
                        <th scope="col" class="px-4 py-3 font-semibold">E-mail</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Роли</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-center">Статус</th>
                        <th scope="col" class="px-4 py-3 font-semibold text-right">Действия</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if (count($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                                $roles = [];

                                foreach ($user->roles->find_all() as $role)
                                {
                                    $roles[] = $role->name;
                                }
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        <?php echo HTML::chars($user->username); ?>
                                    </div>
                                    <div class="text-[11px] text-gray-400">ID: <?php echo (int) $user->id; ?></div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    <?php echo HTML::chars($user->email); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php foreach ($roles as $role): ?>
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase mr-1
                                                     <?php echo $role === 'admin'
                                                         ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                         : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'; ?>">
                                            <?php echo HTML::chars($role); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if ( ! $roles): ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($user->email_confirmed): ?>
                                        <span class="text-emerald-600 dark:text-emerald-400 text-xs font-medium">
                                            <i class="fas fa-check-circle mr-1"></i> подтверждён
                                        </span>
                                    <?php else: ?>
                                        <span class="text-amber-600 dark:text-amber-400 text-xs font-medium">
                                            <i class="fas fa-clock mr-1"></i> не подтверждён
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="<?php echo URL::site('auth/profile/'.$user->id); ?>"
                                       class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                        Профиль
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">
                                Пользователи не найдены
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
