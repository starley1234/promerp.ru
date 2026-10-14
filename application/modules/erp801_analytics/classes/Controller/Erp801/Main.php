<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер аналитики (erp801_analytics).
 *
 * Держит общее для страниц витрин: меню, flash-сообщения, разбор периода из
 * адреса и доступ к сервисам. Контроллеры витрин остаются тонкими: собрать
 * фильтры, проверить права, вызвать сервис, отдать представление.
 *
 * @package    Erp801
 * @category   Controllers
 */
class Controller_Erp801_Main extends Controller_Base
{
    /** @var Service_Erp801_Summary|null */
    protected $_summary;

    /** @var Service_Erp801_Settings|null */
    protected $_settings;

    /** @var Service_Erp801_Sources|null */
    protected $_sources;

    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Аналитика и витрины';
        $this->_meta['description'] = 'Витрины по запасам, производству, качеству и закупкам: оборачиваемость, ABC/XYZ, OEE, Парето простоев и брака, снимки и выгрузка.';
        $this->_meta['keywords']    = 'аналитика, витрина, OEE, ABC, XYZ, оборачиваемость, снимки, отчёт, erp';

        $this->set_menu(array(
            array('title' => 'Обзор',        'href' => '/analytics',             'icon' => 'fas fa-chart-pie',     'acl' => 'erp801.view'),
            array('title' => 'Запасы',       'href' => '/analytics/stock',       'icon' => 'fas fa-boxes',         'acl' => 'erp801.view'),
            array('title' => 'ABC/XYZ',      'href' => '/analytics/abc',         'icon' => 'fas fa-layer-group',   'acl' => 'erp801.view'),
            array('title' => 'Производство', 'href' => '/analytics/production',  'icon' => 'fas fa-industry',      'acl' => 'erp801.view'),
            array('title' => 'Качество',     'href' => '/analytics/quality',     'icon' => 'fas fa-search',        'acl' => 'erp801.view'),
            array('title' => 'Закупки',      'href' => '/analytics/purchases',   'icon' => 'fas fa-truck',         'acl' => 'erp801.view'),
            array('title' => 'Снимки',       'href' => '/analytics/snapshots',   'icon' => 'fas fa-camera',        'acl' => 'erp801.view'),
            array('title' => 'Пороги',       'href' => '/analytics/settings',    'icon' => 'fas fa-sliders-h',     'acl' => 'erp801.manage'),
        ));
    }

    /**
     * @param   string  $type success|error|warning|info
     * @param   string  $message
     * @return  void
     */
    protected function _flash($type, $message)
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp801_flash', array());
            $flash[] = array('type' => $type, 'message' => $message);
            $session->set('erp801_flash', $flash);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp801: не удалось записать flash — :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * @return  array
     */
    protected function _flash_messages()
    {
        try
        {
            $session = Session::instance();
            $flash   = $session->get('erp801_flash', array());
            $session->delete('erp801_flash');
        }
        catch (Exception $e)
        {
            $flash = array();
        }

        return is_array($flash) ? $flash : array();
    }

    /**
     * Действие выполняется только формой (POST).
     *
     * Проверка нужна там, где GET-адрес совпадает с изменяющим действием: без
     * неё дымовой прогон (он ходит по всем адресам методом GET) и любой
     * поисковый робот оставляли бы за собой настоящие записи. Ошибка 405 —
     * честный ответ «этот адрес не для ссылки, а для формы».
     *
     * @return  void
     */
    protected function _require_post()
    {
        if (strtoupper((string) $this->request->method()) !== 'POST')
        {
            // `->allowed()` обязателен: без списка разрешённых методов
            // HTTP_Exception_405 падает при отдаче ответа («A list of allowed
            // methods must be specified») и превращается в 500.
            throw (new HTTP_Exception_405('Это действие выполняется формой: откройте страницу и нажмите кнопку.'))
                ->allowed(array('POST'));
        }
    }

    /**
     * Текущий пользователь (NULL для CLI/гостя).
     *
     * @return  int|null
     */
    protected function _current_user_id()
    {
        try
        {
            $user = Auth::instance()->get_user();

            return ($user AND $user->loaded()) ? (int) $user->id : NULL;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }

    /**
     * Показать ошибку сервиса на странице, а не текстом исключения.
     *
     * @param   Exception   $e
     * @param   string      $fallback
     * @return  void
     */
    protected function _flash_error(Exception $e, $fallback = 'Не удалось посчитать витрину')
    {
        $own = $e instanceof Exception_Erp801_Analytics;

        $this->_flash('error', $own ? $e->getMessage() : $fallback);

        if ( ! $own AND ! $e instanceof HTTP_Exception)
        {
            Kohana::$log->add(Log::ERROR, 'erp801: :error', array(':error' => $e->getMessage()));
        }
    }

    /**
     * Фильтры витрины из адреса.
     *
     * Период по умолчанию не задаётся здесь: у каждой витрины он свой (запасы —
     * квартал, производство и качество — месяц), и «один общий по умолчанию»
     * означал бы пять разных смыслов одного адреса.
     *
     * @param   array   $extra  дополнительные ключи (например, class)
     * @return  array
     */
    protected function _filters(array $extra = array())
    {
        $filters = array();

        foreach (array_merge(array('from', 'to', 'warehouse_id', 'center_id', 'limit', 'due_days', 'class', 'tab'), $extra) as $key)
        {
            $value = $this->_query($key);

            if ($value !== NULL AND $value !== '')
            {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * Значения из тела POST-запроса (без исключения на отсутствующем поле).
     *
     * @param   string  $key
     * @return  mixed
     */
    protected function _posted($key)
    {
        $post = $this->request->post();

        return (is_array($post) AND array_key_exists($key, $post)) ? $post[$key] : NULL;
    }

    /**
     * Всё тело POST-запроса.
     *
     * @return  array
     */
    protected function _form()
    {
        $post = $this->request->post();

        return is_array($post) ? $post : array();
    }

    /**
     * Пояснение фильтров для шапки страницы.
     *
     * @param   array   $filters
     * @param   array   $payload
     * @return  string
     */
    protected function _period_label(array $filters, array $payload = array())
    {
        $from = isset($payload['from']) ? $payload['from'] : (isset($filters['from']) ? $filters['from'] : NULL);
        $to   = isset($payload['to']) ? $payload['to'] : (isset($filters['to']) ? $filters['to'] : NULL);

        if ( ! $from OR ! $to)
        {
            return 'период по умолчанию';
        }

        return 'период: '.$from.' — '.$to;
    }

    /**
     * @return  Service_Erp801_Summary
     */
    protected function _summary()
    {
        if ($this->_summary === NULL)
        {
            $this->_summary = Service_Erp801_Summary::instance();
        }

        return $this->_summary;
    }

    /**
     * @return  Service_Erp801_Settings
     */
    protected function _settings()
    {
        if ($this->_settings === NULL)
        {
            $this->_settings = Service_Erp801_Settings::instance();
        }

        return $this->_settings;
    }

    /**
     * @return  Service_Erp801_Sources
     */
    protected function _sources()
    {
        if ($this->_sources === NULL)
        {
            $this->_sources = Service_Erp801_Sources::instance();
        }

        return $this->_sources;
    }
}
