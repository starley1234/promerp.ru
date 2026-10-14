<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Компактный поиск MCP-инструментов для моделей с небольшим context window.
 * Полный каталог никогда не возвращается: только несколько доступных ключу
 * кандидатов, их аргументы и готовый шаблон следующего tools/call.
 */
class Service_Erp804_Discovery
{
    /** @var Service_Erp804_Discovery|null */
    protected static $_instance;

    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * @param string $query Задача обычными словами либо точное имя инструмента
     * @param array  $key   Авторизованный MCP-ключ
     * @param int    $limit 1..5
     * @param string $mode  any/read/write
     * @return array
     */
    public function search($query, array $key, $limit = 3, $mode = 'read')
    {
        $query = trim((string) $query);
        $limit = max(1, min(5, (int) $limit));
        $mode = in_array($mode, array('any', 'read', 'write'), TRUE) ? $mode : 'any';
        $registry = Service_Erp804_Registry::instance();
        $ranked = array();
        $requested_names = $this->explicit_names($query);
        $legacy_aliases = $this->legacy_aliases();
        $available_names = array_keys($registry->for_key($key));

        foreach ($registry->for_key($key) as $name => $tool)
        {
            // Полный каталог на 100+ строк противоречит назначению компактного
            // endpoint. Вместо него поиск предложит system.tools.summary.
            if (in_array($name, array('system.tool.search', 'system.tools.list'), TRUE))
            {
                continue;
            }
            if ($mode === 'read' AND ! empty($tool['mutating']))
            {
                continue;
            }
            if ($mode === 'write' AND empty($tool['mutating']))
            {
                continue;
            }

            $rank = $this->rank($query, $name, $tool);
            $interpreted_from = NULL;

            if (in_array($name, $requested_names, TRUE))
            {
                $rank['score'] = max(1200, $rank['score']);
                $rank['matches'][] = 'точное имя в запросе';
            }

            foreach ($requested_names as $requested_name)
            {
                if (isset($legacy_aliases[$requested_name]) AND $legacy_aliases[$requested_name] === $name)
                {
                    $rank['score'] = max(900, $rank['score']);
                    $rank['matches'][] = 'замена придуманного имени';
                    $interpreted_from = $requested_name;
                }
            }

            if ($rank['score'] > 0)
            {
                $ranked[] = array(
                    'name' => $name, 'tool' => $tool, 'score' => $rank['score'],
                    'matches' => array_values(array_unique($rank['matches'])),
                    'interpreted_from' => $interpreted_from,
                );
            }
        }

        usort($ranked, function ($a, $b) {
            if ($a['score'] === $b['score']) return strcmp($a['name'], $b['name']);
            return ($a['score'] > $b['score']) ? -1 : 1;
        });
        // Для точного имени единственный ответ полезнее и в несколько раз
        // короче трёх «похожих» подсказок.
        $ranked = array_slice($ranked, 0, (isset($ranked[0]) AND $ranked[0]['score'] >= 900) ? 1 : $limit);
        $results = array();

        foreach ($ranked as $row)
        {
            $results[] = $this->candidate(
                $row['name'], $row['tool'], $row['score'], $row['matches'], $row['interpreted_from']
            );
        }

        $unavailable_names = array_values(array_diff($requested_names, $available_names));

        if ( ! $results)
        {
            return array(
                'summary' => 'Подходящий доступный инструмент не найден. Переформулируйте задачу: укажите объект и действие.',
                'found' => 0,
                'query' => $query,
                'examples' => array(
                    'показать остатки материала на складе',
                    'найти производственный заказ по номеру',
                    'проверить дефекты и результаты ОТК',
                    'показать историю изменений документа',
                ),
                'requested_tool_names' => $requested_names,
                'unavailable_tool_names' => $unavailable_names,
                'next_step' => 'Повторите system.tool.search с конкретным объектом и действием. Не вызывайте и не повторяйте придуманное имя инструмента.',
            );
        }

        $has_unavailable = ! empty($unavailable_names);
        $summary = $has_unavailable
            ? 'Инструмент '.implode(', ', $unavailable_names).' не объявлен или недоступен. Не повторяйте его поиск; используйте предложенную замену '.$results[0]['name'].'.'
            : 'Найдено подходящих инструментов: '.count($results).'. Сначала используйте первый, если его назначение совпадает с задачей.';

        return array(
            'summary' => $summary,
            'found' => count($results),
            'query' => $query,
            'requested_tool_names' => $requested_names,
            'unavailable_tool_names' => $unavailable_names,
            'requested_tool_available' => empty($unavailable_names),
            'endpoint' => isset($key['endpoint_path']) ? $key['endpoint_path'] : '/mcp/discovery',
            'results' => $results,
            'next_step' => 'Вызовите system.tool.call ОДИН РАЗ с объектом из results[0].tool_arguments прямо на верхнем уровне аргументов. Не оборачивайте его ещё в один arguments и не повторяйте одинаковый ошибочный вызов.',
        );
    }

    protected function rank($query, $name, array $tool)
    {
        $q = $this->lower($query);
        $title = $this->lower(isset($tool['title']) ? $tool['title'] : '');
        $description = $this->lower(isset($tool['description']) ? $tool['description'] : '');
        $schema = $this->lower(json_encode(isset($tool['schema']) ? $tool['schema'] : array(), JSON_UNESCAPED_UNICODE));
        $aliases = $this->aliases($name);
        $score = 0;
        $matches = array();

        if ($q === $this->lower($name))
        {
            return array('score' => 1000, 'matches' => array('точное имя'));
        }
        if ($q !== '' AND strpos($this->lower($name), $q) !== FALSE) { $score += 120; $matches[] = 'имя'; }
        if ($q !== '' AND strpos($title, $q) !== FALSE) { $score += 80; $matches[] = 'название'; }
        if ($q !== '' AND strpos($description, $q) !== FALSE) { $score += 45; $matches[] = 'описание'; }

        foreach ($this->tokens($q) as $token)
        {
            $hit = FALSE;
            $haystacks = array(
                array($this->lower($name), 24), array($title, 18),
                array($description, 12), array($aliases, 16), array($schema, 5),
            );
            foreach ($haystacks as $haystack)
            {
                foreach ($this->token_variants($token) as $variant)
                {
                    if (strpos($haystack[0], $variant) !== FALSE)
                    {
                        $score += $haystack[1];
                        $hit = TRUE;
                        break;
                    }
                }
            }
            if ($hit) $matches[] = $token;
        }

        return array('score' => $score, 'matches' => array_values(array_unique($matches)));
    }

    protected function candidate($name, array $tool, $score, array $matches, $interpreted_from = NULL)
    {
        $schema = isset($tool['schema']) ? (array) $tool['schema'] : array();
        $properties = isset($schema['properties']) ? (array) $schema['properties'] : array();
        $required = isset($schema['required']) ? (array) $schema['required'] : array();
        $arguments = array();
        $example = array();

        foreach ($properties as $field => $rule)
        {
            $argument = array(
                'name' => $field,
                'type' => isset($rule['type']) ? $rule['type'] : 'string',
                'required' => in_array($field, $required, TRUE),
                'description' => isset($rule['description']) ? $rule['description'] : '',
            );
            foreach (array('default', 'enum', 'minimum', 'maximum') as $attribute)
            {
                if (array_key_exists($attribute, $rule)) $argument[$attribute] = $rule[$attribute];
            }
            $arguments[] = $argument;

            if (array_key_exists('default', $rule)) $example[$field] = $rule['default'];
            elseif (in_array($field, $required, TRUE)) $example[$field] = '<'.$argument['description'].'>';
        }

        return array(
            'name' => $name,
            'title' => isset($tool['title']) ? $tool['title'] : $name,
            'description' => isset($tool['description']) ? $tool['description'] : '',
            'confidence' => $score >= 100 ? 'high' : ($score >= 35 ? 'medium' : 'low'),
            'matched_by' => $matches,
            'read_only' => empty($tool['mutating']),
            'requires_human_approval' => ! empty($tool['approval']),
            'interpreted_from' => $interpreted_from,
            'arguments' => $arguments,
            // Это именно объект аргументов MCP-инструмента system.tool.call.
            // Слабому клиенту не нужно извлекать его из JSON-RPC envelope.
            'tool_arguments' => array('tool_name' => $name, 'arguments' => $example),
            'call' => array(
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => array(
                    'name' => 'system.tool.call',
                    'arguments' => array('tool_name' => $name, 'arguments' => $example),
                ),
            ),
        );
    }

    /** Явно упомянутые имена вида namespace.action в свободном запросе. */
    protected function explicit_names($query)
    {
        preg_match_all('/\b[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+\b/i', (string) $query, $matches);
        return array_values(array_unique(array_map(array($this, 'lower'), isset($matches[0]) ? $matches[0] : array())));
    }

    /**
     * Частые имена, которые слабые модели придумывают по REST-подобному шаблону.
     * Они не становятся отдельными MCP tools: поиск явно переводит агента на
     * существующий канонический инструмент и не даёт зациклиться.
     */
    protected function legacy_aliases()
    {
        return array(
            'planning.need.list' => 'planning.program.get',
            'planning.needs.list' => 'planning.program.get',
            'planning.deficit.list' => 'planning.deficits',
            'planning.offer.list' => 'planning.proposals.search',
            'production.bom.list' => 'production.tech_card.composition',
            'production.order.list' => 'production.orders.search',
            'production.operation.list' => 'shopfloor.operations.search',
            'quality.check.list' => 'quality.inspections',
            'quality.defect.list' => 'quality.defects_report',
            'analytics.snapshot.list' => 'analytics.snapshots',
            'analytics.kpi.get' => 'analytics.summary',
        );
    }

    /** Простая нормализация частых окончаний: без внешнего морфологического сервиса. */
    protected function token_variants($token)
    {
        $variants = array($token);
        $stem = preg_replace('/(ами|ями|ого|ему|ах|ях|ов|ев|ами|ы|и|а|я|е|у|s)$/u', '', $token);
        if ($stem !== '' AND strlen($stem) >= 4) $variants[] = $stem;
        return array_values(array_unique($variants));
    }

    protected function aliases($name)
    {
        $groups = array(
            'production' => 'производство заказ заказ-наряд выпуск изделие product manufacturing order',
            'warehouse' => 'склад остаток запас наличие движение материал wms stock inventory movement',
            'procurement' => 'закупка поставщик заявка заказ поставщику приемка supply supplier purchase receipt',
            'quality' => 'качество отк отклонение дефект брак контроль inspection defect qc quality',
            'planning' => 'планирование программа потребность предложение plan planning requirement demand',
            'shopfloor' => 'цех смена операция выработка простой рабочий центр shop floor shift operation downtime',
            'document' => 'документ печать форма паспорт document print template',
            'analytics' => 'аналитика показатель метрика kpi сводка dashboard analytics',
            'audit' => 'аудит журнал история изменение кто сделал audit history change log',
            'registry' => 'реестр номенклатура объект позиция item registry catalogue',
            'tech' => 'техкарта маршрут ebom mbom спецификация bom routing technology card',
            'system' => 'система здоровье готовность права ключ status health permissions',
            'demo' => 'демо стенд сценарий demo stand scenario',
        );
        $actions = array(
            'search' => 'найти поиск подобрать искать find search', 'list' => 'список показать все list show',
            'get' => 'получить открыть подробнее карточка get details', 'status' => 'статус состояние status state',
            'create' => 'создать добавить create add', 'update' => 'изменить обновить update edit',
            'complete' => 'завершить выполнить закрыть complete finish', 'cancel' => 'отменить cancel',
            'start' => 'начать запустить start', 'approve' => 'согласовать подтвердить approve',
            'summary' => 'кратко сводка список инструментов catalogue tools list overview',
        );
        $text = str_replace(array('.', '_', '-'), ' ', $this->lower($name));
        foreach ($groups + $actions as $part => $words)
        {
            if (strpos($text, $part) !== FALSE) $text .= ' '.$words;
        }
        return $text;
    }

    protected function tokens($text)
    {
        $parts = preg_split('/[^\p{L}\p{N}_]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stop = array('и', 'в', 'на', 'по', 'для', 'с', 'из', 'the', 'a', 'to', 'of', 'for', 'show', 'please', 'нужно', 'хочу');
        return array_values(array_filter(array_unique((array) $parts), function ($token) use ($stop) {
            return strlen($token) >= 2 AND ! in_array($token, $stop, TRUE);
        }));
    }

    protected function lower($value)
    {
        $value = (string) $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
