<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Компоненты интерфейса.
 *
 * Зачем. Карточка, KPI-плитка, таблица и бейдж состояния были свёрстаны заново
 * почти в каждом представлении проекта (замер из `docs/FRONTEND_REVIEW.md`:
 * 290 файлов повторяли разметку карточки, 128 — таблицу). Из-за этого правка
 * вида превращалась в правку сотен файлов, а одинаковые по смыслу блоки
 * выглядели по-разному в соседних модулях.
 *
 * Здесь — один набор разметки и правила к нему:
 *
 *  * компонент — это представление `application/views/components/<имя>.php`,
 *    без бизнес-логики и без запросов к базе;
 *  * тексты экранируются (`HTML::chars`) внутри компонента; готовый HTML
 *    передаётся только в ключах с суффиксом `_html` и только из кода проекта;
 *  * у таблицы всегда есть подпись (`caption`), заголовки со `scope` и
 *    прокрутка на узком экране — «на телефоне таблица обрезается» не должно
 *    повторяться;
 *  * у цифры всегда есть подпись и единица измерения: «7020» без единицы
 *    читается как угодно, а «7 020 шт» — однозначно.
 *
 * Как пользоваться:
 *
 *     // короткая форма — строкой
 *     echo Component::render('badge', array('label' => 'ниже минимума', 'state' => 'warn'));
 *
 *     // длинная форма — когда внутри есть разметка (карточка, таблица)
 *     Component::begin('card', array('title' => 'Остатки', 'icon' => 'fas fa-boxes'));
 *     ?> ...любая разметка... <?php
 *     Component::end();
 *
 * @package    Erp
 * @category   UI
 */
class Component
{
    /**
     * @var array  Стек открытых компонентов: begin() вкладываются друг в друга.
     */
    protected static $_stack = array();

    /**
     * @var array  Переменные, которые шаблон делает общими для всех представлений.
     *
     * Компонент не должен их наследовать. Пока `View` подмешивает их в любое
     * представление, `card` без заголовка получал заголовком название страницы
     * (на `/terms` и `/privacy` из-за этого выходило два заголовка
     * «Условия использования»), `badge` — подсказку с названием страницы,
     * `empty` и `toolbar` — его же вместо своего текста.
     *
     * Список проверяет `tests/Unit/ComponentsTest.php`: если шаблон добавит
     * новую общую переменную, тест потребует дополнить список.
     */
    protected static $_template_globals = array(
        'current_user',
        'current_user_roles',
        'scripts',
        'site_logo',
        'site_name',
        'styles',
        'table_view',
        'title',
    );

    /**
     * Отрисовать компонент.
     *
     * @param   string  $name
     * @param   array   $vars
     * @return  string
     */
    public static function render($name, array $vars = array())
    {
        // Общие переменные шаблона объявляем явно: `View` подмешивает их в
        // любое представление, и компонент не должен молча получить чужое
        // значение только потому, что вызывающий его не передал.
        foreach (self::$_template_globals as $key)
        {
            if ( ! array_key_exists($key, $vars))
            {
                $vars[$key] = NULL;
            }
        }

        return (string) View::factory('components/'.$name, $vars);
    }

    /**
     * Начать компонент с разметкой внутри.
     *
     * @param   string  $name
     * @param   array   $vars
     * @return  void
     */
    public static function begin($name, array $vars = array())
    {
        self::$_stack[] = array('name' => $name, 'vars' => $vars);

        ob_start();
    }

    /**
     * Закончить компонент: содержимое между begin() и end() станет `body`.
     *
     * @return  void
     */
    public static function end()
    {
        if ( ! self::$_stack)
        {
            throw new Kohana_Exception('Component::end() вызван без Component::begin()');
        }

        $open = array_pop(self::$_stack);
        $body = ob_get_clean();
        $vars = $open['vars'];

        // Разметка внутри begin()/end() имеет приоритет над `body`, переданным
        // заранее, но пустая пара begin()/end() не должна затирать готовое тело
        // (иначе компонент молча отрисуется пустым — проверено на фильтрах).
        if (trim($body) !== '')
        {
            $vars['body'] = $body;
        }

        echo self::render($open['name'], $vars);
    }

    /**
     * Задан ли в адресе какой-нибудь отбор (непустой параметр, кроме номера страницы).
     * По нему свёрнутая панель фильтров раскрывается сама.
     *
     * @return  boolean
     */
    public static function query_applied()
    {
        $request = Request::initial();

        if ( ! $request)
        {
            return FALSE;
        }

        foreach ((array) $request->query() as $key => $value)
        {
            if ($key !== 'page' AND $value !== '' AND $value !== NULL AND $value !== array())
            {
                return TRUE;
            }
        }

        return FALSE;
    }

    // =========================================================================
    //  Состояния: один словарь на весь интерфейс
    // =========================================================================

    /**
     * Возможные состояния: ключ → подпись по умолчанию.
     *
     * @return  array
     */
    public static function states()
    {
        return array(
            'ok'      => 'в норме',
            'warn'    => 'внимание',
            'bad'     => 'проблема',
            'info'    => 'к сведению',
            'neutral' => 'без оценки',
        );
    }

    /**
     * Подпись состояния (для бейджа и KPI).
     *
     * @param   string  $state
     * @return  string
     */
    public static function state_label($state)
    {
        $states = self::states();

        return isset($states[$state]) ? $states[$state] : $states['neutral'];
    }

    /**
     * Классы Tailwind для состояния.
     *
     * @param   string  $state
     * @param   string  $part   text|bg|border|badge
     * @return  string
     */
    public static function state_class($state, $part = 'text')
    {
        $map = array(
            'ok'      => array('text' => 'text-emerald-600 dark:text-emerald-400',
                               'bg'   => 'bg-emerald-50 dark:bg-emerald-900/20',
                               'border' => 'border-emerald-200 dark:border-emerald-800',
                               'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'),
            'warn'    => array('text' => 'text-amber-600 dark:text-amber-400',
                               'bg'   => 'bg-amber-50 dark:bg-amber-900/20',
                               'border' => 'border-amber-200 dark:border-amber-800',
                               'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'),
            'bad'     => array('text' => 'text-rose-600 dark:text-rose-400',
                               'bg'   => 'bg-rose-50 dark:bg-rose-900/20',
                               'border' => 'border-rose-200 dark:border-rose-800',
                               'badge' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'),
            'info'    => array('text' => 'text-blue-600 dark:text-blue-400',
                               'bg'   => 'bg-blue-50 dark:bg-blue-900/20',
                               'border' => 'border-blue-200 dark:border-blue-800',
                               'badge' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200'),
            'neutral' => array('text' => 'text-gray-600 dark:text-gray-300',
                               'bg'   => 'bg-gray-50 dark:bg-gray-800',
                               'border' => 'border-gray-200 dark:border-gray-700',
                               'badge' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'),
        );

        $state = isset($map[$state]) ? $state : 'neutral';

        return isset($map[$state][$part]) ? $map[$state][$part] : $map[$state]['text'];
    }

    /**
     * Спарклайн (мини-график) для KPI: рисуется SVG, без JavaScript.
     *
     * Тренд нужен, чтобы «76 %» отвечало не только на «сколько», но и на
     * «лучше или хуже, чем было».
     *
     * @param   array   $values   ряд значений (слева направо)
     * @param   int     $width
     * @param   int     $height
     * @param   string  $state
     * @return  string  готовая SVG-разметка
     */
    public static function sparkline(array $values, $width = 120, $height = 28, $state = 'info')
    {
        $values = array_values(array_filter($values, function ($value) {
            return $value !== NULL AND $value !== '';
        }));

        if (count($values) < 2)
        {
            return '';
        }

        $min = min($values);
        $max = max($values);
        $span = ($max - $min) > 0 ? ($max - $min) : 1;

        $step = $width / (count($values) - 1);
        $points = array();

        foreach ($values as $index => $value)
        {
            $x = round($index * $step, 2);
            $y = round($height - (($value - $min) / $span) * ($height - 4) - 2, 2);

            $points[] = $x.','.$y;
        }

        $color = array(
            'ok'      => '#059669',
            'warn'    => '#d97706',
            'bad'     => '#e11d48',
            'info'    => '#2563eb',
            'neutral' => '#6b7280',
        );

        return '<svg viewBox="0 0 '.$width.' '.$height.'" width="'.$width.'" height="'.$height.'"'
            .' class="overflow-visible" role="img" aria-hidden="true" focusable="false">'
            .'<polyline fill="none" stroke="'.(isset($color[$state]) ? $color[$state] : $color['info']).'"'
            .' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="'.implode(' ', $points).'" />'
            .'</svg>';
    }
}
