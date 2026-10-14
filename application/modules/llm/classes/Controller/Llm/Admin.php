<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Llm_Admin extends Controller_Base {

    public function action_index()
    {
        $config_llm = Kohana::$config->load('llm');
        $config_providers = $config_llm->get('providers');
        $config = $config_providers['local_lms'];
        
        $base_url = $config['base_url'] ?? 'http://localhost:1234/v1/';
        $api_key = $config['api_key'] ?? '';
        
        $status_data = [];
        $error = null;
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $base_url . 'models');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $api_key
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }
            
            curl_close($ch);
            
            if ($http_code >= 200 && $http_code < 300) {
                $status_data = json_decode($response, true);
            } else {
                $error = "Server returned HTTP $http_code: $response";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        
        $menu = array(
            array(
                'title' => 'Управление локальными LLM',
                'href'  => '/llm_admin',
                'icon'  => 'fas fa-robot'
            ),
        );
        $this->set_menu($menu);
        
        $this->_meta['title'] = 'Управление локальными LLM';
        $this->_meta['description'] = 'Перечень LLM, загрузка, выгрузка';

        $this->template->content = View::factory('llm/admin')
            ->set('status_data', $status_data)
            ->set('error', $error)
            ->set('base_url', $base_url);
    }

    /**
     * Выгрузка модели (Unload) с поддержкой AJAX
     */
    public function action_unload()
    {
        $this->auto_render = FALSE;
        $model_id = $this->request->query('model');
        $is_ajax = $this->request->is_ajax();

        if (!$model_id) {
            if ($is_ajax) {
                $this->response->status(400)->body(json_encode(['error' => 'Model ID is required']));
                return;
            }
            throw new HTTP_Exception_400('Model ID is required');
        }

        $config = Kohana::$config->load('llm.providers.local_lms');
        // Убираем /v1/ из базового пути для обращения к /api/v1/models/unload если это требуется API
        $base_url = $config['base_url'] ?? 'http://localhost:1234/v1/';
        $api_key = $config['api_key'] ?? '';
        
        // Корректируем URL для выгрузки согласно документации LM Studio
        // Обычно это POST /api/v1/models/unload или /v1/models/unload
        $unload_url = $base_url . 'models/unload';

        $success = false;
        $message = '';

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $unload_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['instance_id' => $model_id]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code >= 200 && $http_code < 300) {
                $success = true;
                $message = "Model $model_id unloaded successfully.";
            } else {
                $message = "Failed to unload model. Server returned code: $http_code. Response: $response";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }

        if ($is_ajax) {
            $this->response->headers('Content-Type', 'application/json');
            $this->response->body(json_encode([
                'success' => $success,
                'message' => $message
            ]));
            return;
        }

        if ($success) {
            Message::success($message);
        } else {
            Message::error($message);
        }

        $this->redirect('admin/llm');
    }
	
	
	/**
	 * Загрузка модели (Load)
	 */
	public function action_load()
	{
		$this->auto_render = FALSE;
		// model_id для загрузки (например: "lmstudio-community/Meta-Llama-3-8B-Instruct-GGUF")
		$model_id = $this->request->query('model');
		$is_ajax = $this->request->is_ajax();

		if (!$model_id) {
			if ($is_ajax) {
				return $this->response->status(400)->body(json_encode(['error' => 'Model ID is required']));
			}
			throw new HTTP_Exception_400('Model ID is required');
		}

		$config = Kohana::$config->load('llm.providers.local_lms');
		$base_url = $config['base_url'] ?? 'http://localhost:1234/v1/';
		$api_key = $config['api_key'] ?? '';
		
		// Эндпоинт загрузки
		$load_url = $base_url . 'models/load';

		$success = false;
		$message = '';

		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $load_url);
			curl_setopt($ch, CURLOPT_POST, true);
			// Важно: LM Studio ожидает параметр 'model' согласно API документации
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				'model' => $model_id
			]));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Authorization: Bearer ' . $api_key,
				'Content-Type: application/json'
			]);
			
			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			$success = ($http_code >= 200 && $http_code < 300);
			$res_data = json_decode($response, true);
			$error_part = $res_data['error'] ?? $response;
			if (is_array($error_part)) {
				$error_part = json_encode($error_part);
			}
			$message = $success ? "Model loaded" : "Error: " . $error_part;
		} catch (Exception $e) {
			$success = false;
			$message = $e->getMessage();
		}

		if ($is_ajax) {
			$this->response->headers('Content-Type', 'application/json');
			$this->response->body(json_encode(['success' => $success, 'message' => $message]));
			return;
		}

		$success ? Message::success($message) : Message::error($message);
		$this->redirect('admin/llm');
	}
	
}