<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Список типов объектов MDM (Иерархия).
 * 
 * @var  Model_Erp230_Type[]  $types  Коллекция типов, отсортированная по ltree path
 */
?>

<div>

	<!-- Заголовок -->
	<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
		<div>
			<h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center">
				<i class="fas fa-sitemap text-indigo-600 dark:text-indigo-400 mr-3"></i>
				Типы объектов MDM
			</h1>
			<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
				Управление иерархией и JSON-схемами атрибутов
			</p>
		</div>
		<div class="flex items-center gap-2">
			<a href="<?php echo URL::site('erp230_types/create'); ?>"
			   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm">
				<i class="fas fa-plus mr-2"></i> Создать тип
			</a>
		</div>
	</div>

	<!-- Дерево типов -->
	<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
				<thead class="bg-gray-50 dark:bg-gray-900/50">
					<tr>
						<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Наименование типа</th>
						<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Системный ID</th>
						<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Путь (ltree)</th>
						<th scope="col" class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Схема</th>
						<th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
					<?php if (count($types) === 0): ?>
						<tr>
							<td colspan="5" class="px-6 py-12 text-center text-gray-400">
								<i class="fas fa-folder-open text-4xl mb-3 block opacity-40"></i>
								Типы еще не созданы.
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($types as $type): ?>
							<?php 
								// Вычисляем отступ на основе уровня вложенности ltree (количество точек в пути)
								$level = substr_count($type->path, '.');
								$padding = $level * 1.5;
							?>
							<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
								<td class="px-6 py-4 whitespace-nowrap">
									<div class="flex items-center" data-indent="<?php echo (float) $padding * 16; ?>">
										<?php if ($level > 0): ?>
											<i class="fas fa-angle-right text-gray-300 mr-2"></i>
										<?php endif; ?>
										<i class="fas <?php echo ($level === 0) ? 'fa-folder text-amber-500' : 'fa-file-code text-blue-400'; ?> mr-2"></i>
										<a href="<?php echo URL::site('erp230_types/view/'.$type->id); ?>" 
										   class="text-sm font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
											<?php echo HTML::chars($type->display_name); ?>
										</a>
									</div>
								</td>
								<td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-gray-400">
									<?php echo HTML::chars($type->id); ?>
								</td>
								<td class="px-6 py-4 whitespace-nowrap text-xs text-gray-400 font-mono">
									<?php echo HTML::chars($type->path); ?>
								</td>
								<td class="px-6 py-4 whitespace-nowrap text-center">
									<?php if (!empty($type->schema) && $type->schema !== '{}'): ?>
										<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
											<i class="fas fa-check-circle mr-1"></i> JSON
										</span>
									<?php else: ?>
										<span class="text-gray-300 dark:text-gray-600">—</span>
									<?php endif; ?>
								</td>
								<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
									<a href="<?php echo URL::site('erp230_types/view/'.$type->id); ?>" 
									   class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 mr-3" title="Редактировать">
										<i class="fas fa-edit"></i>
									</a>
									<a href="<?php echo URL::site('erp230_objects?type_id='.$type->id); ?>" 
									   class="text-gray-400 hover:text-blue-500 transition-colors" title="Показать объекты этого типа">
										<i class="fas fa-external-link-alt"></i>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Инфо-панель -->
	<div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
		<div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-lg">
			<h3 class="text-blue-800 dark:text-blue-300 font-semibold text-sm mb-1">Иерархия ltree</h3>
			<p class="text-xs text-blue-600 dark:text-blue-400 leading-relaxed">
				Типы организованы в дерево с помощью расширения PostgreSQL ltree. Это позволяет выполнять быстрые запросы по всей ветке наследования.
			</p>
		</div>
		<div class="p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-100 dark:border-purple-800 rounded-lg">
			<h3 class="text-purple-800 dark:text-purple-300 font-semibold text-sm mb-1">JSON Schema</h3>
			<p class="text-xs text-purple-600 dark:text-purple-400 leading-relaxed">
				Каждый тип может иметь свою схему валидации атрибутов. Объекты проверяются на соответствие схеме при сохранении.
			</p>
		</div>
		<div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-lg">
			<h3 class="text-amber-800 dark:text-amber-300 font-semibold text-sm mb-1">Наследование</h3>
			<p class="text-xs text-amber-600 dark:text-amber-400 leading-relaxed">
				Дочерние типы наследуют базовые свойства родительских типов, расширяя их специфичными для конкретной категории атрибутами.
			</p>
		</div>
	</div>
</div>