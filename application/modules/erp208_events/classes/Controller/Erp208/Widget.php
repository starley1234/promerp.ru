<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Erp208
 * @version 1.0.0
 * @author AI
 */
class Controller_Erp208_Widget extends Controller_Base {

    /**
     * Отображает виджет последних событий.
     * Этот метод предназначен для HMVC-вызовов, например, для встраивания на дашборд.
     *
     * @return void
     */
    public function action_latest_events()
    {
        $this->auto_render = FALSE;

        if (!$this->can('erp208_events.view_latest_events_widget')) {
            $this->response->body('<div class="alert alert-warning">' . __('У вас нет прав для просмотра виджета последних событий.') . '</div>');
            return;
        }

        try
        {
            $latest_events = ORM::factory('Erp208_Event')
                ->order_by('created_at', 'DESC')
                ->limit(5)
                ->find_all();

            $view = View::factory('erp208/widget/latest_events')
                ->set('latest_events', $latest_events);

            $this->response->body($view->render());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'Ошибка в Controller_Erp208_Widget::action_latest_events: :error',
                array(':error' => $e->getMessage()));

            $this->response->body('<div class="alert alert-danger">' . __('Не удалось загрузить последние события.') . '</div>');
        }
    }

    /**
     * Отображает форму для публикации нового события.
     * Этот метод предназначен для HMVC-вызовов, например, для встраивания на дашборд
     * или в другую часть интерфейса, где требуется быстрая публикация события.
     *
     * @return void
     */
    public function action_event_publisher_form()
    {
        $this->auto_render = FALSE;

        if (!$this->can('erp208_events.publish_event_widget')) {
            $this->response->body('<div class="alert alert-warning">' . __('У вас нет прав для публикации событий через виджет.') . '</div>');
            return;
        }

        $errors = array();
        $post_data = array();

        if ($post = $this->request->post())
        {
            if (isset($post['event_name']) && isset($post['payload']))
            {
                $event_name = (string) $post['event_name'];
                $payload_string = (string) $post['payload'];
                $payload = array();

                if (!empty($payload_string)) {
                    $decoded_payload = json_decode($payload_string, TRUE);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $payload = $decoded_payload;
                    } else {
                        $errors['payload'] = __('Полезная нагрузка должна быть валидной JSON строкой или пустой.');
                    }
                }

                if (empty($event_name)) {
                    $errors['event_name'] = __('Имя события не может быть пустым.');
                }

                if (empty($errors))
                {
                    try
                    {
                        $event_bus_service = Service_Erp208_EventBus::instance();
                        $event_bus_service->publish($event_name, $payload);

                        Event::fire('erp208_events.published', (object)['name' => $event_name, 'payload' => $payload]);

                        $post_data = array();
                        $this->response->body('<div class="alert alert-success">' . __('Событие ":event_name" успешно опубликовано!', array(':event_name' => $event_name)) . '</div>');
                        return;
                    }
                    catch (Exception $e)
                    {
                        Kohana::$log->add(Log::ERROR, 'Ошибка публикации события из виджета: :error',
                            array(':error' => $e->getMessage()));
                        $errors['general'] = __('Не удалось опубликовать событие: :error', array(':error' => $e->getMessage()));
                    }
                }
            }
            else
            {
                $errors['general'] = __('Отсутствует имя события или полезная нагрузка.');
            }
            $post_data = $post;
        }

        $view = View::factory('erp208/widget/event_publisher_form')
            ->set('errors', $errors)
            ->set('post_data', $post_data);

        $config = Kohana::$config->load('erp208_events');
        $event_name_suggestions = $config['event_name_suggestions'] ?? array();
        $view->set('event_name_suggestions', $event_name_suggestions);

        $this->response->body($view->render());
    }
}