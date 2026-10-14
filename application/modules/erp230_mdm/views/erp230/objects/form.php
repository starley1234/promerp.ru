<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Форма создания / редактирования Golden Record (MDM).
 *
 * @var  Model_Erp230_Object  $object
 * @var  array                $errors
 * @var  string               $mode        create|edit
 * @var  array                $types
 * @var  array                $orgs
 * @var  mixed                $properties
 * @var  array                $schema
 * @var  array                $candidates  optional
 */

$mode       = isset($mode) ? $mode : 'create';
$is_create  = ($mode === 'create');
$errors     = isset($errors) && is_array($errors) ? $errors : [];
$schema     = isset($schema) && is_array($schema) ? $schema : [];
$candidates = isset($candidates) && is_array($candidates) ? $candidates : [];
$types      = isset($types) ? $types : [];
$orgs       = isset($orgs) ? $orgs : [];
$csrf       = class_exists('Security') ? Security::token() : '';

$post_data = Request::current()->post();

$action = $is_create
	? URL::site('erp230_objects/create')
	: URL::site('erp230_objects/edit/'.$object->id);

$props_map = [];
if (isset($properties) && (is_object($properties) || is_array($properties)))
{
	foreach ($properties as $p)
	{
		if ( ! is_object($p)) continue;
		$raw = isset($p->value) ? $p->value : '';
		if (is_string($raw))
		{
			$dec = json_decode($raw, TRUE);
			$val = (json_last_error() === JSON_ERROR_NONE)
				? (is_bool($dec) ? ($dec ? 'true' : 'false')
					: (is_array($dec) ? json_encode($dec, JSON_UNESCAPED_UNICODE) : (string) $dec))
				: $raw;
		}
		else
		{
			$val = is_bool($raw) ? ($raw ? 'true' : 'false') : (string) $raw;
		}
		$props_map[$p->key] = [
			'value' => $val,
			'uom'   => isset($p->uom_code) ? $p->uom_code : '',
		];
	}
}

if ( ! empty($post_data['attr_key']))
{
	foreach ($post_data['attr_key'] as $i => $key)
	{
		if (empty($key)) continue;
		$props_map[$key] = [
			'value' => isset($post_data['attr_value'][$i]) ? $post_data['attr_value'][$i] : '',
			'uom'   => isset($post_data['attr_uom'][$i]) ? $post_data['attr_uom'][$i] : '',
		];
	}
}

$schema_props    = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];
$schema_required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];
$x_mdm           = isset($schema['x-mdm']) && is_array($schema['x-mdm']) ? $schema['x-mdm'] : [];
$identity_keys   = isset($x_mdm['identity_keys']) && is_array($x_mdm['identity_keys']) ? $x_mdm['identity_keys'] : [];

$known_keys = array_keys($schema_props);
$extra_props = [];
foreach ($props_map as $k => $v)
{
	if ( ! in_array($k, $known_keys, TRUE))
	{
		$extra_props[$k] = $v;
	}
}

function erp230_form_err($errors, $key)
{
	if (empty($errors[$key])) return '';
	$msg = is_array($errors[$key]) ? implode(', ', $errors[$key]) : (string) $errors[$key];
	return '<p class="mt-1 text-xs text-red-600 dark:text-red-400">'.HTML::chars($msg).'</p>';
}

function erp230_form_val($object, $post, $key)
{
	if (isset($post[$key])) return (string) $post[$key];
	if (isset($object->$key)) return (string) $object->$key;
	if ($key === 'org_id' && isset($_COOKIE['erp230_last_org_id'])) return (string) $_COOKIE['erp230_last_org_id'];
	return '';
}
?>
<div class="mx-auto max-w-5xl" id="mdm-object-form-root"
	data-mode="<?php echo HTML::chars($mode); ?>"
	data-object-id="<?php echo HTML::chars($is_create ? '' : (string) $object->id); ?>"
	data-api-type="<?php echo HTML::chars(URL::site('mdm/type')); ?>"
	data-api-codes="<?php echo HTML::chars(URL::site('mdm/codes')); ?>"
	data-api-search="<?php echo HTML::chars(URL::site('mdm/api/search')); ?>">

	<div class="mb-4">
		<a href="<?php echo $is_create ? URL::site('erp230_objects') : URL::site('erp230_objects/view/'.$object->id); ?>"
		   class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
			<i class="fas fa-arrow-left mr-2"></i>
			<?php echo $is_create ? 'К реестру' : 'К карточке'; ?>
		</a>
	</div>

	<div class="mb-6">
		<h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
			<i class="fas <?php echo $is_create ? 'fa-plus-circle' : 'fa-pen-to-square'; ?> text-blue-600 dark:text-blue-400 mr-2"></i>
			<?php echo $is_create ? 'Новый объект MDM' : 'Редактирование: '.HTML::chars($object->master_code); ?>
		</h1>
	</div>

	<?php if ( ! empty($errors)): ?>
		<div class="mb-6 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4">
			<div class="flex gap-3">
				<div class="shrink-0 text-red-500"><i class="fas fa-circle-exclamation"></i></div>
				<div class="text-sm text-red-800 dark:text-red-300 space-y-1">
					<p class="font-semibold">Ошибка сохранения. Пожалуйста, проверьте поля формы.</p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo HTML::chars($action); ?>" id="mdm-object-form" class="space-y-6" autocomplete="off">
		<?php if ($csrf): ?><input type="hidden" name="csrf" value="<?php echo HTML::chars($csrf); ?>"><?php endif; ?>

		<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
			<div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
				<h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
					<i class="fas fa-id-card text-gray-400 mr-2"></i> Идентификация
				</h2>
			</div>
			<div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
				<div class="md:col-span-2">
					<label for="type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Тип изделия <span class="text-red-500">*</span></label>
					<select name="type_id" id="type_id" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2.5">
						<option value="">— выберите тип —</option>
						<?php foreach ($types as $t): ?>
							<?php 
								$tid = is_object($t) ? $t->id : $t['id'];
								$tname = is_object($t) ? $t->display_name : $t['display_name'];
								$sel = ((string)erp230_form_val($object, $post_data, 'type_id') === (string)$tid) ? ' selected' : '';
							?>
							<option value="<?php echo HTML::chars($tid); ?>"<?php echo $sel; ?>><?php echo HTML::chars($tname); ?></option>
						<?php endforeach; ?>
					</select>
					<?php echo erp230_form_err($errors, 'type_id'); ?>
				</div>

				<div>
					<label for="org_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Организация <span class="text-red-500">*</span></label>
					<select name="org_id" id="org_id" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2.5">
						<option value="">— выберите организацию —</option>
						<?php foreach ($orgs as $o): ?>
							<?php 
								$oid = is_object($o) ? $o->id : $o['id'];
								$oname = is_object($o) ? $o->name : $o['name'];
								$sel = ((string)erp230_form_val($object, $post_data, 'org_id') === (string)$oid) ? ' selected' : '';
							?>
							<option value="<?php echo HTML::chars($oid); ?>"<?php echo $sel; ?>><?php echo HTML::chars($oname); ?></option>
						<?php endforeach; ?>
					</select>
					<?php echo erp230_form_err($errors, 'org_id'); ?>
				</div>

				<div>
					<label for="master_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Мастер-код <span class="text-red-500">*</span></label>
					<div class="flex gap-2">
						<input type="text" name="master_code" id="master_code" required
						       value="<?php echo HTML::chars(erp230_form_val($object, $post_data, 'master_code')); ?>"
						       class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm font-mono px-3 py-2.5">
						<?php if ($is_create): ?>
							<button type="button" id="btn-gen-code" class="px-3 py-2.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm font-medium">Auto</button>
						<?php endif; ?>
					</div>
					<?php echo erp230_form_err($errors, 'master_code'); ?>
				</div>
			</div>
		</div>

		<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
			<div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
				<h2 class="text-lg font-bold text-gray-900 dark:text-white"><i class="fas fa-list-check text-gray-400 mr-2"></i> Атрибуты</h2>
				<span id="schema-count" class="text-xs text-gray-400"><?php echo count($schema_props); ?> полей</span>
			</div>
			<div id="schema-fields" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
				<?php if (empty($schema_props)): ?>
					<div id="schema-empty" class="md:col-span-2 text-center py-8 text-gray-400">
						<i class="fas fa-puzzle-piece text-3xl mb-2 block opacity-40"></i>
						<span class="text-sm">Схема типа не загружена</span>
					</div>
				<?php else: ?>
					<?php foreach ($schema_props as $key => $def): ?>
						<?php
							$cur = isset($props_map[$key]) ? $props_map[$key] : ['value' => '', 'uom' => isset($def['x-uom']) ? $def['x-uom'] : ''];
							$required = in_array($key, $schema_required);
							$title = isset($def['title']) ? $def['title'] : $key;
						?>
						<div class="schema-field" data-key="<?php echo HTML::chars($key); ?>">
							<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
								<?php echo HTML::chars($title); ?>
								<?php if ($required): ?><span class="text-red-500">*</span><?php endif; ?>
							</label>
							<input type="hidden" name="attr_key[]" value="<?php echo HTML::chars($key); ?>">
							<div class="flex gap-2">
								<input type="text" name="attr_value[]" value="<?php echo HTML::chars($cur['value']); ?>" <?php echo $required ? 'required' : ''; ?>
								       class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2.5">
								<input type="text" name="attr_uom[]" value="<?php echo HTML::chars($cur['uom']); ?>" placeholder="ед."
								       class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-2 py-2.5">
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow overflow-hidden">
			<div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
				<h2 class="text-lg font-bold text-gray-900 dark:text-white">Дополнительные свойства</h2>
				<button type="button" id="btn-add-attr" class="text-sm text-blue-600 font-medium">+ Добавить</button>
			</div>
			<div class="p-6 space-y-3" id="extra-attrs">
				<?php foreach ($extra_props as $ek => $ev): ?>
					<div class="extra-row grid grid-cols-12 gap-2">
						<input type="text" name="attr_key[]" value="<?php echo HTML::chars($ek); ?>" placeholder="Ключ" class="col-span-4 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
						<input type="text" name="attr_value[]" value="<?php echo HTML::chars($ev['value']); ?>" placeholder="Значение" class="col-span-5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
						<input type="text" name="attr_uom[]" value="<?php echo HTML::chars($ev['uom']); ?>" placeholder="Ед." class="col-span-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
						<button type="button" class="btn-remove-attr col-span-1 text-red-500"><i class="fas fa-times"></i></button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="flex items-center justify-end gap-3">
			<a href="<?php echo $is_create ? URL::site('erp230_objects') : URL::site('erp230_objects/view/'.$object->id); ?>" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg">Отмена</a>
			<button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg shadow-sm">
				<?php echo $is_create ? 'Создать объект' : 'Сохранить изменения'; ?>
			</button>
		</div>
	</form>
</div>

<template id="tpl-extra-row">
	<div class="extra-row grid grid-cols-12 gap-2">
		<input type="text" name="attr_key[]" placeholder="Ключ" class="col-span-4 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
		<input type="text" name="attr_value[]" placeholder="Значение" class="col-span-5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
		<input type="text" name="attr_uom[]" placeholder="Ед." class="col-span-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
		<button type="button" class="btn-remove-attr col-span-1 text-red-500"><i class="fas fa-times"></i></button>
	</div>
</template>

<script>
(function() {
	const root = document.getElementById('mdm-object-form-root');
	const apiType = root.getAttribute('data-api-type');
	const apiCodes = root.getAttribute('data-api-codes');
	const typeEl = document.getElementById('type_id');
	const orgEl = document.getElementById('org_id');
	const masterCodeEl = document.getElementById('master_code');
	const schemaFields = document.getElementById('schema-fields');
	const extraWrap = document.getElementById('extra-attrs');
	const tpl = document.getElementById('tpl-extra-row');

	function esc(s) {
		let d = document.createElement('div');
		d.textContent = s || '';
		return d.innerHTML;
	}

	function loadSchema(typeId) {
		if (!typeId) {
			schemaFields.innerHTML = '<div class="md:col-span-2 text-center py-8 text-gray-400">Выберите тип</div>';
			return;
		}
		
		fetch(apiType + '/schema/' + typeId)
			.then(r => r.json())
			.then(data => {
				const schema = data.schema || data;
				const props = schema.properties || {};
				const required = schema.required || [];
				let html = '';
				
				Object.keys(props).forEach(key => {
					const def = props[key];
					const isReq = required.includes(key);
					const uom = def['x-uom'] || '';
					html += `
						<div class="schema-field" data-key="${esc(key)}">
							<label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
								${esc(def.title || key)} ${isReq ? '<span class="text-red-500">*</span>' : ''}
							</label>
							<input type="hidden" name="attr_key[]" value="${esc(key)}">
							<div class="flex gap-2">
								<input type="text" name="attr_value[]" value="" ${isReq ? 'required' : ''}
								       class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2.5">
								<input type="text" name="attr_uom[]" value="${esc(uom)}" placeholder="ед."
								       class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-2 py-2.5">
							</div>
						</div>`;
				});
				schemaFields.innerHTML = html || '<div class="md:col-span-2 text-center py-8 text-gray-400">Нет атрибутов в схеме</div>';
				document.getElementById('schema-count').textContent = Object.keys(props).length + ' полей';
			});
	}

	typeEl.addEventListener('change', (e) => loadSchema(e.target.value));

	orgEl.addEventListener('change', (e) => {
		if (e.target.value) {
			document.cookie = "erp230_last_org_id=" + e.target.value + "; path=/; max-age=" + (86400 * 30);
		}
	});
	
	document.getElementById('btn-add-attr').addEventListener('click', () => {
		extraWrap.appendChild(tpl.content.cloneNode(true));
	});

	extraWrap.addEventListener('click', (e) => {
		if (e.target.closest('.btn-remove-attr')) {
			e.target.closest('.extra-row').remove();
		}
	});

	const btnGen = document.getElementById('btn-gen-code');
	if (btnGen) {
		btnGen.addEventListener('click', () => {
			const type = typeEl.value;
			const org = orgEl.value;
			if (!type || !org) return alert('Выберите тип и организацию');
			
			fetch(`${apiCodes}/next?type_id=${type}&org_id=${org}`)
				.then(r => r.json())
				.then(res => {
					if (res.status === 'ok' && res.code) {
						masterCodeEl.value = res.code;
					} else {
						alert('Для данного типа не настроена автогенерация (нужна политика mode:auto)');
					}
				})
				.catch(() => {
					alert('Ошибка связи с сервером при генерации кода');
				});
		});
	}

	// Валидация кода при потере фокуса
	masterCodeEl.addEventListener('blur', () => {
		const code = masterCodeEl.value;
		const type = typeEl.value;
		const org = orgEl.value;
		if (!code || !type || !org) return;

		const formData = new FormData();
		formData.append('code', code);
		formData.append('type_id', type);
		formData.append('org_id', org);

		fetch(`${apiCodes}/validate`, { method: 'POST', body: formData })
			.then(r => r.json())
			.then(res => {
				masterCodeEl.classList.remove('border-red-500', 'border-green-500');
				if (res.status === 'ok') {
					if (!res.valid) {
						masterCodeEl.classList.add('border-red-500');
						if (res.errors.duplicate) console.warn('Код уже существует');
					} else {
						masterCodeEl.classList.add('border-green-500');
						if (res.normalized) masterCodeEl.value = res.normalized;
					}
				}
			});
	});
})();
</script>