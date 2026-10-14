<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Состав изделия для производственного заказа (erp402_production).
 *
 * Технологическая карта (erp228) хранит разбор ответа LLM — дерево
 * `structured_data` свободной формы. Для производства нужны не строки
 * markdown, а потребность: материал + норма расхода на единицу изделия.
 *
 * Сервис решает эту задачу в два шага:
 *  1. `extract()` обходит дерево и вытаскивает строки состава по смыслу
 *     (имя материала + количество), поддерживая русские и английские ключи;
 *  2. `match()` сопоставляет имя материала с номенклатурой реестра `erp227`
 *     через сервис склада и помечает результат: точное совпадение, похожее
 *     (`approximate`) или не найдено — планировщик видит, где нужно решение
 *     человека, а не получает молча подставленную «похожую» позицию.
 *
 * Строки с одинаковым материалом складываются (материал может повторяться
 * в дереве несколькими узлами), порядок первого появления сохраняется.
 *
 * @package    Erp402
 * @category   Services
 */
class Service_Erp402_Bom
{
    /** @var Service_Erp402_Bom */
    protected static $_instance;

    /** @var array Ключи, в которых встречается наименование материала */
    protected $_name_keys = array('name', 'title', 'material', 'component', 'part', 'position', 'наименование', 'материал', 'позиция', 'деталь');

    /** @var array Ключи, в которых встречается количество */
    protected $_qty_keys = array('qty', 'quantity', 'count', 'amount', 'norm', 'количество', 'норма', 'кол-во');

    /** @var array Ключи единицы измерения */
    protected $_uom_keys = array('uom', 'unit', 'units', 'uom_code', 'ед', 'ед_изм', 'единица');

    /** @var int Ограничение обхода дерева: защита от «дерева на миллион узлов» */
    const MAX_NODES = 2000;

    /**
     * @return  Service_Erp402_Bom
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
     * Состав из технологической карты.
     *
     * @param   int|string  $card_id    id или uuid техкарты
     * @return  array       card, items, operations, warnings
     * @throws  Exception_Erp402_Production
     */
    public function from_tech_card($card_id)
    {
        if ( ! class_exists('Service_Erp228_TechCards'))
        {
            throw new Exception_Erp402_Production('Модуль технологических карт (erp228) недоступен', NULL, 'dependency');
        }

        $cards = Service_Erp228_TechCards::instance();
        $card  = $cards->get($card_id);

        if ( ! $card)
        {
            throw new Exception_Erp402_Production('Технологическая карта не найдена', NULL, 'card_not_found');
        }

        $document = $cards->actual_document($card->id);

        $structure = ($document AND $document->decode_structured()) ? $document->decode_structured() : array();

        list($items, $warnings) = $this->extract($structure);

        $items = $this->match($items);

        if ( ! $items)
        {
            $warnings[] = 'В актуальном документе техкарты не найдено строк состава — заполните состав вручную.';
        }

        return array(
            'card'       => array(
                'id'    => (int) $card->id,
                'uuid'  => (string) $card->uuid,
                'title' => (string) $card->title,
            ),
            'items'      => $items,
            'operations' => $this->operations($card->id),
            'warnings'   => $warnings,
        );
    }

    /**
     * Операции актуального техпроцесса: маршрут заказа.
     *
     * Маршрут живёт в модуле техкарт, поэтому берём его через сервис erp228 —
     * своих запросов к чужим таблицам модуль не делает.
     *
     * @param   int     $card_id
     * @return  array
     */
    public function operations($card_id)
    {
        if ( ! class_exists('Service_Erp228_TechCards'))
        {
            return array();
        }

        try
        {
            return Service_Erp228_TechCards::instance()->operations($card_id);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp402: маршрут техкарты не получен — :error',
                array(':error' => $e->getMessage()));

            return array();
        }
    }

    /**
     * Вытащить строки состава из дерева `structured_data`.
     *
     * @param   mixed   $structure
     * @return  array   array(строки состава, предупреждения)
     */
    public function extract($structure)
    {
        $found    = array();
        $nodes    = 0;
        $warnings = array();

        $walk = function ($node, $depth) use (&$walk, &$found, &$nodes, &$warnings) {
            if ($depth > 8 OR $nodes > self::MAX_NODES)
            {
                if ($nodes > self::MAX_NODES)
                {
                    $warnings[] = 'Дерево состава слишком большое — разобрана только его часть.';
                }

                return;
            }

            if ( ! is_array($node))
            {
                return;
            }

            $nodes++;

            // Узел — строка состава?
            $line = $this->_as_line($node);

            if ($line)
            {
                $key = mb_strtolower($line['name']);

                if (isset($found[$key]))
                {
                    // Один материал в разных узлах дерева — складываем нормы
                    $found[$key]['qty_per_unit'] = round($found[$key]['qty_per_unit'] + $line['qty_per_unit'], 6);
                    $found[$key]['sources']++;
                }
                else
                {
                    $line['sources'] = 1;
                    $found[$key]     = $line;
                }

                return;
            }

            foreach ($node as $child)
            {
                if (is_array($child))
                {
                    $walk($child, $depth + 1);
                }
            }
        };

        $walk($structure, 0);

        $items = array_values($found);

        foreach ($items as $index => $item)
        {
            $items[$index]['line_no'] = ($index + 1) * 10;
        }

        return array($items, $warnings);
    }

    /**
     * Сопоставить строки состава с номенклатурой реестра (erp227).
     *
     * @param   array   $items
     * @return  array
     */
    public function match(array $items)
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            return $items;
        }

        $stock = Service_Erp401_Stock::instance();

        foreach ($items as $index => $item)
        {
            $items[$index]['item_id']    = NULL;
            $items[$index]['match']      = 'none';
            $items[$index]['candidate']  = NULL;

            try
            {
                $candidates = $stock->search_items($item['name'], 5);
            }
            catch (Exception $e)
            {
                $candidates = array();
            }

            if ( ! $candidates)
            {
                continue;
            }

            $items[$index]['candidate'] = $this->candidate_name($candidates[0]);

            foreach ($candidates as $candidate)
            {
                if (mb_strtolower($this->candidate_name($candidate)) === mb_strtolower($item['name']))
                {
                    $items[$index]['item_id'] = (int) $this->candidate_id($candidate);
                    $items[$index]['match']   = 'exact';
                    break;
                }
            }

            // Похожее совпадение не подставляем молча: планировщик решает сам
            if ($items[$index]['item_id'] === NULL)
            {
                $items[$index]['match'] = 'approximate';
            }
        }

        return $items;
    }

    /**
     * Имя позиции в результате поиска номенклатуры.
     *
     * @param   mixed   $candidate
     * @return  string
     */
    public function candidate_name($candidate)
    {
        if (is_array($candidate))
        {
            return (string) (isset($candidate['name']) ? $candidate['name'] : '');
        }

        if (is_object($candidate))
        {
            return (string) (isset($candidate->name) ? $candidate->name : '');
        }

        return (string) $candidate;
    }

    /**
     * Идентификатор позиции в результате поиска номенклатуры.
     *
     * @param   mixed   $candidate
     * @return  int|null
     */
    public function candidate_id($candidate)
    {
        if (is_array($candidate))
        {
            return isset($candidate['id']) ? (int) $candidate['id'] : NULL;
        }

        if (is_object($candidate))
        {
            return isset($candidate->id) ? (int) $candidate->id : NULL;
        }

        return NULL;
    }

    /**
     * Разобрать узел дерева как строку состава.
     *
     * @param   array   $node
     * @return  array|null
     */
    protected function _as_line(array $node)
    {
        $name = NULL;

        foreach ($this->_name_keys as $key)
        {
            if (isset($node[$key]) AND is_string($node[$key]) AND trim($node[$key]) !== '')
            {
                $name = trim($node[$key]);
                break;
            }
        }

        if ($name === NULL OR mb_strlen($name) > 255)
        {
            return NULL;
        }

        $qty = NULL;

        foreach ($this->_qty_keys as $key)
        {
            if (isset($node[$key]) AND is_numeric(str_replace(',', '.', (string) $node[$key])))
            {
                $qty = (float) str_replace(',', '.', (string) $node[$key]);
                break;
            }
        }

        // Без количества узел — не строка состава: это может быть раздел дерева
        if ($qty === NULL OR $qty <= 0)
        {
            return NULL;
        }

        $uom = 'pcs';

        foreach ($this->_uom_keys as $key)
        {
            if (isset($node[$key]) AND is_string($node[$key]) AND trim($node[$key]) !== '')
            {
                $uom = mb_substr(trim($node[$key]), 0, 16);
                break;
            }
        }

        return array(
            'name'         => $name,
            'qty_per_unit' => round($qty, 6),
            'uom_code'     => $uom,
            'note'         => NULL,
        );
    }
}
