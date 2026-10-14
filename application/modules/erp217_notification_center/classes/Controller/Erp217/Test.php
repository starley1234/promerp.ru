<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Диагностическая страница центра уведомлений (только DEV/TESTING).
 *
 * Публикует тестовое событие смены стадии сделки и запускает обработку очереди —
 * так можно проверить, что шина, шаблоны и отправка работают, не трогая боевые данные.
 *
 *   /erp217_test                        — прогнать сценарий и увидеть отчёт в JSON
 *   /erp217_test?notification_id=3      — обработать конкретное уведомление
 *
 * В PRODUCTION страница отключена (403). Раньше контроллер печатал `print_r($payload)`
 * прямо в ответ, что ломало JSON-клиентов и вывод CLI-задач.
 */
class Controller_Erp217_Test extends Controller_Erp217_Main {

    public function action_index()
    {
        if (Kohana::$environment === Kohana::PRODUCTION)
        {
            throw new HTTP_Exception_403('Диагностика уведомлений доступна только в DEV/TESTING');
        }

        $notification_id = (int) $this->request->query('notification_id');

        $payload = [
            'deal_id'        => 77,
            'title'          => 'Тест '.date('Y-m-d H:i:s'),
            'new_stage_id'   => 78,
            'target_user_id' => 1,
            'is_notification' => TRUE,
        ];

        $result = ['payload' => $payload, 'events' => [], 'processed' => NULL, 'error' => NULL];

        try
        {
            Event::fire('erp221.deal.stage_changed.api', $payload);
            $result['events'][] = 'erp221.deal.stage_changed.api';

            if ($notification_id)
            {
                $result['processed'] = Service_Erp217_Bus::instance()->process_notification($notification_id);
            }
        }
        catch (Throwable $e)
        {
            $result['error'] = $e->getMessage();
        }

        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
        $this->response->body(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
