<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля технологических карт (erp228_tech_cards).
 *
 * Задаёт мета-данные, меню модуля и общие помощники: flash-сообщения,
 * id текущего пользователя и ответ «JSON или форма» для POST-действий.
 *
 * @package    Erp228
 * @category   Controllers
 */
abstract class Controller_Erp228_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Технологические карты';
        $this->_meta['description'] = 'Исходник изделия, техпроцесс, операции, нормы времени и стоимость.';
        $this->_meta['keywords']    = 'техкарты, техпроцесс, операции, производство, openSCAD, erp';

        $this->set_menu(array(
            array(
                'title' => 'Сводка',
                'href'  => '/tech_cards',
                'icon'  => 'fas fa-chart-pie',
                'acl'   => 'erp228.view',
            ),
            array(
                'title' => 'Карты',
                'href'  => '/tech_cards/list',
                'icon'  => 'fas fa-layer-group',
                'acl'   => 'erp228.view',
            ),
            array(
                'title' => 'Новая техкарта',
                'href'  => '/tech_cards/editor',
                'icon'  => 'fas fa-plus-circle',
                'acl'   => 'erp228.manage',
            ),
        ));
    }

    /**
     * Сообщение пользователю (переживает редирект).
     *
     * @param  string $type    success|error|warning|info
     * @param  string $message
     * @return void
     */
    protected function _flash($type, $message)
    {
        $session = Session::instance();
        $flash   = $session->get('erp228_flash', array());
        $flash[] = array('type' => $type, 'message' => $message);
        $session->set('erp228_flash', $flash);
    }

    /**
     * Забрать накопленные сообщения (и очистить).
     *
     * @return array
     */
    protected function _flash_messages()
    {
        $session = Session::instance();
        $flash   = $session->get('erp228_flash', array());
        $session->set('erp228_flash', array());

        return $flash;
    }

    /**
     * @return int|null
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
     * Действие для POST-кнопок карточки: JSON для API/AJAX и редирект для формы.
     *
     * Если форма отправлена обычным POST (скрытое поле __html=1), выполняется
     * редирект с flash-сообщением — интерфейс работает без JS. В остальных
     * случаях возвращается JSON (тот же метод служит мини-API).
     *
     * @param  callable $callback
     * @return void
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
        catch (ORM_Validation_Exception $e)
        {
            $result = array('status' => 'error', 'message' => 'Проверьте поля', 'errors' => $e->errors(''));
            $code   = 422;
        }
        catch (Exception $e)
        {
            $result = array('status' => 'error', 'message' => $e->getMessage());
            $code   = 400;
        }

        if ($is_form)
        {
            $this->_flash($result['status'] === 'error' ? 'error' : 'success', $result['message']);

            if ( ! empty($result['redirect']))
            {
                $this->redirect($result['redirect']);
            }

            $this->redirect('/tech_cards/list');
        }

        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
}
