<?php defined('SYSPATH') or die('No direct script access.');

/**
 * ИИ-разбор сделок (erp221_crm).
 *
 * Помощник менеджера, а не источник истины: резюме переписки, оценка
 * вероятности, возражения клиента и следующий шаг. Каждый ответ сохраняется в
 * `erp_221_ai_analysis` вместе с моделью и уверенностью — видно, что машина
 * сказала вчера и почему. Если провайдер LLM недоступен, методы возвращают
 * NULL, а продажа продолжает жить: ИИ здесь необязателен.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Ai
{
    /** Что умеет модуль: код разбора => описание. */
    const INSIGHTS = array(
        'summary'     => 'Резюме сделки',
        'probability' => 'Вероятность успеха',
        'objections'  => 'Возражения клиента',
        'next_action' => 'Следующий шаг',
        'follow_up'   => 'Письмо клиенту',
        'risk'        => 'Риски сделки',
    );

    /** @var Service_Erp221_Ai|null */
    protected static $_instance;

    /**
     * @return  Service_Erp221_Ai
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Доступен ли провайдер LLM (без него модуль работает как обычно).
     *
     * @return  boolean
     */
    public function available()
    {
        return class_exists('Llm_Factory');
    }

    /**
     * История разборов по объекту.
     *
     * @param   string  $entity_type  deal|client|contact|activity
     * @param   integer $entity_id
     * @param   integer $limit
     * @return  array
     */
    public function history($entity_type, $entity_id, $limit = 10)
    {
        $rows = DB::select()->from('erp_221_ai_analysis')
            ->where('entity_type', '=', (string) $entity_type)
            ->where('entity_id', '=', (int) $entity_id)
            ->order_by('created_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit((int) $limit)
            ->execute()->as_array();

        foreach ($rows as &$row)
        {
            $row['id']         = (int) $row['id'];
            $row['entity_id']  = (int) $row['entity_id'];
            $row['confidence'] = Service_Erp221_Cast::num($row['confidence']);
            $row['payload']    = $this->decode($row['content']);
            $row['created_at'] = Service_Erp221_Cast::stamp($row['created_at']);
        }

        return $rows;
    }

    /**
     * Разобрать сделку: резюме, вероятность, возражения, следующий шаг.
     *
     * @param   integer $deal_id
     * @param   string  $insight  key из INSIGHTS
     * @param   integer $actor
     * @return  array|null
     */
    public function insight($deal_id, $insight = 'summary', $actor = NULL)
    {
        if ( ! isset(self::INSIGHTS[$insight]))
        {
            throw new Kohana_Exception('Неизвестный вид разбора «:insight»', array(':insight' => (string) $insight));
        }

        $deal = Service_Erp221_Deals::instance()->get($deal_id);

        $history = Service_Erp221_Timeline::instance()->timeline($deal['id'], array(), 50, 0);

        $prompt = $this->deal_prompt($insight, $deal, $history['rows']);

        $answer = $this->ask($insight, $prompt);

        if ($answer === NULL)
        {
            return NULL;
        }

        $analysis = $this->store('deal', $deal['id'], $insight, $answer['content'], $answer['confidence'], $answer['model']);

        // Вероятность и резюме дублируются в сделке: их видно на доске без
        // открытия карточки, и они не теряются при смене провайдера.
        if ($insight === 'probability' AND $answer['payload'] AND isset($answer['payload']['probability']))
        {
            DB::update('erp_221_deals')
                ->set(array(
                    'ai_probability' => max(0, min(100, (int) $answer['payload']['probability'])),
                    'updated_at'     => DB::expr('CURRENT_TIMESTAMP'),
                ))
                ->where('id', '=', (int) $deal['id'])
                ->execute();
        }

        if ($insight === 'summary')
        {
            DB::update('erp_221_deals')
                ->set(array('ai_summary' => $answer['content'], 'updated_at' => DB::expr('CURRENT_TIMESTAMP')))
                ->where('id', '=', (int) $deal['id'])
                ->execute();
        }

        Service_Erp221_Timeline::instance()->event('ai.insight', $actor, array(
            'deal_id' => (int) $deal['id'],
            'insight' => $insight,
            'model'   => $answer['model'],
        ));

        return $analysis;
    }

    /**
     * Уровень ЛПР у контактного лица — по должности и заметкам.
     *
     * @param   integer $contact_id
     * @param   integer $actor
     * @return  array|null
     */
    public function classify_contact_level($contact_id, $actor = NULL)
    {
        $contact = DB::select()->from('erp_301_contacts')
            ->where('id', '=', (int) $contact_id)->where('is_deleted', '=', 0)
            ->execute()->current();

        if ( ! $contact)
        {
            throw new Kohana_Exception('Контакт #:id не найден', array(':id' => (int) $contact_id));
        }

        $prompt = "Определи уровень влияния контактного лица в компании-клиенте.\n"
            ."Имя: ".$contact['name']."\n"
            ."Должность: ".($contact['position'] ? $contact['position'] : 'не указана')."\n"
            ."Заметка: ".($contact['note'] ? $contact['note'] : 'нет')."\n\n"
            .'Верни JSON: {"level": "lpr|lvpr|user|unknown", "reason": "коротко", "confidence": 0.0}';

        $answer = $this->ask('contact_level', $prompt);

        if ($answer === NULL)
        {
            return NULL;
        }

        $payload = $this->decode($answer['content']);
        $level   = $payload AND isset($payload['level']) ? strtolower((string) $payload['level']) : 'unknown';

        if ( ! in_array($level, array('lpr', 'lvpr', 'user', 'unknown'), TRUE))
        {
            $level = 'unknown';
        }

        Service_Erp221_Clients::instance()->save_contact_profile($contact_id, array(
            'decision_maker_level' => $level,
        ), $actor);

        return $this->store('contact', (int) $contact_id, 'contact_level', $answer['content'], $answer['confidence'], $answer['model']);
    }

    /**
     * Картина отказов: статистика по причинам плюс, если есть LLM, короткий разбор.
     *
     * @param   string  $date_from  YYYY-MM-DD
     * @return  array
     */
    public function analyze_loss_patterns($date_from = NULL)
    {
        $stats = Service_Erp221_Analytics::instance()->loss_reasons(array('from' => $date_from));

        $result = array('stats' => $stats, 'comment' => NULL, 'model' => NULL);

        if ( ! $stats OR ! $this->available())
        {
            return $result;
        }

        $lines = array();

        foreach ($stats as $row)
        {
            $lines[] = $row['label'].': сделок '.$row['count'].', на сумму '.number_format($row['amount'], 2, ',', ' ').' ₽';
        }

        $prompt = "Это статистика отказов по сделкам за период.\n".implode("\n", $lines)
            ."\n\nНайди закономерности и предложи, что исправить в работе с клиентами. Ответь коротко, деловым языком.";

        $answer = $this->ask('loss_patterns', $prompt);

        if ($answer)
        {
            $result['comment'] = $answer['content'];
            $result['model']   = $answer['model'];
        }

        return $result;
    }

    /**
     * Подсказка по сделке без записи в базу (для интерфейса и API).
     *
     * @param   integer $deal_id
     * @param   string  $insight
     * @return  string|null
     */
    public function preview($deal_id, $insight = 'next_action')
    {
        $deal = Service_Erp221_Deals::instance()->get($deal_id);

        $history = Service_Erp221_Timeline::instance()->timeline($deal['id'], array(), 30, 0);

        $answer = $this->ask($insight, $this->deal_prompt($insight, $deal, $history['rows']));

        return $answer ? $answer['content'] : NULL;
    }

    /**
     * Виды разборов для интерфейса.
     *
     * @return  array
     */
    public function kinds()
    {
        return self::INSIGHTS;
    }

    /**
     * Собрать запрос к модели по сделке.
     *
     * @param   string $insight
     * @param   array  $deal
     * @param   array  $timeline
     * @return  string
     */
    protected function deal_prompt($insight, array $deal, array $timeline)
    {
        $lines = array();

        foreach ($timeline as $entry)
        {
            $lines[] = '- ['.$entry['created_at'].'] '.($entry['subject'] ? $entry['subject'].': ' : '').$entry['content'];
        }

        $context = "Сделка №".$deal['number']." «".$deal['title']."»\n"
            ."Клиент: ".$deal['client']['name'].($deal['client']['inn'] ? ' (ИНН '.$deal['client']['inn'].')' : '')."\n"
            ."Стадия: ".$deal['stage']['label'].", сумма: ".number_format($deal['amount'], 2, ',', ' ').' '.$deal['currency']."\n"
            ."Срок: ".($deal['deadline'] ? $deal['deadline'] : 'не указан')."\n"
            ."Ответственный: id ".($deal['assigned_to'] ? $deal['assigned_to'] : '—')."\n"
            ."Описание: ".($deal['description'] ? $deal['description'] : 'нет')."\n\n"
            ."Лента (".count($lines)." записей):\n".($lines ? implode("\n", $lines) : 'записей нет');

        $asks = array(
            'summary'     => "Сделай краткое резюме сделки: что уже произошло, на чём стоим, что важно не потерять.\nВерни текст до 1200 символов.",
            'probability' => "Оцени вероятность успеха сделки.\nВерни JSON: {\"probability\": 0-100, \"reason\": \"почему такая оценка, кратко\"}",
            'objections'  => "Найди в ленте возражения клиента и сгруппируй их.\nВерни JSON: {\"objections\": [{\"text\": \"возражение\", \"answered\": true|false, \"advice\": \"как ответить\"}]}",
            'next_action' => "Предложи следующий конкретный шаг по сделке.\nВерни JSON: {\"action\": \"что сделать\", \"why\": \"зачем\", \"deadline_days\": 1-14}",
            'follow_up'   => "Напиши короткое деловое письмо клиенту по этой сделке на русском языке.\nВерни текст письма без темы.",
            'risk'        => "Назови риски сделки и что их снизит.\nВерни JSON: {\"risks\": [{\"risk\": \"...\", \"mitigation\": \"...\"}]}",
        );

        return $context."\n\nЗадача: ".$asks[$insight];
    }

    /**
     * Запрос к модели: ответ, уверенность и имя модели (NULL, если не вышло).
     *
     * @param   string  $insight
     * @param   string  $prompt
     * @return  array|null  ['content' => string, 'payload' => array|null, 'confidence' => float|null, 'model' => string]
     */
    protected function ask($insight, $prompt)
    {
        if ( ! $this->available())
        {
            return NULL;
        }

        $config = (array) Kohana::$config->load('erp221_crm.ai');

        try
        {
            $llm = Llm_Factory::get()->provider((string) Arr::get($config, 'provider', 'local'));

            if (Arr::get($config, 'model'))
            {
                $llm->model((string) $config['model']);
            }

            $response = $llm->chat(array(
                array('role' => 'system', 'content' => (string) Arr::get($config, 'prompt', 'Ты — опытный руководитель отдела продаж. Отвечай по делу, на русском языке.')),
                array('role' => 'user', 'content' => $prompt),
            ), array('temperature' => 0.2, 'max_tokens' => (int) Arr::get($config, 'max_tokens', 900)));

            $content = Arr::path((array) $response, 'choices.0.message.content');

            if ( ! $content)
            {
                return NULL;
            }

            $payload = $this->decode($content);

            return array(
                'content'    => trim((string) $content),
                'payload'    => $payload,
                'confidence' => $payload AND isset($payload['confidence']) ? (float) $payload['confidence'] : NULL,
                'model'      => (string) Arr::get($config, 'model', ''),
            );
        }
        catch (Exception $e)
        {
            // ИИ — помощник: его недоступность не должна ломать карточку сделки.
            Kohana::$log->add(Log::WARNING, 'erp221: ИИ-разбор «:insight» недоступен — :error', array(
                ':insight' => $insight,
                ':error'   => $e->getMessage(),
            ));

            return NULL;
        }
    }

    /**
     * Сохранить разбор в базу.
     *
     * @param   string  $entity_type
     * @param   integer $entity_id
     * @param   string  $insight_type
     * @param   string  $content
     * @param   float   $confidence
     * @param   string  $model
     * @return  array
     */
    protected function store($entity_type, $entity_id, $insight_type, $content, $confidence = NULL, $model = NULL)
    {
        $id = Service_Erp221_Cast::insert_id(DB::insert('erp_221_ai_analysis', array(
                'entity_type', 'entity_id', 'insight_type', 'content', 'confidence', 'model', 'created_at',
            ))
            ->values(array(
                (string) $entity_type,
                (int) $entity_id,
                (string) $insight_type,
                (string) $content,
                $confidence,
                $model ? mb_substr((string) $model, 0, 120) : NULL,
                DB::expr('CURRENT_TIMESTAMP'),
            ))
            ->execute());

        return array(
            'id'           => (int) $id,
            'entity_type'  => $entity_type,
            'entity_id'    => (int) $entity_id,
            'insight_type' => $insight_type,
            'content'      => $content,
            'payload'      => $this->decode($content),
            'confidence'   => $confidence,
            'model'        => $model,
            'created_at'   => date('Y-m-d H:i'),
        );
    }

    /**
     * Разобрать ответ модели как JSON, если он в формате JSON.
     *
     * @param   string  $content
     * @return  array|null
     */
    protected function decode($content)
    {
        $content = trim((string) $content);
        $content = preg_replace('/^```(?:json)?\s*|```$/i', '', $content);

        $decoded = json_decode($content, TRUE);

        return is_array($decoded) ? $decoded : NULL;
    }
}
