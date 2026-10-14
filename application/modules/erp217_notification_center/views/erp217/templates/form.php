<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="max-w-5xl mx-auto">
        <form action="<?php echo URL::site('erp217_templates/edit' . ($template->loaded() ? '/'.$template->id : '')); ?>" method="POST" id="template-form">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Левая колонка: Редактор -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 space-y-4">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Код шаблона = Название события -->
                                <div>
                                    <label for="alias" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Код шаблона = Название события</label>
                                    <input type="text" name="alias" id="alias" 
                                           value="<?php echo HTML::chars($template->alias); ?>" 
                                           class="w-full px-4 py-2 rounded-lg border <?php echo isset($errors['alias']) ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'; ?> bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all"
                                           placeholder="new_order_notify" required>
                                    <?php if (isset($errors['alias'])): ?>
                                        <p class="text-red-500 text-xs mt-1"><?php echo $errors['alias']; ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Канал связи -->
                                <div>
                                    <label for="channel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Канал связи</label>
                                    <select name="channel" id="channel" 
                                            class="w-full px-4 py-2 rounded-lg border <?php echo isset($errors['channel']) ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'; ?> bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                                        <?php foreach ($channels as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo ($template->channel == $key) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Описание -->
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Описание</label>
                                <input type="text" name="description" id="description" 
                                       value="<?php echo HTML::chars($template->description); ?>" 
                                       class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all"
                                       placeholder="Для чего этот шаблон...">
                            </div>

                            <!-- Тема сообщения -->
                            <div>
                                <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Тема сообщения (Subject)</label>
                                <input type="text" name="subject" id="subject" 
                                       value="<?php echo HTML::chars($template->subject); ?>" 
                                       class="variable-target w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all"
                                       placeholder="Здравствуйте, {{user_name}}!">
                            </div>

                            <!-- Тело сообщения -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Текст уведомления</label>
                                    <span class="text-xs text-gray-400">Поддерживает HTML и переменные</span>
                                </div>
                                <textarea name="body" id="body" rows="12" 
                                          class="variable-target w-full px-4 py-2 rounded-lg border <?php echo isset($errors['body']) ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'; ?> bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-mono text-sm"
                                          placeholder="Введите текст сообщения..." required><?php echo HTML::chars($template->body); ?></textarea>
                                <?php if (isset($errors['body'])): ?>
                                    <p class="text-red-500 text-xs mt-1"><?php echo $errors['body']; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Футер формы -->
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <a href="<?php echo URL::site('erp217_templates'); ?>" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                                <i class="fas fa-arrow-left mr-1"></i> Отмена
                            </a>
                            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm transition-all flex items-center">
                                <i class="fas fa-save mr-2"></i> Сохранить шаблон
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Правая колонка: Переменные и Справка -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-bold mb-4 flex items-center">
                            <i class="fas fa-code text-blue-500 mr-2"></i> Переменные
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Нажмите на переменную, чтобы вставить её в последнее активное поле.
                        </p>
                        
                        <div class="space-y-2" id="variable-list">
                            <?php 
                            $vars = [
                                'user_name' => 'Имя пользователя',
                                'user_email' => 'Email пользователя',
                                'event_date' => 'Дата события',
                                'order_id' => 'ID заказа',
                                'site_url' => 'URL сайта',
                                'support_phone' => 'Телефон поддержки'
                            ];
                            foreach ($vars as $key => $label): ?>
                                <button type="button" 
                                        data-var="{{<?php echo $key; ?>}}"
                                        class="insert-var w-full text-left px-3 py-2 rounded-md bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500 transition-all group">
                                    <code class="text-blue-600 dark:text-blue-400 font-bold text-sm group-hover:text-blue-700">{{<?php echo $key; ?>}}</code>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-0.5"><?php echo $label; ?></div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-6 border border-blue-100 dark:border-blue-800/30">
                        <h4 class="text-sm font-bold text-blue-800 dark:text-blue-300 mb-2 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i> Подсказка
                        </h4>
                        <p class="text-xs text-blue-700/80 dark:text-blue-400/80 leading-relaxed">
                            Уникальность шаблона определяется парой <b>Код + Канал</b>. Вы можете создать разные тексты для Email и Telegram под одним кодом события.
                        </p>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let lastFocusedInput = document.getElementById('body');

    document.querySelectorAll('.variable-target').forEach(input => {
        input.addEventListener('focus', function() {
            lastFocusedInput = this;
        });
    });

    document.querySelectorAll('.insert-var').forEach(button => {
        button.addEventListener('click', function() {
            const varTag = this.getAttribute('data-var');
            const input = lastFocusedInput;
            
            if (!input) return;

            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value;
            const before = text.substring(0, start);
            const after = text.substring(end, text.length);

            input.value = before + varTag + after;
            
            input.focus();
            input.selectionStart = input.selectionEnd = start + varTag.length;
        });
    });
});
</script>