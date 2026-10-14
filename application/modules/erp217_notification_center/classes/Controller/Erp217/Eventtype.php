<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер управления типами событий (Центр уведомлений ERP217).
 */
class Controller_Erp217_Eventtype extends Controller_Erp217_Main {

    /**
     * Отображение списка типов событий
     */
    public function action_index()
    {
        $this->set_title(__('Типы событий уведомлений'));

        $event_types = ORM::factory('Erp217_EventType')
            ->where('is_deleted', '=', 0)
            ->find_all();

        $this->template->content = View::factory('erp217/eventtype/index')
            ->set('items', $event_types);
    }

    /**
     * AJAX сохранение (добавление или редактирование)
     */
    public function action_ajax_save()
    {
        if ($this->request->method() !== Request::POST) {
            throw (new HTTP_Exception_405('Method Not Allowed'))->allowed(array('POST'));
        }

        $id = $this->request->post('id');
        $model = ORM::factory('Erp217_EventType', $id);

        try {
            $model->values($this->request->post(), array('alias', 'name', 'description', 'allowed_roles'));
            
            if ( ! $model->loaded()) {
                $model->created_by = $this->_current_user->id;
            }

            $model->save();

            $this->json_response([
                'status' => 'success',
                'message' => __('Данные успешно сохранены'),
                'item' => $model->as_array()
            ]);
        } catch (ORM_Validation_Exception $e) {
            $this->json_response([
                'status' => 'error',
                'errors' => $e->errors('models')
            ]);
        } catch (Exception $e) {
            $this->json_response([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX получение данных для редактирования
     */
    public function action_ajax_get()
    {
        $id = $this->request->query('id');
        $model = ORM::factory('Erp217_EventType', $id);

        if ( ! $model->loaded() || $model->is_deleted) {
            $this->json_response(['status' => 'error', 'message' => __('Запись не найдена')], 404);
        }

        $this->json_response([
            'status' => 'success',
            'data' => $model->as_array()
        ]);
    }

    /**
     * AJAX удаление (мягкое)
     */
    public function action_ajax_delete()
    {
        if ($this->request->method() !== Request::POST) {
            throw (new HTTP_Exception_405('Method Not Allowed'))->allowed(array('POST'));
        }

        $id = $this->request->post('id');
        $model = ORM::factory('Erp217_EventType', $id);

        if ($model->loaded()) {
            $model->is_deleted = 1;
            $model->save();
            
            $this->json_response([
                'status' => 'success',
                'message' => __('Запись успешно удалена')
            ]);
        } else {
            $this->json_response([
                'status' => 'error',
                'message' => __('Запись не найдена')
            ], 404);
        }
    }

    /**
     * Вспомогательный метод для JSON ответов
     */
    protected function json_response($data, $code = 200)
    {
        $this->auto_render = FALSE;
        $this->response
            ->status($code)
            ->headers('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode($data));
    }
}