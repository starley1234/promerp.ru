<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Карточка типа объекта MDM (Создание / Редактирование).
 * 
 * @var  Model_Erp230_Type    $type      Текущий тип
 * @var  Model_Erp230_Type[]  $parents   Список всех типов для выбора родителя
 * @var  bool                 $can_edit  Право на редактирование
 */

$is_new = ! $type->loaded();
$action_url = $is_new ? 'erp230_types/create' : 'erp230_types/view/' . $type->id;
?>

<div>

	<!-- Навигация -->
	<div class="mb-4">
		<a href="<?php echo URL::site('erp230_types'); ?>"
		   class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
			<i class="fas fa-arrow-left mr-2"></i> К списку типов
		</a>
	</div>

	<form method="post" action="<?php echo URL::site($action_url); ?>" class="space-y-6">
		
		<div class="flex flex-col lg:flex-row gap-6">
			
			<!-- Основные параметры -->
			<div class="lg:col-span-1 w-full lg:w-1/3 space-y-6">
				<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-6">
					<h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
						<i class="fas fa-info-circle text-blue-500 mr-2"></i> Основные данные
					</h2>

					<div class="space-y-4">
						<!-- Системный ID -->
						<div>
							<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Системный ID (код)</label>
							<input type="text" name="id" value="<?php echo HTML::chars($type->id); ?>" 
							       <?php echo $is_new ? '' : 'readonly'; ?>
							       class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all <?php echo $is_new ? '' : 'opacity-60 cursor-not-allowed'; ?>"
							       placeholder="например: material_raw" required>
							<?php if (!$is_new): ?>
								<p class="text-[11px] text-gray-400 mt-1">ID нельзя изменить после создания, так как он используется в ltree пути.</p>
							<?php endif; ?>
						</div>

						<!-- Отображаемое имя -->
						<div>
							<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Наименование</label>
							<input type="text" name="display_name" value="<?php echo HTML::chars($type->display_name); ?>" 
							       class="w-full px-3 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all"
							       placeholder="например: Сырье и материалы" required>
						</div>

						<!-- Родительский тип -->
						<div>
							<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Родительский тип</label>
							<select name="parent_id" class="w-full px-3 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all">
								<option value="">-- Корень (нет родителя) --</option>
								<?php foreach ($parents as $p): ?>
									<?php if ($p->id === $type->id) continue; // Нельзя быть родителем самому себе ?>
									<option value="<?php echo HTML::chars($p->id); ?>" <?php echo ($type->parent_id === $p->id) ? 'selected' : ''; ?>>
										<?php echo str_repeat('• ', substr_count($p->path, '.')) . HTML::chars($p->display_name); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<?php if (!$is_new): ?>
						<div class="pt-4 border-t border-gray-100 dark:border-gray-700">
							<div class="flex justify-between text-xs mb-1">
								<span class="text-gray-400">Путь в дереве:</span>
								<span class="font-mono text-gray-600 dark:text-gray-300"><?php echo HTML::chars($type->path); ?></span>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<?php if ($can_edit): ?>
				<div class="flex items-center gap-3">
					<button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 text-sm font-bold rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/20">
						<i class="fas fa-save mr-2"></i> Сохранить изменения
					</button>
				</div>
				<?php endif; ?>
			</div>

			<!-- Редактор JSON-схемы -->
			<div class="lg:col-span-2 flex-1">
				<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden flex flex-col h-full min-h-[500px]">
					<div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between bg-gray-50/50 dark:bg-gray-900/50">
						<div>
							<h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
								<i class="fas fa-code text-purple-500 mr-2"></i> JSON Schema
							</h2>
							<p class="text-xs text-gray-500 dark:text-gray-400">Определение структуры атрибутов и правил валидации</p>
						</div>
						<div class="flex items-center gap-2">
							<button type="button" data-call="formatJson" class="text-xs px-2 py-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded transition-colors">
								Форматировать
							</button>
						</div>
					</div>
					<div class="flex-1 relative">
						<textarea name="schema" id="schema_editor" 
						          class="w-full h-full min-h-[400px] p-4 font-mono text-sm bg-gray-900 text-green-400 outline-none resize-none"
						          spellcheck="false"><?php echo HTML::chars($type->schema ?: "{\n  \"type\": \"object\",\n  \"properties\": {}\n}"); ?></textarea>
					</div>
					<div class="px-6 py-3 bg-gray-50 dark:bg-gray-900/80 border-t border-gray-100 dark:border-gray-700">
						<p class="text-[11px] text-gray-400 leading-relaxed">
							<i class="fas fa-lightbulb mr-1 text-amber-400"></i> 
							Используйте стандарт <b>JSON Schema</b>. Эти правила будут применяться автоматически при создании или обновлении объектов данного типа.
						</p>
					</div>
				</div>
			</div>

		</div>
	</form>
</div>

<script>
function formatJson() {
	const textarea = document.getElementById('schema_editor');
	try {
		const obj = JSON.parse(textarea.value);
		textarea.value = JSON.stringify(obj, null, 2);
	} catch (e) {
		alert('Ошибка в формате JSON: ' + e.message);
	}
}

// Табуляция в textarea
document.getElementById('schema_editor').addEventListener('keydown', function(e) {
	if (e.key === 'Tab') {
		e.preventDefault();
		var start = this.selectionStart;
		var end = this.selectionEnd;
		this.value = this.value.substring(0, start) + "\t" + this.value.substring(end);
		this.selectionStart = this.selectionEnd = start + 1;
	}
});
</script>