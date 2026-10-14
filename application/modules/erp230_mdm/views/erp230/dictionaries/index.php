<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Хаб справочников MDM.
 *
 * Единая точка входа для перехода к отдельным справочникам модуля
 * Master Data Management: типы изделий, единицы измерения, организации,
 * источники данных.
 *
 * @var  array  $sections  Разделы справочников:
 *                          [ ['title', 'description', 'href', 'icon', 'count'], ... ]
 */

$sections = isset($sections) ? $sections : [];
?>

<div>

	<!-- Заголовок -->
	<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
		<div>
			<h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center">
				<i class="fas fa-book text-blue-600 dark:text-blue-400 mr-3"></i>
				Справочники MDM
			</h1>
			<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
				Управление мастер-данными: типы, единицы измерения, организации, источники
			</p>
		</div>
		<div class="flex items-center gap-2">
			<a href="<?php echo URL::site('erp230'); ?>"
			   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
				<i class="fas fa-gauge-high mr-2"></i> Панель управления
			</a>
		</div>
	</div>

	<?php if (empty($sections)): ?>
		<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-12 text-center text-gray-400">
			<i class="fas fa-inbox text-4xl mb-3 block opacity-40"></i>
			Нет доступных справочников.
		</div>
	<?php else: ?>
		<!-- Карточки разделов -->
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
			<?php foreach ($sections as $section): ?>
				<?php
					$title       = Arr::get($section, 'title', '');
					$description = Arr::get($section, 'description', '');
					$href        = Arr::get($section, 'href', '#');
					$icon        = Arr::get($section, 'icon', 'fas fa-folder');
					$count       = (int) Arr::get($section, 'count', 0);
				?>
				<a href="<?php echo HTML::chars($href); ?>"
				   class="group flex flex-col bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-5 hover:border-blue-400 dark:hover:border-blue-600 hover:shadow-md transition-all">
					<div class="flex items-start justify-between mb-4">
						<span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xl group-hover:scale-105 transition-transform">
							<i class="<?php echo HTML::chars($icon); ?>"></i>
						</span>
						<span class="inline-flex items-center justify-center min-w-[2.5rem] px-2.5 py-1 rounded-full text-sm font-bold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 tabular-nums">
							<?php echo $count; ?>
						</span>
					</div>
					<h2 class="text-base font-semibold text-gray-900 dark:text-white mb-1 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
						<?php echo HTML::chars($title); ?>
					</h2>
					<p class="text-sm text-gray-500 dark:text-gray-400 flex-1">
						<?php echo HTML::chars($description); ?>
					</p>
					<div class="mt-4 flex items-center text-sm font-medium text-blue-600 dark:text-blue-400">
						Перейти
						<i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

</div>