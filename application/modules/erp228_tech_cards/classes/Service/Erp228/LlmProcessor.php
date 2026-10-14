<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Генерация техпроцесса по техкарте (erp228_tech_cards).
 *
 * Порядок работы:
 *   1. карта переводится в статус «генерация» (processing);
 *   2. если в конфиге включён LLM (llm.enabled) и доступен фасад Llm — запрос
 *      к модели, ответ разбирает Service_Erp228_Parser;
 *   3. иначе (или при сбое модели) работает офлайн-шаблон по типу производства —
 *      модуль остаётся рабочим без внешнего API;
 *   4. результат сохраняется как новая версия документа, карта → «готово»;
 *   5. при ошибке карта → «ошибка», текст ошибки сохраняется в error_msg.
 *
 * Никаких секретов в конфиге модуля не хранится: ключи берёт на себя модуль llm.
 *
 * @package    Erp228
 * @category   Services
 */
class Service_Erp228_LlmProcessor
{
    /** @var Service_Erp228_LlmProcessor */
    protected static $_instance;

    /**
     * @return Service_Erp228_LlmProcessor
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Сгенерировать техпроцесс по карте и сохранить его документом.
     *
     * @param  Model_Erp228_TechCard $card
     * @param  int|null              $user_id
     * @return Model_Erp228_TechDocument
     * @throws Exception
     */
    public function generate($card, $user_id = NULL)
    {
        $service = Service_Erp228_TechCards::instance();
        $config  = (array) Kohana::$config->load('erp228_tech_cards');
        $parser  = Service_Erp228_Parser::instance();

        $service->set_status($card->id, 'processing', NULL, $user_id);

        $started = microtime(TRUE);
        $source  = 'offline';
        $tokens  = 0;

        try
        {
            if ($this->llm_available($config))
            {
                $answer = $this->ask($card, $config);

                $parsed = $parser->parse($answer);
                $source = isset($config['llm']['model']) ? (string) $config['llm']['model'] : 'llm';
                $tokens = $this->approximate_tokens($answer);
            }
            else
            {
                $parsed = $parser->offline($card);
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp228: генерация техкарты #:id сорвалась — :error', array(
                ':id'    => $card->id,
                ':error' => $e->getMessage(),
            ));

            if ( ! empty($config['llm']['strict']))
            {
                // Строгий режим: без LLM работать нельзя, показываем ошибку пользователю
                $service->set_status($card->id, 'error', $e->getMessage(), $user_id);

                throw $e;
            }

            $parsed = $parser->offline($card);
        }

        $document = $service->save_document($card->id, array(
            'content_markdown' => $parsed['content_markdown'],
            'structured_data'  => $parsed['structured_data'],
            'llm_model'        => $source,
            'tokens_total'     => $tokens,
            'gen_time_ms'      => (int) round((microtime(TRUE) - $started) * 1000),
        ), $parsed['operations'], $user_id);

        $service->set_status($card->id, 'ready', NULL, $user_id);

        return $document;
    }

    /**
     * Доступен ли реальный вызов модели.
     *
     * @param  array $config
     * @return bool
     */
    public function llm_available(array $config)
    {
        if (empty($config['llm']['enabled']))
        {
            return FALSE;
        }

        return class_exists('Llm');
    }

    /**
     * Запрос к модели через фасад модуля llm.
     *
     * @param  Model_Erp228_TechCard $card
     * @param  array $config
     * @return string
     */
    protected function ask($card, array $config)
    {
        $prompt = Service_Erp228_Parser::instance()->build_prompt($card, $config['llm']);

        $options = array();

        if (isset($config['llm']['temperature']))
        {
            $options['temperature'] = (float) $config['llm']['temperature'];
        }

        if (isset($config['llm']['max_tokens']))
        {
            $options['max_tokens'] = (int) $config['llm']['max_tokens'];
        }

        $model = isset($config['llm']['model']) ? (string) $config['llm']['model'] : NULL;

        $answer = $model
            ? Llm::model($model)->ask($prompt, $options)
            : Llm::ask($prompt, $options);

        if ( ! is_string($answer) OR trim($answer) === '')
        {
            throw new Kohana_Exception('Модель вернула пустой ответ');
        }

        return $answer;
    }

    /**
     * Оценка числа токенов по длине ответа (модель токенов не возвращает).
     *
     * @param  string $answer
     * @return int
     */
    protected function approximate_tokens($answer)
    {
        return (int) ceil(mb_strlen((string) $answer, 'UTF-8') / 4);
    }
}
