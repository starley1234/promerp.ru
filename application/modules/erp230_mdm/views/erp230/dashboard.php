<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Дашборд модуля MDM (ERP230).
 *
 * @var  array  $stats       Сводные метрики (total, active, draft, archived, merged, dirty, types, orgs)
 * @var  array  $dirty       Последние «грязные» записи (см. _object_digest)
 * @var  array  $recent      Последние изменённые активные записи
 * @var  bool   $can_create  Доступна ли кнопка создания
 */

$stats      = isset($stats) ? $stats : [];
$dirty      = isset($dirty) ? $dirty : [];
$recent     = isset($recent) ? $recent : [];
$can_create = ! empty($can_create);

$val = function ($key) use ($stats) {
	return (int) Arr::get($stats, $key, 0);
};

// Бейджи состояний (те же, что в list.php).
$state_badges = [
	'active'   => ['label' => 'Активен',   'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
	'draft'    => ['label' => 'Черновик',  'cls' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'],
	'archived' => ['label' => 'В архиве',  'cls' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300'],
	'merged'   => ['label' => 'Объединён', 'cls' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'],
];

// Карточки-метрики: ключ статы => оформление.
$metric_cards = [
	['key' => 'total',    'label' => 'Всего записей', 'icon' => 'fa-database',       'color' => 'blue'],
	['key' => 'active',   'label' => 'Активные',      'icon' => 'fa-circle-check',    'color' => 'green'],
	['key' => 'dirty',    'label' => 'Требуют консолидации', 'icon' => 'fa-triangle-exclamation', 'color' => 'orange'],
	['key' => 'draft',    'label' => 'Черновики',     'icon' => 'fa-pen-ruler',       'color' => 'gray'],
	['key' => 'merged',   'label' => 'Объединённые',  'icon' => 'fa-code-merge',      'color' => 'purple'],
	['key' => 'archived', 'label' => 'В архиве',      'icon' => 'fa-box-archive',     'color' => 'yellow'],
];

$color_map = [
	'blue'   => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30',
	'green'  => 'text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30',
	'orange' => 'text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/30',
	'gray'   => 'text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700/50',
	'purple' => 'text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30',
	'yellow' => 'text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/30',
];
?>

<div>

	<!-- Заголовок + быстрые действия -->
	<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
		<div>
			<h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center">
				<i class="fas fa-gauge-high text-blue-600 dark:text-blue-400 mr-3"></i>
				MDM — панель управления
			</h1>
			<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
				Единый реестр мастер-данных и цифровых двойников
			</p>
		</div>
		<div class="flex items-center gap-2">
			<a href="<?php echo URL::site('erp230_objects'); ?>"
			   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
				<i class="fas fa-database mr-2"></i> Реестр
			</a>
			<?php if ($can_create): ?>
				<a href="<?php echo URL::site('erp230_objects/create'); ?>"
				   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm">
					<i class="fas fa-plus mr-2"></i> Новый объект
				</a>
			<?php endif; ?>
		</div>
	</div>

	<!-- Метрики -->
	<div id="metrics-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
		<?php foreach ($metric_cards as $card): ?>
			<?php $cls = Arr::get($color_map, $card['color'], $color_map['gray']); ?>
			<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-4">
				<div class="flex items-center justify-between mb-2">
					<span class="inline-flex items-center justify-center w-9 h-9 rounded-lg <?php echo $cls; ?>">
						<i class="fas <?php echo $card['icon']; ?>"></i>
					</span>
				</div>
				<div class="text-2xl font-extrabold text-gray-900 dark:text-white tabular-nums"
				     data-metric="<?php echo $card['key']; ?>">
					<?php echo $val($card['key']); ?>
				</div>
				<div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?php echo $card['label']; ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Справочники: мини-статистика -->
	<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
		<a href="<?php echo URL::site('erp230_dictionaries'); ?>"
		   class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-4 hover:border-blue-300 dark:hover:border-blue-700 transition-colors">
			<span class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-200">
				<i class="fas fa-sitemap text-indigo-500 mr-3"></i> Типы изделий
			</span>
			<span class="text-lg font-bold text-gray-900 dark:text-white tabular-nums"><?php echo $val('types'); ?></span>
		</a>
		<a href="<?php echo URL::site('erp230_dictionaries'); ?>"
		   class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-4 hover:border-blue-300 dark:hover:border-blue-700 transition-colors">
			<span class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-200">
				<i class="fas fa-building text-teal-500 mr-3"></i> Справочники
			</span>
			<span class="text-lg font-bold text-gray-900 dark:text-white tabular-nums"><?php echo $val('orgs'); ?></span>
		</a>
	</div>

	<!-- Две колонки: dirty + recent -->
	<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

		<!-- Требуют консолидации -->
		<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
			<div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
				<h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center">
					<span class="inline-block w-2.5 h-2.5 rounded-full bg-orange-500 mr-2"></span>
					Требуют консолидации
				</h2>
				<a href="<?php echo URL::site('erp230_objects').'?is_dirty=1'; ?>"
				   class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Все →</a>
			</div>
			<?php 
				echo View::factory('erp230/partials/object_rows')
				->set('rows', $dirty)
				->set('state_badges', $state_badges)
				->set('empty_text', 'Нет записей, требующих консолидации.'); 
			?>
		</div>

		<!-- Недавно изменённые -->
		<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
			<div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
				<h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center">
					<i class="fas fa-clock-rotate-left text-blue-500 mr-2"></i>
					Недавно изменённые
				</h2>
				<a href="<?php echo URL::site('erp230_objects'); ?>"
				   class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Все →</a>
			</div>
			<?php echo View::factory('erp230/partials/object_rows')
				->set('rows', $recent)
				->set('state_badges', $state_badges)
				->set('empty_text', 'Пока нет изменённых записей.'); ?>
		</div>

	</div>

</div>

<script>
(function () {
	// Автообновление метрик каждые 60с через /erp230_index/stats.
	var STATS_URL = '<?php echo URL::site('erp230_index/stats'); ?>';

	function refreshStats() {
		if (document.hidden) return; // не дёргаем в фоновой вкладке
		$.getJSON(STATS_URL)
			.done(function (data) {
				if (data.status !== 'ok' || !data.stats) return;
				$('#metrics-grid [data-metric]').each(function () {
					var key = $(this).data('metric');
					if (data.stats[key] !== undefined) {
						$(this).text(data.stats[key]);
					}
				});
			});
	}

	setInterval(refreshStats, 60000);
})();
</script>