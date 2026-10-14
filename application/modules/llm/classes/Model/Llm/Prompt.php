<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель для работы с шаблонами промптов LLM
 */
class Model_Llm_Prompt extends ORM {

    protected $_table_name = 'llm_prompts';

    protected $_table_columns = [
        'id'            => ['type' => 'int'],
        'name'          => ['type' => 'string'],
        'description'   => ['type' => 'string'],
        'system'        => ['type' => 'string'],
        'prompt'        => ['type' => 'string'],
        'provider_name' => ['type' => 'string'],
        'model_name'    => ['type' => 'string'],
        'options'       => ['type' => 'string'], // JSON
        'developer_notes'    => ['type' => 'string'],
        'created_at'    => ['type' => 'int'],
        'updated_at'    => ['type' => 'int'],
    ];

    /**
     * Правила валидации
     */
    public function rules()
    {
        return [
            'name' => [
                ['not_empty'],
                ['max_length', [':value', 100]],
            ],
            'prompt' => [
                ['not_empty'],
            ],
        ];
    }

    /**
     * Автоматическое обновление меток времени
     */
    public function save(?Validation $validation = NULL)
    {
        if ($this->_loaded === FALSE) {
            $this->created_at = time();
        }
        $this->updated_at = time();

        return parent::save($validation);
    }

    /**
     * Получение декодированных опций
     * 
     * @return array
     */
    public function get_options()
    {
        if (empty($this->options)) {
            return [];
        }
        
        $decoded = json_decode($this->options, TRUE);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Подготовка текста промпта с подстановкой переменных
     * 
     * @param array $variables Ассоциативный массив переменных {{key}} => value
     * @return string
     */
    public function compile_prompt(array $variables = [])
    {
        $content = $this->prompt;

        foreach ($variables as $key => $value) {
            $val_str = (is_array($value) || is_object($value)) 
                ? json_encode($value, JSON_UNESCAPED_UNICODE) 
                : (string)$value;
                
            $content = str_replace('{{' . $key . '}}', $val_str, $content);
        }

        return $content;
    }
}