<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Представление организационной структуры (дерево).
 *
 * @var  ORM_Result  $units    Плоский список всех подразделений
 * @var  array       $tree     Иерархическое дерево (построенное контроллером)
 * @var  bool        $can_edit Права на редактирование
 * @var  bool        $can_del  Права на удаление
 */
?>

<div>

	<!-- Заголовок и действия -->
	<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
		<div>
			<nav class="flex mb-2" aria-label="Breadcrumb">
				<ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm text-gray-500 dark:text-gray-400">
					<li><a href="<?php echo URL::site('erp230_dictionaries'); ?>" class="hover:text-blue-600">Справочники</a></li>
					<li><i class="fas fa-chevron-right mx-2 text-xs"></i></li>
					<li class="font-bold text-gray-900 dark:text-white">Организации</li>
				</ol>
			</nav>
			<h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
				Организационная структура
			</h1>
		</div>

		<?php if ($can_edit): ?>
		<div class="flex items-center gap-2">
			<button type="button" data-call="openOrgModal"
					class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm">
				<i class="fas fa-plus mr-2"></i> Добавить подразделение
			</button>
		</div>
		<?php endif; ?>
	</div>

	<!-- Контент -->
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
		
		<!-- Левая колонка: Дерево -->
		<div class="lg:col-span-2">
			<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
				<div class="p-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex justify-between items-center">
					<span class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Иерархия (ltree)</span>
					<span class="text-xs text-gray-500">Всего узлов: <?php echo count($units); ?></span>
				</div>
				<div class="p-6">
					<?php if (empty($tree)): ?>
						<div class="text-center py-12 text-gray-400">
							<i class="fas fa-sitemap text-4xl mb-3 block opacity-20"></i>
							Структура пуста
						</div>
					<?php else: ?>
						<div class="org-tree-container">
							<?php 
								$render_node = function($nodes) use (&$render_node, $can_edit, $can_del) {
									echo '<ul class="space-y-2 ml-4 border-l border-gray-200 dark:border-gray-700 pl-4 mt-2">';
									foreach ($nodes as $node) {
										$u = $node['unit'];
										echo '<li class="relative">';
										echo '<div class="flex items-center group py-1">';
										echo '<i class="fas fa-building text-gray-400 mr-2 text-xs"></i>';
										echo '<span class="text-gray-900 dark:text-gray-200 font-medium">' . HTML::chars($u->name) . '</span>';
										echo '<span class="ml-2 text-xs text-gray-400 font-mono">[' . HTML::chars($u->id) . ']</span>';
										
										if ($can_edit) {
											echo '<div class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">';
											echo '<button class="text-blue-500 hover:text-blue-700 p-1" title="Редактировать"><i class="fas fa-edit text-xs"></i></button>';
											if ($can_del && empty($node['children'])) {
												echo '<button class="text-red-500 hover:text-red-700 p-1" title="Удалить"><i class="fas fa-trash text-xs"></i></button>';
											}
											echo '</div>';
										}
										
										echo '</div>';
										if (!empty($node['children'])) {
											$render_node($node['children']);
										}
										echo '</li>';
									}
									echo '</ul>';
								};
								$render_node($tree);
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Правая колонка: Инфо/Справка -->
		<div class="space-y-6">
			<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-5">
				<h3 class="text-blue-800 dark:text-blue-300 font-bold mb-2 flex items-center">
					<i class="fas fa-info-circle mr-2"></i> О структуре
				</h3>
				<p class="text-sm text-blue-700 dark:text-blue-400 leading-relaxed">
					Организационная структура используется для разграничения прав доступа (RLS) и фильтрации данных. 
					Каждый объект в системе привязан к конкретному узлу этой иерархии.
				</p>
				<ul class="mt-3 space-y-1 text-xs text-blue-600 dark:text-blue-500">
					<li><i class="fas fa-check mr-1"></i> <b>ID:</b> Только заглавные латинские буквы и цифры.</li>
					<li><i class="fas fa-check mr-1"></i> <b>Path:</b> Формируется автоматически через ltree.</li>
				</ul>
			</div>

			<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-5">
				<h3 class="text-gray-900 dark:text-white font-bold mb-4">Технические детали</h3>
				<div class="space-y-3">
					<div>
						<label class="text-[10px] uppercase font-bold text-gray-400 block">Таблица БД</label>
						<code class="text-xs text-pink-500">org_units</code>
					</div>
					<div>
						<label class="text-[10px] uppercase font-bold text-gray-400 block">Тип иерархии</label>
						<span class="text-xs text-gray-600 dark:text-gray-400">PostgreSQL LTree (GIST index)</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Простейшая заглушка модального окна (логика добавления реализуется отдельно) -->
<div id="orgModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
	<div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
		<h2 class="text-xl font-bold mb-4 dark:text-white">Новое подразделение</h2>
		<p class="text-sm text-gray-500 mb-6">Функционал создания через UI находится в разработке. Используйте миграции или SQL для наполнения структуры.</p>
		<button data-action="hide-target" data-target="#orgModal" class="w-full py-2 bg-gray-100 dark:bg-gray-700 rounded-lg font-medium">Закрыть</button>
	</div>
</div>

<script>
function openOrgModal() {
	document.getElementById('orgModal').classList.remove('hidden');
}
</script>