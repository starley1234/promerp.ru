<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Старые адреса CRM: `/erp221/<раздел>(/<действие>(/<id>))`.
 *
 * Исходный модуль держал восемь отдельных контроллеров, поэтому ссылки в
 * заметках менеджеров и письмах выглядят как `/erp221/deal/view/12`. Новый
 * модуль свёл разделы к двум контроллерам (`Index`, `Api`), но старые адреса
 * не выбрасываем: они переводятся на актуальную страницу постоянным
 * перенаправлением 301, а неизвестный раздел честно отвечает 404.
 *
 * @package    Erp221
 * @category   Controllers
 */
class Controller_Erp221_Legacy extends Controller_Erp221_Main
{
    /**
     * Что стало со старым разделом: `раздел.действие` → действие нового модуля.
     *
     * Ключ `index` — действие исходного контроллера по умолчанию; его же
     * подставляет маршрут, когда в старом адресе нет сегмента действия.
     *
     * @var array
     */
    protected $_map = array(
        'deal'      => array('' => 'deals', 'index' => 'deals', 'kanban' => 'deals',
                             'view' => 'deal', 'detail' => 'deal', 'edit' => 'deal',
                             'save' => 'deal_save', 'stage' => 'deal_stage',
                             'delete' => 'deal_delete', 'contact' => 'deal_contact'),
        'client'    => array('' => 'clients', 'index' => 'clients', 'list' => 'clients',
                             'view' => 'client', 'detail' => 'client', 'edit' => 'client',
                             'save' => 'client_save', 'stats' => 'clients'),
        'contact'   => array('' => 'clients', 'index' => 'clients',
                             'save' => 'contact_save', 'delete' => 'contact_delete'),
        'stage'     => array('' => 'stages', 'index' => 'stages', 'settings' => 'stages',
                             'save' => 'stage_save', 'delete' => 'stage_delete'),
        'activity'  => array('' => 'journal', 'index' => 'journal', 'timeline' => 'journal',
                             'calendar' => 'journal', 'save' => 'activity_save',
                             'delete' => 'activity_delete'),
        'analytics' => array('' => 'analytics', 'index' => 'analytics', 'dashboard' => 'analytics',
                             'loss' => 'loss_analysis', 'losses' => 'loss_analysis'),
        'tool'      => array('' => 'kb', 'index' => 'kb', 'kb' => 'kb',
                             'search' => 'suggest', 'suggest' => 'suggest'),
        'exchange'  => array('' => 'export', 'index' => 'export', 'export' => 'export',
                             'import' => 'import', 'template' => 'export'),
    );

    /**
     * Раздел, у которого есть короткая ссылка на карточку: `/erp221/deal/12`.
     *
     * @var array
     */
    protected $_cards = array('deal' => 'deal', 'client' => 'client');

    /**
     * Перевести старый адрес на страницу нового модуля.
     *
     * @return  void
     * @throws  HTTP_Exception_404
     */
    public function action_index()
    {
        $section = trim((string) $this->request->param('section'));
        // Старое действие — сегмент `step`: имя `action` в маршруте трогать
        // нельзя, по нему Kohana выбирает метод контроллера.
        $action  = trim((string) $this->request->param('step', 'index'));
        $id      = $this->request->param('id');

        // `/erp221` и `/erp221/index` — это прежняя доска сделок.
        if ($section === '' OR $section === 'index')
        {
            $this->_go('deals');
        }

        // Короткая ссылка на карточку: `/erp221/deal/12`.
        if ($action !== '' AND ctype_digit($action) AND isset($this->_cards[$section]))
        {
            $this->_go($this->_cards[$section], $action);
        }

        if (isset($this->_map[$section]))
        {
            $target = Arr::get($this->_map[$section], $action);
        }
        elseif ($this->_page_exists($section))
        {
            // Раздел уже назван так, как называется страница нового модуля.
            $target = $section;
        }
        else
        {
            $target = NULL;
        }

        if ($target === NULL)
        {
            throw new HTTP_Exception_404('Старый адрес CRM «:url» не найден', array(
                ':url' => trim($section.'/'.$action, '/'),
            ));
        }

        $this->_go($target, $id);
    }

    /**
     * Проверить доступ и увести на страницу нового модуля постоянным адресом.
     *
     * @param   string       $action
     * @param   mixed        $id
     * @return  void
     */
    protected function _go($action, $id = NULL)
    {
        $this->allow_or_403('erp221.view');

        $params = array('action' => $action);

        if ($id !== NULL AND $id !== '')
        {
            $params['id'] = $id;
        }

        $this->redirect(Route::url('erp221_index', $params), 301);
    }

    /**
     * Существует ли у нового модуля страница с таким действием.
     *
     * @param   string  $name
     * @return  boolean
     */
    protected function _page_exists($name)
    {
        return $name !== '' AND method_exists('Controller_Erp221_Index', 'action_'.$name);
    }
}
