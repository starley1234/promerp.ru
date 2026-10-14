<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Service_Erp812_MarkdownRenderer — парсер и визуализатор Markdown с поддержкой
 * диаграмм Mermaid, таблиц, бейджей, callout-блоков и блоков кода.
 *
 * @package    Erp812
 * @category   Services
 */
class Service_Erp812_MarkdownRenderer
{
    /**
     * @var Service_Erp812_MarkdownRenderer
     */
    protected static $_instance;

    /**
     * @return Service_Erp812_MarkdownRenderer
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * @var int  На сколько уровней опускать заголовки: Markdown документа
     *           начинается с `#`, а страница уже даёт свой h1 — иначе на
     *           странице модуля выходило два заголовка первого уровня.
     */
    protected $_level_shift = 0;

    /**
     * Статический вызов рендеринга Markdown в стилизованный HTML.
     *
     * @param   string  $markdown
     * @param   int     $level_shift  на сколько уровней опустить заголовки
     * @return  string
     */
    public static function render($markdown, $level_shift = 0)
    {
        return self::instance()->to_html($markdown, $level_shift);
    }

    /**
     * Рендеринг Markdown в стилизованный HTML.
     *
     * @param   string  $markdown
     * @param   int     $level_shift
     * @return  string
     */
    public function to_html($markdown, $level_shift = 0)
    {
        $this->_level_shift = max(0, min(5, (int) $level_shift));

        if (!is_string($markdown) || trim($markdown) === '')
        {
            return '';
        }

        $lines = explode("\n", str_replace(array("\r\n", "\r"), "\n", $markdown));
        $output = array();
        $in_code_block = FALSE;
        $code_lang = '';
        $code_buffer = array();
        $in_table = FALSE;
        $table_buffer = array();
        $in_list = FALSE;
        $list_type = 'ul';

        foreach ($lines as $line)
        {
            // Блоки кода (включая Mermaid)
            if (preg_match('/^```([a-zA-Z0-9_-]*)/', $line, $code_match))
            {
                if ($in_code_block)
                {
                    // Закрытие блока кода
                    if ($code_lang === 'mermaid')
                    {
                        $diagram_code = htmlspecialchars(implode("\n", $code_buffer), ENT_QUOTES, 'UTF-8');
                        $output[] = '<div class="my-6 rounded-2xl border border-gray-200 dark:border-gray-700 bg-slate-900/5 dark:bg-gray-800/60 p-4 sm:p-6 overflow-x-auto text-center shadow-sm">';
                        $output[] = '  <div class="mermaid text-sm">' . $diagram_code . '</div>';
                        $output[] = '</div>';
                    }
                    else
                    {
                        $code_content = htmlspecialchars(implode("\n", $code_buffer), ENT_QUOTES, 'UTF-8');
                        $lang_badge = $code_lang ? '<span class="text-[11px] font-mono uppercase tracking-wider text-gray-400 bg-gray-800 px-2 py-0.5 rounded">' . htmlspecialchars($code_lang) . '</span>' : '';
                        $output[] = '<div class="my-5 rounded-xl border border-gray-700 bg-gray-900 text-gray-100 shadow-md overflow-hidden">';
                        if ($lang_badge)
                        {
                            $output[] = '<div class="flex items-center justify-between px-4 py-2 bg-gray-950/80 border-b border-gray-800">' . $lang_badge . '<span class="text-xs text-gray-400 font-mono">code</span></div>';
                        }
                        $output[] = '  <pre class="p-4 overflow-x-auto text-xs sm:text-sm font-mono leading-relaxed"><code class="language-' . htmlspecialchars($code_lang) . '">' . $code_content . '</code></pre>';
                        $output[] = '</div>';
                    }
                    $in_code_block = FALSE;
                    $code_lang = '';
                    $code_buffer = array();
                    continue;
                }
                else
                {
                    // Открытие блока кода
                    $this->_close_list($output, $in_list);
                    $this->_close_table($output, $in_table, $table_buffer);
                    $in_code_block = TRUE;
                    $code_lang = strtolower(trim($code_match[1]));
                    $code_buffer = array();
                    continue;
                }
            }

            if ($in_code_block)
            {
                $code_buffer[] = $line;
                continue;
            }

            // Таблицы Markdown
            if (trim($line) !== '' && strpos(trim($line), '|') === 0 && substr(trim($line), -1) === '|')
            {
                $this->_close_list($output, $in_list);
                $in_table = TRUE;
                $table_buffer[] = $line;
                continue;
            }
            elseif ($in_table)
            {
                $this->_close_table($output, $in_table, $table_buffer);
            }

            // Пустая строка
            if (trim($line) === '')
            {
                $this->_close_list($output, $in_list);
                continue;
            }

            // Заголовки (H1 - H6)
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $h_match))
            {
                $this->_close_list($output, $in_list);
                $level = min(6, strlen($h_match[1]) + $this->_level_shift);
                $title_text = $h_match[2];
                $anchor = URL::title(strip_tags($title_text), '-', TRUE);
                $parsed_title = $this->_parse_inline($title_text);

                switch ($level)
                {
                    case 1:
                        $output[] = '<h1 id="' . $anchor . '" class="text-2xl font-bold text-gray-900 dark:text-white mt-8 mb-4 tracking-tight border-b border-gray-200 dark:border-gray-700 pb-3">' . $parsed_title . '</h1>';
                        break;
                    case 2:
                        $output[] = '<h2 id="' . $anchor . '" class="text-xl font-bold text-gray-900 dark:text-white mt-8 mb-3 tracking-tight flex items-center gap-2.5"><span class="h-4 w-1 bg-blue-600 rounded-full inline-block"></span>' . $parsed_title . '</h2>';
                        break;
                    case 3:
                        $output[] = '<h3 id="' . $anchor . '" class="text-lg font-bold text-gray-900 dark:text-white mt-6 mb-2 tracking-tight">' . $parsed_title . '</h3>';
                        break;
                    case 4:
                        $output[] = '<h4 id="' . $anchor . '" class="text-base font-semibold text-gray-800 dark:text-gray-200 mt-4 mb-1.5">' . $parsed_title . '</h4>';
                        break;
                    default:
                        $output[] = '<h5 id="' . $anchor . '" class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mt-3 mb-1">' . $parsed_title . '</h5>';
                        break;
                }
                continue;
            }

            // Цитаты / Callout блоки (> [!NOTE], > [!TIP], > [!WARNING], > [!IMPORTANT])
            if (preg_match('/^>\s*(.*)$/', $line, $q_match))
            {
                $this->_close_list($output, $in_list);
                $quote_content = trim($q_match[1]);

                if (preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(.*)$/i', $quote_content, $callout_match))
                {
                    $type = strtoupper($callout_match[1]);
                    $rest = $callout_match[2];
                    $styles = array(
                        'NOTE'      => array('bg' => 'bg-blue-50 dark:bg-blue-900/20 border-blue-400 text-blue-900 dark:text-blue-200', 'icon' => 'fa-info-circle text-blue-600 dark:text-blue-400', 'title' => 'Примечание'),
                        'TIP'       => array('bg' => 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-400 text-emerald-900 dark:text-emerald-200', 'icon' => 'fa-lightbulb text-emerald-600 dark:text-emerald-400', 'title' => 'Совет'),
                        'IMPORTANT' => array('bg' => 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-400 text-indigo-900 dark:text-indigo-200', 'icon' => 'fa-star text-indigo-600 dark:text-indigo-400', 'title' => 'Важно'),
                        'WARNING'   => array('bg' => 'bg-amber-50 dark:bg-amber-900/20 border-amber-400 text-amber-900 dark:text-amber-200', 'icon' => 'fa-triangle-exclamation text-amber-600 dark:text-amber-400', 'title' => 'Внимание'),
                        'CAUTION'   => array('bg' => 'bg-rose-50 dark:bg-rose-900/20 border-rose-400 text-rose-900 dark:text-rose-200', 'icon' => 'fa-shield-halved text-rose-600 dark:text-rose-400', 'title' => 'Осторожно'),
                    );
                    $conf = isset($styles[$type]) ? $styles[$type] : $styles['NOTE'];
                    $output[] = '<div class="my-4 rounded-xl border-l-4 ' . $conf['bg'] . ' p-4 shadow-sm">';
                    $output[] = '  <div class="flex items-center gap-2 font-bold text-xs uppercase tracking-wider mb-1"><i class="fas ' . $conf['icon'] . '"></i> <span>' . $conf['title'] . '</span></div>';
                    if ($rest) {
                        $output[] = '  <p class="text-sm leading-relaxed">' . $this->_parse_inline($rest) . '</p>';
                    }
                    $output[] = '</div>';
                }
                else
                {
                    $output[] = '<blockquote class="my-4 border-l-4 border-gray-300 dark:border-gray-600 pl-4 py-1.5 text-gray-600 dark:text-gray-300 italic text-sm">' . $this->_parse_inline($quote_content) . '</blockquote>';
                }
                continue;
            }

            // Горизонтальный разделитель (--- или ***)
            if (preg_match('/^(\-{3,}|\*{3,}|_{3,})$/', trim($line)))
            {
                $this->_close_list($output, $in_list);
                $output[] = '<hr class="my-6 border-t border-gray-200 dark:border-gray-700">';
                continue;
            }

            // Списки (маркированные - или *, нумерованные 1.)
            if (preg_match('/^(\*|-|\+)\s+(.+)$/', $line, $list_match))
            {
                if (!$in_list || $list_type !== 'ul')
                {
                    $this->_close_list($output, $in_list);
                    $in_list = TRUE;
                    $list_type = 'ul';
                    $output[] = '<ul class="my-3 space-y-1.5 list-disc list-inside text-sm text-gray-700 dark:text-gray-300 leading-relaxed pl-2">';
                }
                $output[] = '<li>' . $this->_parse_inline($list_match[2]) . '</li>';
                continue;
            }

            if (preg_match('/^(\d+)\.\s+(.+)$/', $line, $num_match))
            {
                if (!$in_list || $list_type !== 'ol')
                {
                    $this->_close_list($output, $in_list);
                    $in_list = TRUE;
                    $list_type = 'ol';
                    $output[] = '<ol class="my-3 space-y-1.5 list-decimal list-inside text-sm text-gray-700 dark:text-gray-300 leading-relaxed pl-2">';
                }
                $output[] = '<li>' . $this->_parse_inline($num_match[2]) . '</li>';
                continue;
            }

            // Обычный абзац текста
            $this->_close_list($output, $in_list);
            $output[] = '<p class="my-2.5 text-sm sm:text-base text-gray-700 dark:text-gray-300 leading-relaxed">' . $this->_parse_inline($line) . '</p>';
        }

        // Финальное закрытие незакрытых тегов
        $this->_close_list($output, $in_list);
        $this->_close_table($output, $in_table, $table_buffer);

        return implode("\n", $output);
    }

    /**
     * Закрытие списка.
     */
    protected function _close_list(&$output, &$in_list)
    {
        if ($in_list)
        {
            $output[] = '</ul>';
            $in_list = FALSE;
        }
    }

    /**
     * Закрытие и рендеринг таблицы Markdown.
     */
    protected function _close_table(&$output, &$in_table, &$table_buffer)
    {
        if (!$in_table || empty($table_buffer))
        {
            $in_table = FALSE;
            $table_buffer = array();
            return;
        }

        $html = array();
        $html[] = '<div class="my-5 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">';
        $html[] = '  <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs sm:text-sm text-left">';

        $header_parsed = FALSE;
        $alignments = array();

        foreach ($table_buffer as $index => $row_line)
        {
            $trimmed = trim($row_line, " |\t\n\r\0\x0B");
            $cells = explode('|', $trimmed);
            $cells = array_map('trim', $cells);

            if ($index === 1 && preg_match('/^[\s\-:|]+$/', $row_line))
            {
                // Строка-разделитель (определяет выравнивание)
                foreach ($cells as $col_idx => $spec)
                {
                    $spec = trim($spec);
                    if (substr($spec, 0, 1) === ':' && substr($spec, -1) === ':') {
                        $alignments[$col_idx] = 'text-center';
                    } elseif (substr($spec, -1) === ':') {
                        $alignments[$col_idx] = 'text-right';
                    } else {
                        $alignments[$col_idx] = 'text-left';
                    }
                }
                $header_parsed = TRUE;
                continue;
            }

            if (!$header_parsed)
            {
                $html[] = '    <thead class="bg-gray-50 dark:bg-gray-800/80 font-semibold text-gray-900 dark:text-white">';
                $html[] = '      <tr>';
                foreach ($cells as $col_idx => $cell)
                {
                    $align = isset($alignments[$col_idx]) ? $alignments[$col_idx] : 'text-left';
                    $html[] = '        <th scope="col" class="px-4 py-3 ' . $align . '">' . $this->_parse_inline($cell) . '</th>';
                }
                $html[] = '      </tr>';
                $html[] = '    </thead>';
                $html[] = '    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800">';
            }
            else
            {
                $html[] = '      <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-750 transition-colors">';
                foreach ($cells as $col_idx => $cell)
                {
                    $align = isset($alignments[$col_idx]) ? $alignments[$col_idx] : 'text-left';
                    $html[] = '        <td class="px-4 py-2.5 ' . $align . ' text-gray-700 dark:text-gray-300">' . $this->_parse_inline($cell) . '</td>';
                }
                $html[] = '      </tr>';
            }
        }

        if ($header_parsed)
        {
            $html[] = '    </tbody>';
        }

        $html[] = '  </table>';
        $html[] = '</div>';

        $output[] = implode("\n", $html);
        $in_table = FALSE;
        $table_buffer = array();
    }

    /**
     * Парсинг inline-элементов: `код`, **жирный**, *курсив*, [ссылки](url), бейджи `[badge:color]`.
     *
     * @param   string  $text
     * @return  string
     */
    protected function _parse_inline($text)
    {
        // 1. Инлайн код `...`
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
            return '<code class="px-1.5 py-0.5 rounded-md text-xs font-mono bg-gray-100 dark:bg-gray-700/80 text-blue-600 dark:text-blue-400 border border-gray-200 dark:border-gray-600">' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
        }, $text);

        // 2. Жирный + курсив ***text***
        $text = preg_replace('/\*\*\*([^*]+)\*\*\*/', '<strong class="font-bold italic">$1</strong>', $text);

        // 3. Жирный **text** или __text__
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong class="font-bold text-gray-900 dark:text-white">$1</strong>', $text);
        $text = preg_replace('/__([^_]+)__/', '<strong class="font-bold text-gray-900 dark:text-white">$1</strong>', $text);

        // 4. Курсив *text* или _text_
        $text = preg_replace('/\*([^*]+)\*/', '<em class="italic">$1</em>', $text);

        // 5. Зачёркнутый ~~text~~
        $text = preg_replace('/~~([^~]+)~~/', '<del class="line-through text-gray-400">$1</del>', $text);

        // 6. Ссылки [text](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $label = $m[1];
            $url = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $is_external = (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
            $target = $is_external ? ' target="_blank" rel="noopener noreferrer"' : '';
            return '<a href="' . $url . '" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium underline underline-offset-2 transition-colors"' . $target . '>' . $label . '</a>';
        }, $text);

        // 7. Бейджи статусов `[badge:success:Текст]`, `[badge:info:Текст]` и т.д.
        $text = preg_replace_callback('/\[badge:(success|info|warning|danger|neutral):([^\]]+)\]/i', function ($m) {
            $type = strtolower($m[1]);
            $label = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $classes = array(
                'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                'info'    => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                'danger'  => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                'neutral' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            );
            $cls = isset($classes[$type]) ? $classes[$type] : $classes['neutral'];
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ' . $cls . '">' . $label . '</span>';
        }, $text);

        return $text;
    }
}
