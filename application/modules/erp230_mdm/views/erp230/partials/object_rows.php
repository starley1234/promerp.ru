<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Список компактных строк объектов для виджетов дашборда.
 *
 * @var  array  $rows          Массивы из Controller_Erp230_Index::_object_digest()
 * @var  array  $state_badges  Карта бейджей состояний
 * @var  string $empty_text    Текст для пустого списка
 */

$rows         = isset($rows) ? $rows : [];
$state_badges = isset($state_badges) ? $state_badges : [];
$empty_text   = isset($empty_text) ? $empty_text : 'Нет данных.';
?>
<?php if (empty($rows)): ?>
	<div class="px-4 py-10 text-center text-gray-400 text-sm">
		<i class="fas fa-inbox text-3xl mb-2 block opacity-40"></i>
		<?php echo HTML::chars($empty_text); ?>
	</div>
<?php else: ?>
	<ul class="divide-y divide-gray-100 dark:divide-gray-700/50">
		<?php foreach ($rows as $r): ?>
			<?php
				$state = Arr::get($r, 'state', '');
				$badge = Arr::get($state_badges, $state, [
					'label' => $state,
					'cls'   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
				]);
				
				// Исправлено: принудительно используем erp230_objects в начале пути, 
				// чтобы избежать перехвата роутом erp230_mdm_base (который режет _objects)
				$url = Arr::get($r, 'url');
				if (empty($url))
				{
					$url = URL::site('erp230_objects/view/'.Arr::get($r, 'id'));
				}
			?>
			<li>
				<a href="<?php echo $url; ?>"
				   class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
					<div class="min-w-0">
						<div class="flex items-center gap-2">
							<?php if ( ! empty($r['is_dirty'])): ?>
								<span title="Требует консолидации" class="inline-block w-2 h-2 rounded-full bg-orange-500 shrink-0"></span>
							<?php endif; ?>
							<span class="font-medium text-blue-600 dark:text-blue-400 truncate">
								<?php echo HTML::chars(Arr::get($r, 'master_code', '—')); ?>
							</span>
						</div>
						<div class="text-[11px] text-gray-400 mt-0.5 truncate">
							<?php echo HTML::chars(Arr::get($r, 'type_id', '')); ?>
						</div>
					</div>
					<div class="flex items-center gap-3 shrink-0 ml-3">
						<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium <?php echo $badge['cls']; ?>">
							<?php echo HTML::chars($badge['label']); ?>
						</span>
						<span class="text-[11px] text-gray-400 whitespace-nowrap hidden sm:inline">
							<?php echo HTML::chars(Arr::get($r, 'updated_at', '')); ?>
						</span>
					</div>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>