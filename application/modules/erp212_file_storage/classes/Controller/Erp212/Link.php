<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер для управления временными ссылками на файлы.
 * Позволяет генерировать новые токены доступа и просматривать существующие.
 */
class Controller_Erp212_Link extends Controller {

    /**
     * Генерация новой временной ссылки для файла.
     * Принимает POST запрос с file_id и опционально ttl.
     */
    public function action_create()
    {
        $this->response->headers('Content-Type', 'application/json');
        
        $file_id = $this->request->post('file_id');
        $ttl = $this->request->post('ttl') ?: 3600;

        if (!$file_id) {
            $this->response->status(400);
            $this->response->body(json_encode([
                'success' => false,
                'error' => 'Параметр file_id обязателен'
            ]));
            return;
        }

        try {
            $url = Service_Erp212_Storage::instance()->generateUrl($file_id, $ttl);
            
            $this->response->body(json_encode([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'expires_in' => $ttl
                ]
            ]));
        } catch (Exception $e) {
            $this->response->status(404);
            $this->response->body(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * Получение списка активных ссылок для конкретного файла.
     */
    public function action_list()
    {
        $this->response->headers('Content-Type', 'application/json');
        
        $file_id = $this->request->query('file_id');
        
        if (!$file_id) {
            $this->response->status(400);
            $this->response->body(json_encode(['error' => 'file_id is required']));
            return;
        }

        $links = ORM::factory('Erp212_Link')
            ->where('file_id', '=', $file_id)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->find_all();

        $data = [];
        foreach ($links as $link) {
            $data[] = [
                'id' => $link->id,
                'token' => $link->token,
                'expires_at' => $link->expires_at,
                'click_count' => $link->click_count,
                'remaining_ttl' => $link->get_remaining_ttl()
            ];
        }

        $this->response->body(json_encode([
            'success' => true,
            'data' => $data
        ]));
    }

    /**
     * Удаление (аннулирование) временной ссылки.
     */
    public function action_delete()
    {
        $this->response->headers('Content-Type', 'application/json');
        
        $id = $this->request->param('id');
        $link = ORM::factory('Erp212_Link', $id);

        if (!$link->loaded()) {
            $this->response->status(404);
            $this->response->body(json_encode(['error' => 'Link not found']));
            return;
        }

        $link->delete();

        $this->response->body(json_encode([
            'success' => true,
            'message' => 'Link deleted successfully'
        ]));
    }
}