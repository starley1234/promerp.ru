<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля «Журнал действий» (erp119_audit).
 *
 * Даёт общие для всех контроллеров модуля вещи: мета-данные страницы, меню,
 * flash-сообщения и контекст запроса, который попадает в записи журнала
 * (маршрут и IP — чтобы потом можно было отличить «правку из интерфейса»
 * от «правки ботом по API»).
 *
 * @package    Erp119
 * @category   Controllers
 */
abstract class Controller_Erp119_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Журнал действий';
        $this->_meta['description'] = 'Кто, когда и что изменил: правки, статусы, удаления и выгрузки.';
        $this->_meta['keywords']    = 'аудит, журнал действий, история изменений, безопасность, erp';

        $this->set_menu(array(
            array(
                'title' => 'Сводка',
                'href'  => '/audit',
                'icon'  => 'fas fa-chart-pie',
                'acl'   => 'erp119_audit.view',
            ),
            array(
                'title' => 'Лента',
                'href'  => '/audit/journal',
                'icon'  => 'fas fa-stream',
                'acl'   => 'erp119_audit.view',
            ),
            array(
                'title' => 'Секции',
                'href'  => '/audit/partitions',
                'icon'  => 'fas fa-database',
                'acl'   => 'erp119_audit.manage',
            ),
        ));

        // Контекст записи: где именно произошло действие
        Service_Erp119_Journal::set_context(array(
            'route' => $this->request->uri(),
            'ip'    => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL,
        ));
    }

    /**
     * Сообщение пользователю (переживает редирект).
     *
     * @param   string  $type     success|error|warning|info
     * @param   string  $message
     * @return  void
     */
    protected function _flash($type, $message)
    {
        $session = Session::instance();
        $flash   = $session->get('erp119_flash', array());
        $flash[] = array('type' => $type, 'message' => $message);
        $session->set('erp119_flash', $flash);
    }

    /**
     * Забрать накопленные сообщения (и очистить).
     *
     * @return  array
     */
    protected function _flash_messages()
    {
        $session = Session::instance();
        $flash   = $session->get('erp119_flash', array());
        $session->set('erp119_flash', array());

        return $flash;
    }

    /**
     * @return  int|null
     */
    protected function _user_id()
    {
        if ($this->_current_user AND $this->_current_user->loaded())
        {
            return (int) $this->_current_user->id;
        }

        return NULL;
    }

    /**
     * Параметр запроса: из POST, иначе из GET.
     *
     * @param   string  $key
     * @param   mixed   $default
     * @return  mixed
     */
    protected function _param($key, $default = NULL)
    {
        $value = $this->request->post($key);

        if ($value === NULL)
        {
            $value = $this->request->query($key);
        }

        return $value === NULL ? $default : $value;
    }

    /**
     * Текущие фильтры ленты из строки запроса.
     *
     * @return  array
     */
    protected function _filters()
    {
        $filters = array(
            'term'        => trim((string) $this->request->query('term')),
            'module'      => trim((string) $this->request->query('module')),
            'entity_type' => trim((string) $this->request->query('entity_type')),
            'entity_id'   => trim((string) $this->request->query('entity_id')),
            'action'      => trim((string) $this->request->query('action')),
            'severity'    => trim((string) $this->request->query('severity')),
            'actor_id'    => trim((string) $this->request->query('actor_id')),
            'source'      => trim((string) $this->request->query('source')),
            'field'       => trim((string) $this->request->query('field')),
            'from'        => trim((string) $this->request->query('from')),
            'to'          => trim((string) $this->request->query('to')),
            'has_changes' => (bool) $this->request->query('has_changes'),
        );

        foreach ($filters as $key => $value)
        {
            if ($value === '' OR $value === FALSE)
            {
                unset($filters[$key]);
            }
        }

        return $filters;
    }

    /**
     * JSON-ответ на POST-кнопку или редирект для обычной формы.
     *
     * @param   callable    $callback
     * @return  void
     */
    protected function _respond($callback)
    {
        $this->auto_render = FALSE;

        $is_form = (bool) Arr::get($_POST, '__html');

        try
        {
            $result = call_user_func($callback);
            $code   = 200;
        }
        catch (HTTP_Exception $e)
        {
            $result = array('status' => 'error', 'message' => $e->getMessage());
            $code   = $e->getCode() >= 400 ? $e->getCode() : 400;
        }
        catch (Exception $e)
        {
            $result = array('status' => 'error', 'message' => $e->getMessage());
            $code   = 400;
        }

        if ($is_form)
        {
            $this->_flash(Arr::get($result, 'status') === 'error' ? 'error' : 'success', Arr::get($result, 'message', 'Готово'));

            $this->redirect(Arr::get($result, 'redirect', '/audit/journal'));
        }

        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
}
