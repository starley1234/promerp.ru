<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Публичные страницы сайта: условия использования, политика
 * конфиденциальности, страница о сервисе и эталон интерфейса.
 *
 * Открыты анонимным посетителям: их читают до регистрации, ссылки на них стоят
 * в подвале, и требовать вход ради юридического текста нельзя. Условия и
 * политика закрыты от индексации (noindex + X-Robots-Tag + robots.txt): в
 * поисковой выдаче от них пользы нет, а дублировать текст политики не нужно.
 */
class Controller_Pages extends Controller_Base {

    /**
     * @var bool Страницы открыты без входа.
     */
    protected $auth_required = FALSE;

    /**
     * @var array Действия, доступные анонимному посетителю.
     */
    protected $public_actions = array('terms', 'privacy', 'about', 'required_design_sample', 'design_sample_reference');

    public function action_terms()
    {
        $this->page('pages/terms', array(
            'title'       => 'Условия использования',
            'description' => 'Условия использования сервиса PromERP: общие положения, ответственность за '
                .'учётную запись, ограничение ответственности и порядок споров.',
            'robots'      => 'noindex, follow',
        ));
    }

    public function action_privacy()
    {
        $this->page('pages/privacy', array(
            'title'       => 'Политика конфиденциальности',
            'description' => 'Как PromERP собирает, использует и защищает данные пользователей: '
                .'для чего используются данные, как работают cookie и какие права есть у пользователя.',
            'robots'      => 'noindex, follow',
        ));
    }

    public function action_about()
    {
        $this->page('pages/about', array(
            'title'       => 'О сервисе',
            'description' => 'PromERP — ERP-система для производственного предприятия: заказы, техпроцесс, '
                .'склад, закупки, качество, отгрузка и себестоимость в одном контуре. Что входит и чего пока нет.',
        ));
    }

    /**
     * Эталон интерфейса: правила и живые примеры компонентов.
     *
     * Страницу читает агент перед тем, как верстать экран: здесь правила
     * `AGENTS.md` §10 и `docs/FRONTEND_STATUS.md` показаны живой разметкой на
     * общих компонентах, а четыре типа страниц (ввод, карточка, перечень,
     * сводка) собраны целиком. Открыта гостю: справочник по интерфейсу не
     * должен требовать вход. В поиск не отдаём — это техническая страница,
     * а не содержание сайта.
     *
     * @return  void
     */
    public function action_required_design_sample()
    {
        // Показ. Одни и те же данные выглядят по-разному в простом и
        // профессиональном режиме, и эталон обязан показать оба вида: иначе
        // половина интерфейса остаётся за кадром (в простом режиме вместо
        // таблиц — плитки). Режим переключается запросом `?ui=simple|pro`
        // только для этого запроса: `Ui_Mode::force()` не пишет ни cookie, ни
        // настройку, поэтому выбор человека не меняется.
        //
        // По умолчанию показываем профессиональный вид: в нём видны таблицы,
        // и экран, который верстают по эталону, собирается из тех же блоков.
        $preview = (string) $this->request->query('ui');

        Ui_Mode::force(Ui_Mode::valid($preview) ? $preview : Ui_Mode::PRO);

        $this->page('pages/required_design_sample', array(
            'title'       => 'Эталон интерфейса PromERP',
            'description' => 'Правила и живые примеры интерфейса PromERP: компоненты, состояния, формы, таблицы '
                .'и четыре типа страниц — ввод, карточка, перечень, сводка.',
            'robots'      => 'noindex, follow',
        ));

        // Режим интерфейса передаём готовой подписью: представление само режим
        // не спрашивает (вид выбирают компоненты), а человеку полезно понимать,
        // какой вид он сейчас видит.
        $this->template->content->set('ui_mode_label', Ui_Mode::pro() ? 'профессиональный' : 'простой');
        $this->template->content->set('ui_preview', Ui_Mode::pro() ? Ui_Mode::PRO : Ui_Mode::SIMPLE);
    }

    /**
     * Варианты для живого примера `smart_reference` на странице-эталоне.
     *
     * Только чтение, без базы и без прав: список статический, поэтому пример
     * работает и у гостя, и у вошедшего пользователя, и ничего не меняет.
     * Контракт — как у модульных endpoint'ов поиска сущности:
     * `?q=` → `{items:[{id,label,context}]}`.
     *
     * @return  void
     */
    public function action_design_sample_reference()
    {
        $this->auto_render = FALSE;

        $query = mb_strtolower(trim((string) $this->request->query('q')), 'UTF-8');

        $items = array();

        foreach (self::design_sample_items() as $item)
        {
            if ($query !== ''
                AND mb_strpos(mb_strtolower($item['label'], 'UTF-8'), $query, 0, 'UTF-8') === FALSE)
            {
                continue;
            }

            $items[] = $item;
        }

        $this->response
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode(array('items' => array_slice($items, 0, 20)), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Номенклатура для примера выбора сущности.
     *
     * @return  array
     */
    protected static function design_sample_items()
    {
        return array(
            array('id' => 101, 'label' => 'КРН-50.01 · Корпус насоса НШ-50', 'context' => 'Номенклатура · шт'),
            array('id' => 102, 'label' => 'КРН-50.02 · Крышка насоса НШ-50', 'context' => 'Номенклатура · шт'),
            array('id' => 103, 'label' => 'ВЛ-32.10 · Вал ведущий 32 мм',    'context' => 'Номенклатура · шт'),
            array('id' => 104, 'label' => 'ШС-20.07 · Шестерня ведущая 20',  'context' => 'Номенклатура · шт'),
            array('id' => 105, 'label' => 'ПД-16.03 · Подшипник 16-03',      'context' => 'Номенклатура · шт'),
            array('id' => 106, 'label' => 'МТ-08.44 · Муфта переходная',     'context' => 'Номенклатура · шт'),
            array('id' => 107, 'label' => 'ПР-12.90 · Пружина возвратная',   'context' => 'Номенклатура · шт'),
        );
    }

    /**
     * Отдать статическую страницу в общем шаблоне.
     *
     * Одна точка для заголовка, описания и признака индексации: страницы не
     * должны расходиться между собой по разметке мета-тегов.
     *
     * @param   string  $view     представление страницы
     * @param   array   $meta     title, description, robots (последний — по желанию)
     * @return  void
     */
    protected function page($view, array $meta)
    {
        $this->_meta['title']       = $meta['title'];
        $this->_meta['description'] = $meta['description'];

        $this->template->content = View::factory($view);

        if ( ! empty($meta['robots']))
        {
            // Признак «не индексировать» ставим и в разметке, и в заголовке
            // ответа: заголовок читают роботы, которым мета-тег недоступен.
            $this->_meta['robots'] = $meta['robots'];

            $this->response->headers('X-Robots-Tag', $meta['robots']);
        }
    }

}
