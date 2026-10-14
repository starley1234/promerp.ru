<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Список источников данных MDM.
 * 
 * @var array $items Список источников со статистикой (id, name, kind, trust, usage)
 */
?>

<div>

	<!-- Заголовок -->
	<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
		<div>
			<h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center">
				<i class="fas fa-database text-emerald-600 dark:text-emerald-400 mr-3"></i>
				Источники данных
			</h1>
			<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
				Управление доверием (Trust Score) и мониторинг использования в MDM
			</p>
		</div>
		<div class="flex items-center gap-2">
			<a href="<?php echo URL::site('erp230_sources/create'); ?>"
			   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-sm">
				<i class="fas fa-plus mr-2"></i> Добавить источник
			</a>
		</div>
	</div>

	<!-- Таблица источников -->
	<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
				<thead class="bg-gray-50 dark:bg-gray-900/50">
					<tr>
						<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Источник</th>
						<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Тип</th>
						<th scope="col" class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trust Score</th>
						<th scope="col" class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Использование</th>
						<th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
					<?php if (empty($items)): ?>
						<tr>
							<td colspan="5" class="px-6 py-12 text-center text-gray-400">
								<i class="fas fa-server text-4xl mb-3 block opacity-40"></i>
								Источники данных не найдены.
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($items as $item): ?>
							<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
								<td class="px-6 py-4 whitespace-nowrap">
									<div class="flex items-center">
										<div class="flex-shrink-0 h-8 w-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400">
											<i class="fas fa-plug text-xs"></i>
										</div>
										<div class="ml-4">
											<div class="text-sm font-bold text-gray-900 dark:text-white">
												<a href="<?php echo URL::site('erp230_sources/view/'.$item['id']); ?>" class="hover:text-emerald-600 transition-colors">
													<?php echo HTML::chars($item['name'] ?: $item['id']); ?>
												</a>
											</div>
											<div class="text-xs text-gray-500 font-mono"><?php echo HTML::chars($item['id']); ?></div>
										</div>
									</div>
								</td>
								<td class="px-6 py-4 whitespace-nowrap">
									<span class="px-2 py-1 text-xs font-medium rounded-md bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
										<?php echo HTML::chars($item['kind']); ?>
									</span>
								</td>
								<td class="px-6 py-4 whitespace-nowrap text-center">
									<div class="flex items-center justify-center">
										<div class="w-12 text-sm font-bold <?php 
											echo $item['trust'] >= 80 ? 'text-emerald-600' : ($item['trust'] >= 40 ? 'text-amber-600' : 'text-rose-600'); 
										?>">
											<?php echo $item['trust']; ?>
										</div>
										<div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 ml-2 hidden sm:block">
											<div class="h-1.5 rounded-full <?php 
												echo $item['trust'] >= 80 ? 'bg-emerald-500' : ($item['trust'] >= 40 ? 'bg-amber-500' : 'bg-rose-500'); 
											?>" data-progress="<?php echo $item['trust']; ?>"></div>
										</div>
									</div>
								</td>
								<td class="px-6 py-4 whitespace-nowrap text-center">
									<span class="text-sm font-medium text-gray-900 dark:text-white">
										<?php echo number_format($item['usage'], 0, '.', ' '); ?>
									</span>
									<span class="text-xs text-gray-400 ml-1">знач.</span>
								</td>
								<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
									<a href="<?php echo URL::site('erp230_sources/view/'.$item['id']); ?>" 
									   class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 mr-3 transition-colors" title="Просмотр">
										<i class="fas fa-eye"></i>
									</a>
									<a href="<?php echo URL::site('erp230_sources/edit/'.$item['id']); ?>" 
									   class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors" title="Редактировать">
										<i class="fas fa-edit"></i>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Пояснение -->
	<div class="mt-8 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-xl flex items-start">
		<div class="flex-shrink-0 text-amber-500 mt-0.5">
			<i class="fas fa-info-circle text-lg"></i>
		</div>
		<div class="ml-3">
			<h3 class="text-sm font-bold text-amber-800 dark:text-amber-300">Оценка доверия (Trust Score)</h3>
			<p class="text-xs text-amber-700 dark:text-amber-400 mt-1 leading-relaxed">
				Балл доверия определяет приоритет источника при консолидации данных (Golden Record). 
				Если для одного атрибута объекта есть значения из разных источников, система выберет значение от источника с наибольшим баллом. 
				Изменение баллов может привести к пересчету текущих значений объектов.
			</p>
		</div>
	</div>
</div>