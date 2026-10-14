<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер управления шаблонами уведомлений (CRUD).
 * Доступ ограничен ролями admin и director.
 * 
 * @package    ERP217
 * @category   Controller
 * @author     Generated for Notification Center
 */
class Controller_Erp217_Templates extends Controller_Erp217_Main {

    /**
     * Проверка прав доступа перед выполнением действий
     */
    public function before()
    {
        parent::before();

        // Ограничение доступа: только для администраторов и директоров
        if ( ! $this->has_any_role(['admin', 'director']))
        {
            throw new HTTP_Exception_403('У вас недостаточно прав для управления шаблонами уведомлений.');
        }

        $this->set_title('Управление шаблонами уведомлений');
    }

    /**
     * Список всех шаблонов
     */
    public function action_index()
    {
        $templates = ORM::factory('Erp217_Template')
            ->where('is_deleted', '=', 0)
            ->find_all();

        $this->template->content = View::factory('erp217/templates/list')
            ->set('templates', $templates)
            ->set('channels', Model_Erp217_Template::get_channels());
    }

    /**
     * Создание нового шаблона
     */
    public function action_add()
    {
        $this->action_edit();
    }

    /**
     * Редактирование шаблона
     */
    public function action_edit()
    {
        $id = $this->request->param('id');
        $template = ORM::factory('Erp217_Template', $id);

        if ($id && ! $template->loaded())
        {
            throw new HTTP_Exception_404('Шаблон не найден');
        }

        if ($this->request->method() === Request::POST)
        {
            try
            {
                $data = $this->request->post();
                
                // Если это создание, фиксируем автора до валидации
                if ( ! $template->loaded())
                {
                    $template->created_by = $this->_current_user->id;
                }

                $template->values($data, array('alias', 'channel', 'subject', 'body', 'description'));
                $template->save();

                // Логирование действия в аудит
                // Audit::log('notification_template_save', ['id' => $template->id, 'alias' => $template->alias]);

                HTTP::redirect('/erp217_templates');
            }
            catch (ORM_Validation_Exception $e)
            {
                $errors = $e->errors('models');
            }
        }

        $this->template->content = View::factory('erp217/templates/form')
            ->set('template', $template)
            ->set('channels', Model_Erp217_Template::get_channels())
            ->set('errors', isset($errors) ? $errors : array());
    }

    /**
     * Удаление шаблона (Soft Delete)
     */
    public function action_delete()
    {
        $id = $this->request->param('id');
        $template = ORM::factory('Erp217_Template', $id);

        if ($template->loaded())
        {
            $template->delete();
            // Audit::log('notification_template_delete', ['id' => $id]);
        }

        HTTP::redirect('/erp217_templates');
    }

    /**
     * Предпросмотр/Тестовая отправка (AJAX)
     */
    public function action_test()
    {
        $this->auto_render = FALSE;
        $id = $this->request->query('id');
        
        if ( ! $id) return;

        $template = ORM::factory('Erp217_Template', $id);
        if ( ! $template->loaded()) return;

        // Логика для отправки тестового сообщения текущему пользователю
        // Service_Erp217_Bus::instance()->send_test($template->alias, $this->_current_user->id);

        $this->response->body(json_encode(['status' => 'success', 'message' => 'Тестовое уведомление поставлено в очередь']));
    }

    /**
     * Генерация шаблона через LLM (AJAX)
     * Принимает сырой текст: alias + JSON данные
     */
    public function action_generate_llm()
    {
        try 
        {
            $raw_input = $this->request->post('raw_data');
            if (empty($raw_input))
            {
                throw new Exception('Данные не получены');
            }

            // Разделяем alias и JSON (первая строка - alias, остальное - данные)
            $lines = explode("\n", trim($raw_input));
            $alias = trim(array_shift($lines));
            $json_data = implode("\n", $lines);

            $system_prompt = "Ты — эксперт по настройке CRM уведомлений. 
            Твоя задача: создать шаблон внутреннего уведомления на основе предоставленного события и JSON данных.
            
            ПРАВИЛО 'Суть - Объект - Ссылка':
            1. Заголовок (subject): Коротко, что случилось (например: 'Новая задача', 'Статус изменен').
            2. Тело (body): Краткие детали, используя переменные из JSON в формате {{variable_name}}.
            3. Ссылка: Обязательно добавь в конец тела призыв к действию со ссылкой, если в данных есть ID объекта.
            
            Верни ответ СТРОГО в формате JSON с ключами: subject, body.";

            $user_prompt = "Событие: {$alias}\nДанные: {$json_data}";

            //$llm_response = Llm::model('openrouter::google/gemini-2.0-flash-lite-001')->
             $llm_response = Llm::
                system($system_prompt)
                ->extract($user_prompt, ['subject', 'body']);

            if (empty($llm_response['subject']) || empty($llm_response['body']))
            {
                throw new Exception('LLM не смогла сформировать шаблон');
            }

            // Создаем или обновляем шаблон для канала 'max' (внутренние)
            $template = ORM::factory('Erp217_Template')
                ->where('alias', '=', $alias)
                ->where('channel', '=', 'max')
                ->where('is_deleted', '=', 0)
                ->find();

            if ( ! $template->loaded())
            {
                $template->alias = $alias;
                $template->channel = 'push';
                $template->created_by = $this->_current_user->id;
            }

            $template->subject = $llm_response['subject'];
            $template->body = $llm_response['body'];
            $template->description = "Сгенерировано автоматически через LLM для события " . $alias;
            $template->save();

            return $this->_json_response([
                'status' => 'success',
                'data' => [
                    'id' => $template->id,
                    'alias' => $template->alias,
                    'subject' => $template->subject,
                    'body' => $template->body
                ]
            ]);
        }
        catch (Exception $e)
        {
            return $this->_json_response([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}