<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Реестр Golden Records (MDM) — список объектов.
 *
 * @var  mixed  $items    Collection|array объектов Model_Erp230_Object
 * @var  int    $total    Всего записей (для пагинации)
 * @var  array  $filters  Текущие фильтры (type_id, org_id, state, search, is_dirty, page, limit)
 * @var  int    $pages    Всего страниц
 * @var  array  $types    Справочник типов (ORM|array)
 * @var  array  $orgs     Справочник оргединиц (ORM|array)
 */

$filters = isset($filters) ? $filters : [];
$total   = isset($total) ? (int) $total : 0;
$pages   = isset($pages) ? (int) $pages : 1;
$page    = isset($filters['page']) ? (int) $filters['page'] : 1;
$limit   = isset($filters['limit']) ? (int) $filters['limit'] : 20;

// Хелпер: собрать URL с изменённым набором query-параметров.
$build_url = function (array $overrides = []) use ($filters) {
	$params = array_filter(array_merge($filters, $overrides), function ($v) {
		return $v !== NULL && $v !== '';
	});
	return URL::site('erp230_objects').(empty($params) ? '' : '?'.http_build_query($params));
};

// Бейджи состояний.
$state_badges = [
	'active'   => ['label' => 'Активен',   'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
	'draft'    => ['label' => 'Черновик',  'cls' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'],
	'archived' => ['label' => 'В архиве',  'cls' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300'],
	'merged'   => ['label' => 'Объединён', 'cls' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'],
];
?>

<div>

	<!-- Заголовок + действия -->
	<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
		<div>
			<h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center">
				<i class="fas fa-database text-blue-600 dark:text-blue-400 mr-3"></i>
				Реестр Golden Records
			</h1>
			<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
				Найдено записей: <span class="font-semibold text-gray-700 dark:text-gray-200"><?php echo $total; ?></span>
			</p>
		</div>
		<div class="flex items-center gap-2">
			<a href="<?php echo URL::site('erp230_objects/create'); ?>"
			   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm">
				<i class="fas fa-plus mr-2"></i> Новый объект
			</a>
		</div>
	</div>

	<!-- Семантический поиск (AJAX) -->
	<div class="mb-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-4">
		<label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">
			<i class="fas fa-wand-magic-sparkles mr-1"></i> Семантический поиск
		</label>
		<div class="flex gap-2">
			<input type="text" id="semantic-query"
			       placeholder="Опишите изделие своими словами…"
			       class="flex-1 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
			<button id="semantic-btn"
			        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
				<i class="fas fa-search mr-2"></i> Найти
			</button>
		</div>
		<div id="semantic-results" class="mt-3 hidden"></div>
	</div>

	<!-- Фильтры -->
	<form method="get" action="<?php echo URL::site('erp230_objects'); ?>"
	      class="mb-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-4">
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

			<!-- Поиск по коду -->
			<div class="lg:col-span-2">
				<label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Поиск (код / текст)</label>
				<input type="text" name="search"
				       value="<?php echo HTML::chars(Arr::get($filters, 'search', '')); ?>"
				       placeholder="Мастер-код или ключевые слова"
				       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
			</div>

			<!-- Тип -->
			<div>
				<label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Тип</label>
				<select name="type_id"
				        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
					<option value="">— все типы —</option>
					<?php foreach ((array) $types as $t): ?>
						<?php
							$tid   = is_object($t) ? $t->id : Arr::get($t, 'id');
							$tname = is_object($t) ? (isset($t->display_name) ? $t->display_name : $t->id) : Arr::get($t, 'display_name', $tid);
						?>
						<option value="<?php echo HTML::chars($tid); ?>"
							<?php echo (Arr::get($filters, 'type_id') == $tid) ? 'selected' : ''; ?>>
							<?php echo HTML::chars($tname); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Организация -->
			<div>
				<label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Организация</label>
				<select name="org_id"
				        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
					<option value="">— все —</option>
					<?php foreach ((array) $orgs as $o): ?>
						<?php
							$oid   = is_object($o) ? $o->id : Arr::get($o, 'id');
							$oname = is_object($o) ? (isset($o->name) ? $o->name : $o->id) : Arr::get($o, 'name', $oid);
						?>
						<option value="<?php echo HTML::chars($oid); ?>"
							<?php echo (Arr::get($filters, 'org_id') == $oid) ? 'selected' : ''; ?>>
							<?php echo HTML::chars($oname); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Состояние -->
			<div>
				<label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Состояние</label>
				<select name="state"
				        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
					<?php
						$cur_state = Arr::get($filters, 'state', 'active');
						$state_opts = ['' => '— любое —', 'active' => 'Активные', 'draft' => 'Черновики', 'archived' => 'Архив', 'merged' => 'Объединённые'];
					?>
					<?php foreach ($state_opts as $val => $label): ?>
						<option value="<?php echo $val; ?>" <?php echo ($cur_state === $val) ? 'selected' : ''; ?>>
							<?php echo $label; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="flex items-center justify-between mt-4">
			<label class="inline-flex items-center text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
				<input type="checkbox" name="is_dirty" value="1"
				       <?php echo Arr::get($filters, 'is_dirty') ? 'checked' : ''; ?>
				       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
				Только требующие консолидации (dirty)
			</label>
			<div class="flex gap-2">
				<a href="<?php echo URL::site('erp230_objects'); ?>"
				   class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
					Сбросить
				</a>
				<button type="submit"
				        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors">
					<i class="fas fa-filter mr-2"></i> Применить
				</button>
			</div>
		</div>
	</form>

	<!-- Таблица -->
	<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
				<thead class="bg-gray-50 dark:bg-gray-900/50">
					<tr>
						<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Мастер-код</th>
						<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Тип</th>
						<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Состояние</th>
						<th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dirty</th>
						<th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Обновлён</th>
						<th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Действия</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
					<?php if (empty($items) OR (is_object($items) && count($items) === 0)): ?>
						<tr>
							<td colspan="6" class="px-4 py-12 text-center text-gray-400">
								<i class="fas fa-inbox text-4xl mb-3 block opacity-40"></i>
								Записи не найдены. Измените фильтры или создайте новый объект.
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($items as $obj): ?>
							<?php
								$state = isset($obj->state) ? $obj->state : '';
								$badge = Arr::get($state_badges, $state, ['label' => $state, 'cls' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300']);
								$type_name = ($obj->type && $obj->type->loaded())
									? (isset($obj->type->display_name) ? $obj->type->display_name : $obj->type_id)
									: $obj->type_id;
							?>
							<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
								<td class="px-4 py-3">
									<a href="<?php echo URL::site('erp230_objects/view/'.$obj->id); ?>"
									   class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
										<?php echo HTML::chars($obj->master_code); ?>
									</a>
									<div class="text-[11px] text-gray-400 font-mono truncate max-w-[180px]"><?php echo HTML::chars($obj->id); ?></div>
								</td>
								<td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?php echo HTML::chars($type_name); ?></td>
								<td class="px-4 py-3">
									<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge['cls']; ?>">
										<?php echo HTML::chars($badge['label']); ?>
									</span>
								</td>
								<td class="px-4 py-3 text-center">
									<?php if ( ! empty($obj->is_dirty)): ?>
										<span title="Требует консолидации" class="inline-block w-2.5 h-2.5 rounded-full bg-orange-500"></span>
									<?php else: ?>
										<span title="Синхронизирован" class="inline-block w-2.5 h-2.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
									<?php endif; ?>
								</td>
								<td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
									<?php echo HTML::chars(isset($obj->updated_at) ? $obj->updated_at : '—'); ?>
								</td>
								<td class="px-4 py-3 text-right whitespace-nowrap">
									<a href="<?php echo URL::site('erp230_objects/view/'.$obj->id); ?>"
									   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/30 transition-colors" title="Просмотр">
										<i class="fas fa-eye"></i>
									</a>
									<a href="<?php echo URL::site('erp230_objects/edit/'.$obj->id); ?>"
									   class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-900/30 transition-colors" title="Редактировать">
										<i class="fas fa-pen"></i>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Пагинация -->
	<?php if ($pages > 1): ?>
		<nav class="flex items-center justify-between mt-6">
			<span class="text-sm text-gray-500 dark:text-gray-400">
				Страница <?php echo $page; ?> из <?php echo $pages; ?>
			</span>
			<div class="flex items-center gap-1">
				<?php if ($page > 1): ?>
					<a href="<?php echo $build_url(['page' => $page - 1]); ?>"
					   class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
						<i class="fas fa-chevron-left"></i>
					</a>
				<?php endif; ?>

				<?php
					$start = max(1, $page - 2);
					$end   = min($pages, $page + 2);
				?>
				<?php if ($start > 1): ?>
					<a href="<?php echo $build_url(['page' => 1]); ?>" class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">1</a>
					<?php if ($start > 2): ?><span class="px-2 text-gray-400">…</span><?php endif; ?>
				<?php endif; ?>

				<?php for ($i = $start; $i <= $end; $i++): ?>
					<a href="<?php echo $build_url(['page' => $i]); ?>"
					   class="px-3.5 py-2 text-sm rounded-lg border transition-colors <?php echo ($i === $page)
						   ? 'bg-blue-600 border-blue-600 text-white'
						   : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'; ?>">
						<?php echo $i; ?>
					</a>
				<?php endfor; ?>

				<?php if ($end < $pages): ?>
					<?php if ($end < $pages - 1): ?><span class="px-2 text-gray-400">…</span><?php endif; ?>
					<a href="<?php echo $build_url(['page' => $pages]); ?>" class="px-3.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"><?php echo $pages; ?></a>
				<?php endif; ?>

				<?php if ($page < $pages): ?>
					<a href="<?php echo $build_url(['page' => $page + 1]); ?>"
					   class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
						<i class="fas fa-chevron-right"></i>
					</a>
				<?php endif; ?>
			</div>
		</nav>
	<?php endif; ?>

</div>

<script>
(function () {
	var $q   = $('#semantic-query');
	var $btn = $('#semantic-btn');
	var $box = $('#semantic-results');

	function runSearch() {
		var q = $.trim($q.val());
		if (q === '') { $box.addClass('hidden').empty(); return; }

		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Поиск…');

		$.getJSON('<?php echo URL::site('erp230_objects/search'); ?>', { q: q, limit: 10 })
			.done(function (data) {
				if (data.status !== 'ok' || !data.results || data.results.length === 0) {
					$box.removeClass('hidden').html(
						'<div class="text-sm text-gray-400 py-2"><i class="fas fa-circle-info mr-1"></i> Ничего не найдено.</div>'
					);
					return;
				}
				var html = '<div class="space-y-1 border-t border-gray-100 dark:border-gray-700 pt-3">';
				data.results.forEach(function (r) {
					html += '<a href="<?php echo URL::site('erp230_objects/view/'); ?>' + encodeURIComponent(r.id) + '" ' +
						'class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">' +
						'<span class="text-sm font-medium text-blue-600 dark:text-blue-400">' + $('<div>').text(r.code).html() + '</span>' +
						'<span class="text-xs text-gray-400">' + $('<div>').text(r.type || '').html() +
						' · ' + r.distance + '</span></a>';
				});
				html += '</div>';
				$box.removeClass('hidden').html(html);
			})
			.fail(function (xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Ошибка поиска';
				$box.removeClass('hidden').html('<div class="text-sm text-red-500 py-2"><i class="fas fa-triangle-exclamation mr-1"></i> ' + msg + '</div>');
				if (window.showNotification) showNotification(msg, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false).html('<i class="fas fa-search mr-2"></i> Найти');
			});
	}

	$btn.on('click', runSearch);
	$q.on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); runSearch(); } });
})();
</script>