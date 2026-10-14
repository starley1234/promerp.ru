<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контрагенты: список, карточка, создание, изменение, удаление (erp301_counterparty).
 *
 * @package    Erp301
 * @category   Controllers
 */
class Controller_Erp301_Counterparty extends Controller_Erp301_Main
{
    /** @var int */
    const PAGE_SIZE = 25;

    /**
     * Список контрагентов с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp301.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $filters = array(
            'term'   => trim((string) $this->request->query('term')),
            'role'   => $this->request->query('role'),
            'status' => $this->request->query('status'),
            'kind'   => $this->request->query('kind'),
        );

        $page = (int) $this->request->query('page');
        $page = $page > 0 ? $page : 1;

        $service = Service_Erp301_Counterparties::instance();

        $result = $service->search($filters, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        $this->template->content = View::factory('erp301/counterparty/index')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('page_size', self::PAGE_SIZE)
            ->set('kinds', Model_Erp301_Counterparty::$kinds)
            ->set('roles', Model_Erp301_Counterparty::$role_labels)
            ->set('statuses', Model_Erp301_Counterparty::$statuses)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка контрагента: реквизиты, контакты, договоры.
     */
    public function action_view()
    {
        if ( ! $this->can('erp301.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $service = Service_Erp301_Counterparties::instance();
        $id      = (int) $this->request->param('id');

        if ( ! $id)
        {
            throw new HTTP_Exception_404('Контрагент не указан');
        }

        try
        {
            $counterparty = $service->get($id);
        }
        catch (Kohana_Exception $e)
        {
            throw new HTTP_Exception_404('Контрагент не найден');
        }

        $this->template->content = View::factory('erp301/counterparty/view')
            ->set('counterparty', $counterparty)
            ->set('contacts', $service->contacts($counterparty->id)->find_all())
            ->set('requisites', $service->requisites($counterparty->id)->find_all())
            ->set('contracts', $counterparty->active_contracts()->find_all())
            ->set('printable', $service->printable_requisites($counterparty->id))
            ->set('contract_kinds', Model_Erp301_Contract::$kinds)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма создания/редактирования контрагента.
     */
    public function action_edit()
    {
        if ( ! $this->can('erp301.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $service = Service_Erp301_Counterparties::instance();
        $id      = (int) $this->request->param('id');

        if ($id)
        {
            try
            {
                $counterparty = $service->get($id);
            }
            catch (Kohana_Exception $e)
            {
                throw new HTTP_Exception_404('Контрагент не найден');
            }
        }
        else
        {
            $counterparty = ORM::factory('Erp301_Counterparty');
        }

        $this->template->content = View::factory('erp301/counterparty/form')
            ->set('counterparty', $counterparty)
            ->set('kinds', Model_Erp301_Counterparty::$kinds)
            ->set('roles', Model_Erp301_Counterparty::$role_labels)
            ->set('statuses', Model_Erp301_Counterparty::$statuses)
            ->set('errors', array())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранение контрагента (POST).
     */
    public function action_save()
    {
        if ( ! $this->can('erp301.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/counterparties/list');
        }

        $post    = $this->request->post();
        $service = Service_Erp301_Counterparties::instance();
        $id      = (int) Arr::get($post, 'id');

        // ВАЖНО: redirect() бросает HTTP_Exception_302, а HTTP_Exception наследует
        // Kohana_Exception. Редирект обязан стоять ВНЕ try — иначе успешное сохранение
        // перехватывается общим catch и превращается в ошибку (сам факт записи уже был).
        $redirect = NULL;

        try
        {
            if ($id)
            {
                $counterparty = $service->update($id, $post, $this->_user_id());
                $this->_flash('success', 'Контрагент «'.$counterparty->name.'» обновлён');
            }
            else
            {
                $counterparty = $service->create($post, $this->_user_id());
                $this->_flash('success', 'Контрагент «'.$counterparty->name.'» создан (код '.$counterparty->code.')');
            }

            $redirect = '/counterparties/view/'.$counterparty->id;
        }
        catch (ORM_Validation_Exception $e)
        {
            $counterparty = ORM::factory('Erp301_Counterparty');

            if ($id)
            {
                try
                {
                    $counterparty = $service->get($id);
                }
                catch (Kohana_Exception $read_error)
                {
                    // запись не читается — показываем пустую форму;
                    // переменную $e не трогаем: ниже по ней печатаются ошибки валидации
                    $counterparty = ORM::factory('Erp301_Counterparty');
                }
            }

            $counterparty->values($post, array(
                'code', 'name', 'full_name', 'kind', 'inn', 'kpp', 'ogrn',
                'country', 'city', 'address', 'phone', 'email', 'site', 'note', 'status',
            ));

            $this->template->content = View::factory('erp301/counterparty/form')
                ->set('counterparty', $counterparty)
                ->set('kinds', Model_Erp301_Counterparty::$kinds)
                ->set('roles', Model_Erp301_Counterparty::$role_labels)
                ->set('statuses', Model_Erp301_Counterparty::$statuses)
                ->set('errors', $e->errors(''))
                ->set('messages', $this->_flash_messages());

            return;
        }

        $this->redirect($redirect);
    }

    /**
     * Мягкое удаление контрагента (POST).
     */
    public function action_delete()
    {
        if ( ! $this->can('erp301.delete'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/counterparties/list');
        }

        $id      = (int) Arr::get($this->request->post(), 'id');
        $service = Service_Erp301_Counterparties::instance();

        try
        {
            $counterparty = $service->get($id);
            $service->delete($id, $this->_user_id());

            $this->_flash('success', 'Контрагент «'.$counterparty->name.'» перемещён в архив');
        }
        catch (Exception $e)
        {
            $this->_flash('error', 'Не удалось удалить: '.$e->getMessage());
        }

        $this->redirect('/counterparties/list');
    }

    /**
     * Добавление/изменение контактного лица (POST, JSON).
     */
    public function action_contact_save()
    {
        return $this->_respond(function () {
            if ( ! $this->can('erp301.manage'))
            {
                throw new HTTP_Exception_403('Доступ запрещён');
            }

            $post = $this->request->post();
            $counterparty_id = (int) Arr::get($post, 'counterparty_id');
            $contact_id      = (int) Arr::get($post, 'contact_id');

            $contact = Service_Erp301_Counterparties::instance()
                ->save_contact($counterparty_id, $post, $contact_id ?: NULL);

            return array(
                'status'   => 'ok',
                'id'       => (int) $contact->id,
                'message'  => 'Контакт «'.$contact->name.'» сохранён',
                'redirect' => '/counterparties/view/'.$counterparty_id,
            );
        });
    }

    /**
     * Удаление контактного лица (POST, JSON).
     */
    public function action_contact_delete()
    {
        return $this->_respond(function () {
            if ( ! $this->can('erp301.manage'))
            {
                throw new HTTP_Exception_403('Доступ запрещён');
            }

            $post = $this->request->post();

            Service_Erp301_Counterparties::instance()->delete_contact(
                (int) Arr::get($post, 'counterparty_id'),
                (int) Arr::get($post, 'contact_id')
            );

            return array(
                'status'   => 'ok',
                'message'  => 'Контакт удалён',
                'redirect' => '/counterparties/view/'.(int) Arr::get($post, 'counterparty_id'),
            );
        });
    }

    /**
     * Добавление/изменение реквизитов (POST, JSON).
     */
    public function action_requisite_save()
    {
        return $this->_respond(function () {
            if ( ! $this->can('erp301.manage'))
            {
                throw new HTTP_Exception_403('Доступ запрещён');
            }

            $post = $this->request->post();

            $requisite = Service_Erp301_Counterparties::instance()->save_requisite(
                (int) Arr::get($post, 'counterparty_id'),
                $post,
                ((int) Arr::get($post, 'requisite_id')) ?: NULL
            );

            return array(
                'status'   => 'ok',
                'id'       => (int) $requisite->id,
                'message'  => 'Реквизиты сохранены',
                'redirect' => '/counterparties/view/'.(int) $requisite->counterparty_id,
            );
        });
    }

    /**
     * Обёртка ответа для submart-операций карточки.
     *
     * Если форма отправлена обычным POST (скрытое поле __html=1), выполняется
     * редирект на карточку с flash-сообщением — интерфейс работает без JS.
     * В остальных случаях возвращается JSON (тот же метод служит мини-API).
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

            $this->redirect('/counterparties/list');
        }

        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
}
