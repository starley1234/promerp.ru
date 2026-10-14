<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Стартовая страница системы.
 *
 * Гости видят короткую публичную страницу, а авторизованный пользователь —
 * рабочее место. Набор модулей на нём строится по ролям, поэтому сотрудник не
 * теряется в чужих разделах и сразу видит свои ежедневные действия.
 */
class Controller_Welcome extends Controller_Base
{
    protected $auth_required = FALSE;

    public function action_index()
    {
        $config = Kohana::$config->load('site_config');

        if ($this->_current_user)
        {
            $this->set_menu($this->dashboard_menu());
            $this->template->content = View::factory('dashboard/index')
                ->set('modules', $this->dashboard_modules())
                ->set('roles', $this->_current_user_roles)
                ->set('user', $this->_current_user);
            $this->set_title('Рабочее место — '.$config->get('site_name'));
            $this->set_description('Персональная сводка PromERP: задачи, показатели и быстрый доступ к модулям.');
            $this->set_keywords('PromERP, рабочее место, ERP, производство, склад');
            return;
        }

        // Гостю главная отвечает на два вопроса: что это за система и можно
        // ли посмотреть её вживую. Раньше здесь была только витрина с кнопкой
        // «О системе», а вход в демо жил по адресу /demo, о котором посетитель
        // ниоткуда не узнавал.
        $demo = $this->_demo_invite();

        $this->set_menu(array());
        $this->template->content = View::factory('welcome/index')
            ->set('demo', $demo)
            ->set('faq', $this->_home_faq((bool) $demo));

        // Заголовок и описание главной — про сам продукт и про то, что его
        // можно посмотреть без регистрации. Общие строки из site_config
        // описывали консалтинг («экспертные решения по внедрению»), а не
        // систему, и в выдаче не отвечали на вопрос «что это и зачем мне».
        // Длина выдержана под сниппет: ~60 символов заголовок, ~160 описание.
        $this->set_title('PromERP — ERP-система для производства: склад, цех, качество');
        $this->set_description(
            'ERP для производственного предприятия: заказы на выпуск, техпроцесс, склад, '
            .'закупки, качество и себестоимость в одной системе. Демо-стенд без регистрации.'
        );
        $this->set_keywords(
            'ERP для производства, программа для производственного учёта, складской учёт, '
            .'заказы на выпуск, техпроцесс, планирование производства MRP, себестоимость, '
            .'контроль качества, прослеживаемость партий, PromERP'
        );

        $this->set_json_ld($this->_home_json_ld($config, (bool) $demo));
    }

    /**
     * Частые вопросы главной.
     *
     * Текст ведём здесь, а не в представлении: те же вопросы и ответы уходят
     * и в страницу, и в структурированные данные (FAQPage) — разойтись они
     * не могут. Каждый ответ — то, что правда есть в системе; обещать
     * внедрение «за две недели» или рост прибыли страница не вправе.
     *
     * @param   bool    $demo   собран ли демонстрационный стенд
     * @return  array
     */
    protected function _home_faq($demo)
    {
        $faq = array(
            array(
                'Можно ли посмотреть систему без регистрации?',
                $demo
                    ? 'Да. Демонстрационный стенд собран на сквозном примере производства: войдите одной из ролей — снабженец, мастер, кладовщик, контролёр ОТК или директор — и увидите те же экраны, что у сотрудника. Данные вымышленные, запись отключена.'
                    : 'Да, стенд поднимается на сквозном примере производства: заказ клиента, закупка, приёмка, выпуск, контроль и себестоимость. Попросите доступ — и войдите одной из ролей: снабженец, мастер, кладовщик, контролёр ОТК или директор.',
            ),
            array(
                'Будет ли это работать на планшете в цеху?',
                'Да: интерфейс рассчитан на планшет и работу в перчатках. Кнопки, поля и листание имеют высоту не меньше 40 px, на узком экране остаются главные колонки, а второстепенные уходят сами. Есть тёмная тема и простой режим с крупными плитками вместо таблиц.',
            ),
            array(
                'Считает ли система себестоимость?',
                'Да, по заказу на выпуск: материалы, работа и накладные с формулой каждой строки и ссылкой на первичный документ. Это управленческий расчёт, а не бухгалтерская книга — проводки и финансовая отчётность в систему не входят.',
            ),
            array(
                'Как находится партия, если пришёл брак от поставщика?',
                'По генеалогии партий: видно и прямой путь «из каких партий сделано изделие», и обратный — «кого затронул брак поставщика». Движения склада и журнал действий только дополняются, поэтому историю нельзя переписать задним числом.',
            ),
            array(
                'Можно ли подключить AI-агента?',
                'Да, через инструменты MCP: агенту доступны чтение, расчёты и подготовка черновиков. Опасные действия — утверждение, отзыв партии, списание — выполняются только после подтверждения человеком.',
            ),
            array(
                'Что не входит в систему?',
                'Бухгалтерский учёт и главная книга, расчёт зарплаты и кадровое делопроизводство, обслуживание и ремонт оборудования, а также полноценный PLM с ECR/ECO. Полный состав разделов и границы продукта собраны на странице «О системе».',
            ),
        );

        return $faq;
    }

    /**
     * Структурированные данные главной (schema.org).
     *
     * Поисковику важно понимать не только текст: SoftwareApplication говорит,
     * что перед ним программный продукт, Organization — кто его делает,
     * FAQPage — что на странице есть ответы, WebSite — что здесь есть поиск.
     *
     * @param   Kohana_Config|Config  $config
     * @param   bool                  $demo   собран ли стенд
     * @return  array
     */
    protected function _home_json_ld($config, $demo)
    {
        $url  = rtrim((string) $config->get('site_url'), '/').'/';
        $name = (string) $config->get('site_name');

        $questions = array();

        foreach ($this->_home_faq($demo) as $pair)
        {
            $questions[] = array(
                '@type'          => 'Question',
                'name'           => $pair[0],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $pair[1],
                ),
            );
        }

        return array(
            '@context' => 'https://schema.org',
            '@graph'   => array(
                array(
                    '@type'              => 'Organization',
                    '@id'                => $url.'#organization',
                    'name'               => $name,
                    'url'                => $url,
                    'email'              => (string) $config->get('admin_mail'),
                ),
                array(
                    '@type'              => 'WebSite',
                    '@id'                => $url.'#website',
                    'url'                => $url,
                    'name'               => $name,
                    'inLanguage'         => 'ru-RU',
                    'publisher'          => array('@id' => $url.'#organization'),
                ),
                array(
                    '@type'              => 'SoftwareApplication',
                    '@id'                => $url.'#app',
                    'name'               => 'PromERP',
                    'applicationCategory' => 'BusinessApplication',
                    'applicationSubCategory' => 'ERP для производственного предприятия',
                    'operatingSystem'    => 'Web',
                    'inLanguage'         => 'ru-RU',
                    'url'                => $url,
                    'publisher'          => array('@id' => $url.'#organization'),
                    'description'        => 'Заказы на выпуск, техпроцесс, склад, закупки, '
                        .'качество, отгрузка и себестоимость в одной системе для '
                        .'производственного предприятия.',
                    'featureList'        => array(
                        'Заказы на выпуск и техпроцесс',
                        'Складской учёт, резервы и неснижаемые остатки',
                        'Планирование потребности (MRP)',
                        'Закупки и приёмка с входным контролем',
                        'Контроль качества и изоляция брака',
                        'Партии, серийные номера и прослеживаемость',
                        'Себестоимость заказа с формулой каждой строки',
                        'Аналитика: OEE, оборачиваемость, Парето простоев и брака',
                    ),
                ),
                array(
                    '@type'      => 'FAQPage',
                    '@id'        => $url.'#faq',
                    'isPartOf'   => array('@id' => $url.'#website'),
                    'mainEntity' => $questions,
                ),
            ),
        );
    }

    /**
     * Приглашение на демо-стенд для главной страницы.
     *
     * Возвращает NULL, если стенд выключен или ещё не собран: звать туда, где
     * ничего нет, — худший первый опыт. Сам вход выполняется POST-ом на
     * `/demo/enter`, потому что это смена состояния, а не переход.
     *
     * @return  array|NULL
     */
    protected function _demo_invite()
    {
        if ( ! class_exists('Service_Erp811_Demo'))
        {
            return NULL;
        }

        try
        {
            $demo = Service_Erp811_Demo::instance();

            if ( ! $demo->enabled() OR ! $demo->available())
            {
                return NULL;
            }

            $scenario = class_exists('Service_Erp811_Scenario')
                ? Service_Erp811_Scenario::instance()
                : NULL;

            return array(
                'landing'  => $scenario ? (array) $scenario->landing() : array(),
                'company'  => $scenario ? (array) $scenario->company() : array(),
                'roleplay' => $demo->roleplay(),
            );
        }
        catch (Exception $e)
        {
            // Стенд — витрина, а не часть главной: его поломка не должна
            // ронять страницу, с которой начинается знакомство с системой.
            Kohana::$log->add(Log::WARNING, 'welcome: приглашение в демо недоступно — :error', array(
                ':error' => $e->getMessage(),
            ));

            return NULL;
        }
    }

    /** Карточки разделов. Ограничение ролями — это UX-фильтр, ACL модулей остаётся защитой. */
    protected function dashboard_modules()
    {
        $all = array(
            array('key' => 'production', 'title' => 'Производство', 'description' => 'Планирование, заказы и рабочие задания цеха.', 'href' => '/shopfloor', 'icon' => 'fas fa-industry', 'color' => 'blue', 'roles' => array('admin', 'manager', 'production', 'master', 'operator')),
            array('key' => 'warehouse', 'title' => 'Склад', 'description' => 'Остатки, движения, приёмка и контроль дефицита.', 'href' => '/warehouse/balances', 'icon' => 'fas fa-boxes-stacked', 'color' => 'emerald', 'roles' => array('admin', 'manager', 'warehouse', 'storekeeper')),
            array('key' => 'quality', 'title' => 'Качество', 'description' => 'Контроли, несоответствия и причины брака.', 'href' => '/quality', 'icon' => 'fas fa-clipboard-check', 'color' => 'amber', 'roles' => array('admin', 'manager', 'quality', 'master')),
            array('key' => 'orders', 'title' => 'Заказы и CRM', 'description' => 'Клиенты, заказы, статусы и отгрузки.', 'href' => '/erp221', 'icon' => 'fas fa-briefcase', 'color' => 'violet', 'roles' => array('admin', 'manager', 'sales', 'crm')),
            array('key' => 'procurement', 'title' => 'Закупки', 'description' => 'Поставщики, потребности и заявки на закупку.', 'href' => '/procurement', 'icon' => 'fas fa-truck-ramp-box', 'color' => 'cyan', 'roles' => array('admin', 'manager', 'procurement', 'buyer')),
            array('key' => 'analytics', 'title' => 'Аналитика', 'description' => 'Показатели предприятия и отчёты для решений.', 'href' => '/analytics', 'icon' => 'fas fa-chart-line', 'color' => 'indigo', 'roles' => array('admin', 'manager', 'director', 'analyst')),
            array('key' => 'docs', 'title' => 'Документация & Схемы', 'description' => 'Сквозные бизнес-процессы, архитектура и каталог модулей.', 'href' => '/docs', 'icon' => 'fas fa-book-open', 'color' => 'sky', 'roles' => array('admin', 'manager', 'production', 'warehouse', 'quality', 'sales', 'procurement', 'director', 'accountant', 'demo', 'worker')),
            array('key' => 'documents', 'title' => 'Документы', 'description' => 'Реестр, шаблоны, согласования и печатные формы.', 'href' => '/documents', 'icon' => 'fas fa-file-lines', 'color' => 'slate', 'roles' => array('admin', 'manager', 'accountant', 'document')),
            array('key' => 'admin', 'title' => 'Администрирование', 'description' => 'Пользователи, роли и настройки системы.', 'href' => '/admin_index', 'icon' => 'fas fa-shield-halved', 'color' => 'rose', 'roles' => array('admin')),
        );

        if (in_array('admin', $this->_current_user_roles, TRUE)) return $all;
        $visible = array();
        foreach ($all as $module)
        {
            if (array_intersect($module['roles'], $this->_current_user_roles)) $visible[] = $module;
        }
        return $visible ? $visible : array($all[5]);
    }

    protected function dashboard_menu()
    {
        return array(
            array('title' => 'Рабочее место', 'href' => '/', 'icon' => 'fas fa-house'),
            array('title' => 'Производство', 'href' => '/shopfloor', 'icon' => 'fas fa-industry', 'roles' => array('admin', 'manager', 'production', 'master', 'operator')),
            array('title' => 'Склад', 'href' => '/warehouse/balances', 'icon' => 'fas fa-boxes-stacked', 'roles' => array('admin', 'manager', 'warehouse', 'storekeeper')),
            array('title' => 'Качество', 'href' => '/quality', 'icon' => 'fas fa-clipboard-check', 'roles' => array('admin', 'manager', 'quality', 'master')),
            array('title' => 'Заказы и CRM', 'href' => '/erp221', 'icon' => 'fas fa-briefcase', 'roles' => array('admin', 'manager', 'sales', 'crm')),
            array('title' => 'Закупки', 'href' => '/procurement', 'icon' => 'fas fa-truck-ramp-box', 'roles' => array('admin', 'manager', 'procurement', 'buyer')),
            array('title' => 'Аналитика', 'href' => '/analytics', 'icon' => 'fas fa-chart-line', 'roles' => array('admin', 'manager', 'director', 'analyst')),
            array('title' => 'Документация', 'href' => '/docs', 'icon' => 'fas fa-book-open', 'roles' => array('admin', 'manager', 'production', 'warehouse', 'quality', 'sales', 'procurement', 'director', 'accountant', 'demo', 'worker')),
            array('title' => 'Документы', 'href' => '/documents', 'icon' => 'fas fa-file-lines', 'roles' => array('admin', 'manager', 'accountant', 'document')),
            array('title' => 'Администрирование', 'href' => '/admin_index', 'icon' => 'fas fa-shield-halved', 'roles' => array('admin')),
        );
    }
}
