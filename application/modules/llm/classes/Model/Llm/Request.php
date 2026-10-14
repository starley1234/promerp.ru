<?php defined('SYSPATH') or die('No direct script access.');

class Model_Llm_Request extends ORM {

    protected $_table_name = 'llm_requests';

    protected $_table_columns = [
        'id'                 => ['type' => 'int'],
        'provider_name'      => ['type' => 'string'],
        'model_name'         => ['type' => 'string'],
        'request_messages'   => ['type' => 'string'], // JSON
        'request_options'    => ['type' => 'string'], // JSON
        'response_data'      => ['type' => 'string'], // JSON
        'prompt_tokens'      => ['type' => 'int'],
        'completion_tokens'  => ['type' => 'int'],
        'total_tokens'       => ['type' => 'int'],
        'request_timestamp'  => ['type' => 'int'],
        'response_timestamp' => ['type' => 'int'],
        'duration_ms'        => ['type' => 'int'],
        'status'             => ['type' => 'string'],
        'error_message'      => ['type' => 'string'],
        'user_id'            => ['type' => 'int'],
        'session_id'         => ['type' => 'string'],
    ];

    /**
     * Автоматическая установка метки времени при создании
     */
    public function create(?Validation $validation = NULL)
    {
        if (empty($this->request_timestamp)) {
            $this->request_timestamp = time();
        }
        return parent::create($validation);
    }

    /**
     * Декодирование JSON полей
     */
    public function get_messages()
    {
        return json_decode($this->request_messages, TRUE);
    }

    public function get_options()
    {
        return json_decode($this->request_options, TRUE);
    }

    public function get_response()
    {
        return json_decode($this->response_data, TRUE);
    }
}