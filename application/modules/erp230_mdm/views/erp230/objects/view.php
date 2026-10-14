<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Карточка Golden Record (MDM).
 *
 * @var  Model_Erp230_Object  $object      Загруженный объект
 * @var  mixed                $properties  Текущие свойства (is_current = TRUE)
 * @var  string|NULL          $name        Человекочитаемое имя объекта
 * @var  bool                 $is_valid    Валидна ли hash-chain объекта
 * @var  bool                 $can_edit    Право на редактирование
 * @var  bool                 $can_verify  Право на запуск проверки целостности
 */

$can_edit   = isset($can_edit) ? (bool) $can_edit : FALSE;
$can_verify = isset($can_verify) ? (bool) $can_verify : FALSE;
$is_valid   = isset($is_valid) ? (bool) $is_valid : NULL;
$name       = isset($name) ? $name : NULL;

// Бейджи состояний (те же, что в реестре).
$state_badges = [
	'active'   => ['label' => 'Активен',   'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
	'draft'    => ['label' => 'Черновик',  'cls' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'],
	'archived' => ['label' => 'В архиве',  'cls' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300'],
	'merged'   => ['label' => 'Объединён', 'cls' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'],
];
$state = isset($object->state) ? $object->state : '';
$badge = Arr::get($state_badges, $state, ['label' => $state, 'cls' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300']);

$type_name = ($object->type && $object->type->loaded())
	? (isset($object->type->display_name) ? $object->type->display_name : $object->type_id)
	: $object->type_id;

$org_name = (isset($object->org) && $object->org->loaded())
	? (isset($object->org->name) ? $object->org->name : $object->org_id)
	: (isset($object->org_id) ? $object->org_id : '—');

// CSRF-токен для форм (verify / delete).
$csrf = class_exists('Security') ? Security::token() : '';

/**
 * Декодирование jsonb-значения свойства (аналог Api::_decode()).
 */
if ( ! function_exists('erp230_decode_prop_value'))
{
	function erp230_decode_prop_value($raw)
	{
		if ($raw === NULL OR $raw === '')
			return '';

		if ( ! is_string($raw))
		{
			if (is_bool($raw))
				return $raw ? 'да' : 'нет';
			if (is_array($raw) OR is_object($raw))
				return json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			return (string) $raw;
		}

		$decoded = json_decode($raw, TRUE);

		if (json_last_error() === JSON_ERROR_NONE)
		{
			if (is_bool($decoded))
				return $decoded ? 'да' : 'нет';
			if (is_null($decoded))
				return '';
			if (is_array($decoded))
				return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			return (string) $decoded;
		}

		return $raw;
	}
}
?>

<div>

	<!-- Навигация назад -->
	<div class="mb-4">
		<a href="<?php echo URL::site('erp230_objects'); ?>"
		   class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
			<i class="fas fa-arrow-left mr-2"></i> К реестру
		</a>
	</div>

	<!-- Шапка карточки -->
	<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-6 mb-6">
		<div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
			<div class="min-w-0">
				<div class="flex items-center flex-wrap gap-3 mb-2">
					<h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight break-all">
						<i class="fas fa-cube text-blue-600 dark:text-blue-400 mr-2"></i>
						<?php echo HTML::chars($name ?: $object->master_code); ?>
					</h1>
					<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge['cls']; ?>">
						<?php echo HTML::chars($badge['label']); ?>
					</span>
					<?php if (( ! empty($object->is_dirty)) AND (!$object->is_dirty)): ?>
						<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">
							<i class="fas fa-triangle-exclamation mr-1"></i> Требует консолидации
						</span>
					<?php endif; ?>
				</div>
				<?php if ($name): ?>
					<p class="text-sm text-gray-500 dark:text-gray-400 font-mono break-all">
						Код: <?php echo HTML::chars($object->master_code); ?>
					</p>
				<?php endif; ?>
				<p class="text-sm text-gray-500 dark:text-gray-400 font-mono break-all">
					ID: <?php echo HTML::chars($object->id); ?>
				</p>
			</div>

			<!-- Действия -->
			<div class="flex items-center gap-2 shrink-0 no-print">
				<?php if ($can_edit): ?>
					<?php if (( ! empty($object->is_dirty)) AND (!$object->is_dirty)): ?>
						<form action="<?php echo URL::site('erp230_objects/snapshot/'.$object->id); ?>" method="post" class="inline">
							<button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-orange-600 hover:bg-orange-700 transition-colors shadow-sm">
								<i class="fas fa-camera mr-2"></i> Создать снимок
							</button>
						</form>
					<?php endif; ?>

					<a href="<?php echo URL::site('erp230_objects/edit/'.$object->id); ?>"
					   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-sm">
						<i class="fas fa-pen mr-2"></i> Редактировать
					</a>
                
                	<?php if ($object->state === 'draft'): ?>
						<form action="<?php echo URL::site('erp230_objects/state/'.$object->id); ?>" method="post" class="inline">
							<input type="hidden" name="state" value="active">
							<button type="submit" class="bg-green-600 text-white px-4 py-2 rounded shadow">
								<i class="fas fa-check-circle mr-2"></i> Утвердить (Сделать Golden Record)
							</button>
						</form>
					<?php endif; ?>

					<button type="button" id="delete-btn"
					        class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors"
					        title="Удалить (в архив)">
						<i class="fas fa-trash"></i>
					</button>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

		<!-- Левая колонка: свойства и история -->
		<div class="lg:col-span-2 space-y-6">

			<!-- Таблица текущих свойств -->
			<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
				<div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
					<h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
						<i class="fas fa-list-check text-gray-400 mr-2"></i> Текущие свойства
					</h2>
					<span class="text-xs text-gray-400"><?php echo is_object($properties) ? count($properties) : count((array) $properties); ?> шт.</span>
				</div>
				<div class="overflow-x-auto">
					<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
						<thead class="bg-gray-50 dark:bg-gray-900/50">
							<tr>
								<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Атрибут</th>
								<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Значение</th>
								<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ед. изм.</th>
								<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Источник</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
							<?php if (empty($properties) OR (is_object($properties) && count($properties) === 0)): ?>
								<tr>
									<td colspan="4" class="px-6 py-10 text-center text-gray-400">
										<i class="fas fa-folder-open text-3xl mb-2 block opacity-40"></i>
										У объекта пока нет актуальных свойств.
									</td>
								</tr>
							<?php else: ?>
								<?php foreach ($properties as $prop): ?>
									<?php
										$attr_name = isset($prop->key) ? $prop->key : '—';
										$value     = erp230_decode_prop_value(isset($prop->value) ? $prop->value : '');
										$source    = isset($prop->source_id) ? $prop->source_id : '—';
										$uom       = isset($prop->uom_code) ? $prop->uom_code : '';
										$confidence = isset($prop->confidence) ? (float) $prop->confidence : NULL;
									?>
									<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
										<td class="px-6 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
											<?php echo HTML::chars($attr_name); ?>
										</td>
										<td class="px-6 py-3 text-sm text-gray-900 dark:text-gray-100 break-words">
											<?php echo nl2br(HTML::chars($value)); ?>
										</td>
										<td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
											<?php echo $uom !== '' ? HTML::chars($uom) : '<span class="text-gray-300 dark:text-gray-600">—</span>'; ?>
										</td>
										<td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
											<span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs">
												<?php echo HTML::chars($source); ?>
											</span>
											<?php if ($confidence !== NULL): ?>
												<span class="ml-2 text-[11px] text-gray-400"><?php echo round($confidence * 100); ?>%</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- История версий (Baselines) -->
			<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
				<div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
					<h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
						<i class="fas fa-history text-gray-400 mr-2"></i> История версий (Baselines)
					</h2>
				</div>
				<div class="overflow-x-auto">
					<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
						<thead class="bg-gray-50 dark:bg-gray-900/50">
							<tr>
								<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Дата</th>
								<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Автор</th>
								<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Код</th>
								<th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Хеш</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
							<?php 
								$baselines = $object->baselines->order_by('created_at', 'DESC')->find_all();
								if (count($baselines) === 0): 
							?>
								<tr>
									<td colspan="4" class="px-6 py-6 text-center text-gray-400 text-sm">Снимки версий отсутствуют</td>
								</tr>
							<?php else: ?>
								<?php foreach ($baselines as $b): ?>
									<tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
										<td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
											<?php echo HTML::chars($b->created_at); ?>
										</td>
										<td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-400">
											<?php echo HTML::chars($b->actor_id ?: 'system'); ?>
										</td>
										<td class="px-6 py-3 text-sm font-mono text-blue-600 dark:text-blue-400">
											<?php echo HTML::chars($b->code); ?>
										</td>
										<td class="px-6 py-3 text-xs font-mono text-gray-400 truncate max-w-[150px]" title="<?php echo HTML::chars($b->snapshot_hash); ?>">
											<?php echo HTML::chars(substr($b->snapshot_hash, 0, 8)); ?>...
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Правая колонка: мета -->
		<div class="space-y-6">
			<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-6">
				<h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
					<i class="fas fa-circle-info text-gray-400 mr-2"></i> Метаданные
				</h2>
				<dl class="space-y-3 text-sm">
					<?php if ($name): ?>
						<div class="flex justify-between gap-4">
							<dt class="text-gray-500 dark:text-gray-400 shrink-0">Наименование</dt>
							<dd class="text-gray-900 dark:text-gray-100 font-medium text-right break-words"><?php echo HTML::chars($name); ?></dd>
						</div>
					<?php endif; ?>
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500 dark:text-gray-400 shrink-0">Мастер-код</dt>
						<dd class="text-gray-900 dark:text-gray-100 font-medium text-right break-words font-mono"><?php echo HTML::chars($object->master_code); ?></dd>
					</div>
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500 dark:text-gray-400 shrink-0">Тип</dt>
						<dd class="text-gray-900 dark:text-gray-100 font-medium text-right break-words"><?php echo HTML::chars($type_name); ?></dd>
					</div>
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500 dark:text-gray-400 shrink-0">Организация</dt>
						<dd class="text-gray-900 dark:text-gray-100 font-medium text-right break-words"><?php echo HTML::chars($org_name); ?></dd>
					</div>
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500 dark:text-gray-400 shrink-0">Состояние</dt>
						<dd class="text-right">
							<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge['cls']; ?>">
								<?php echo HTML::chars($badge['label']); ?>
							</span>
						</dd>
					</div>
					<div class="border-t border-gray-100 dark:border-gray-700 pt-3"></div>
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500 dark:text-gray-400 shrink-0">Создан</dt>
						<dd class="text-gray-600 dark:text-gray-300 text-right whitespace-nowrap"><?php echo HTML::chars(isset($object->created_at) ? $object->created_at : '—'); ?></dd>
					</div>
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500 dark:text-gray-400 shrink-0">Обновлён</dt>
						<dd class="text-gray-600 dark:text-gray-300 text-right whitespace-nowrap"><?php echo HTML::chars(isset($object->updated_at) ? $object->updated_at : '—'); ?></dd>
					</div>
				</dl>
			</div>

			<!-- Текущий хеш -->
			<?php if ( ! empty($object->current_hash)): ?>
				<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-6">
					<h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3 flex items-center">
						<i class="fas fa-fingerprint text-gray-400 mr-2"></i> Текущий хеш
					</h2>
					<code class="block text-xs font-mono text-gray-600 dark:text-gray-300 break-all bg-gray-50 dark:bg-gray-900 rounded-lg p-3">
						<?php echo HTML::chars($object->current_hash); ?>
					</code>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Модалка подтверждения удаления -->
<?php if ($can_edit): ?>
<div id="delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4 no-print">
	<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6">
		<div class="flex items-center gap-3 mb-4">
			<div class="w-11 h-11 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center text-red-600 dark:text-red-400">
				<i class="fas fa-triangle-exclamation text-lg"></i>
			</div>
			<h3 class="text-lg font-bold text-gray-900 dark:text-white">Удалить объект?</h3>
		</div>
		<p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
			Объект <span class="font-semibold text-gray-700 dark:text-gray-200"><?php echo HTML::chars($name ?: $object->master_code); ?></span>
			будет перемещён в архив (мягкое удаление). Это действие можно отменить только через восстановление.
		</p>
		<div class="flex justify-end gap-2">
			<button type="button" id="delete-cancel"
			        class="px-4 py-2 text-sm font-medium rounded-lg text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
				Отмена
			</button>
			<form method="post" action="<?php echo URL::site('erp230_objects/delete/'.$object->id); ?>">
				<?php if ($csrf): ?><input type="hidden" name="csrf" value="<?php echo HTML::chars($csrf); ?>"><?php endif; ?>
				<button type="submit"
				        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors">
					<i class="fas fa-trash mr-2"></i> Удалить
				</button>
			</form>
		</div>
	</div>
</div>

<script>
(function () {
	var modal  = document.getElementById('delete-modal');
	var openBtn = document.getElementById('delete-btn');
	var cancelBtn = document.getElementById('delete-cancel');

	function open()  { modal.classList.remove('hidden'); modal.classList.add('flex'); }
	function close() { modal.classList.add('hidden'); modal.classList.remove('flex'); }

	openBtn && openBtn.addEventListener('click', open);
	cancelBtn && cancelBtn.addEventListener('click', close);
	modal && modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
<?php endif; ?>