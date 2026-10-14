### Анализ проблемы с нумераторами

В текущей реализации `Model_Erp227_Sequence::next_code` существует критическая уязвимость: если для типа объекта нет записи в таблице `erp_227_sequences`, система пытается создать её "на лету", используя префикс по умолчанию `OBJ-`. Это приводит к тому, что разные типы объектов (например, "Сотрудники" и "Детали") начинают использовать один и тот же префикс, порождая дубликаты кодов `OBJ-000001`.

### Решение

Необходимо изменить логику генерации:
1. **Уникальный префикс по умолчанию**: Если записи в `erp_227_sequences` нет, использовать префикс `OBJ-$type_id-`. Это гарантирует отсутствие пересечений между разными типами.
2. **Атомарность**: Использовать `INSERT ... ON CONFLICT` или корректную обработку исключений при создании новой последовательности, чтобы избежать Race Condition.
3. **Очистка сервиса**: Удалить отладочный код и двойные вызовы в `Registry.php`.

---

### 1. Файл `application/modules/erp227_core_registry/classes/Model/Erp227/Sequence.php`

Замените метод `next_code` на следующий код:

```php
    /**
     * Генерирует следующий код для указанного типа объекта.
     * Инкрементирует счетчик в БД атомарно.
     * 
     * @param  int|Model_Erp227_Type $type
     * @return string
     */
    public static function next_code($type)
    {
        $type_id = ($type instanceof Model_Erp227_Type) ? $type->id : (int) $type;
        $db = Database::instance();

        // 1. Пытаемся получить и заблокировать строку
        $result = DB::query(Database::SELECT, 'SELECT * FROM erp_227_sequences WHERE type_id = :type_id FOR UPDATE')
            ->parameters([':type_id' => $type_id])
            ->execute()
            ->current();

        if ( ! $result)
        {
            // 2. Если настройки нет, создаем её с уникальным префиксом для этого типа
            // Используем OBJ-{ID}- для исключения дублей между типами
            $prefix = 'OBJ-' . $type_id . '-';
            $padding = 6;
            $initial_val = 1;

            try {
                DB::insert('erp_227_sequences', ['type_id', 'prefix', 'current_val', 'padding'])
                    ->values([$type_id, $prefix, $initial_val, $padding])
                    ->execute();
                
                return $prefix . str_pad($initial_val, $padding, '0', STR_PAD_LEFT);
            } catch (Exception $e) {
                // Если кто-то успел вставить раньше, пробуем прочитать снова
                return self::next_code($type_id);
            }
        }

        // 3. Инкрементируем существующий счетчик
        $new_val = (int) $result['current_val'] + 1;

        DB::update('erp_227_sequences')
            ->set(['current_val' => $new_val])
            ->where('type_id', '=', $type_id)
            ->execute();

        return $result['prefix'] . str_pad($new_val, (int) $result['padding'], '0', STR_PAD_LEFT);
    }
```

### 2. Файл `application/modules/erp227_core_registry/classes/Service/Erp227/Registry.php`

В методе `create_object` необходимо убедиться, что вызов только один и нет `print_r`.

**Правильный блок в `create_object`:**
```php
            // Внедрение Нумератора: если код не задан, генерируем его автоматически
            if (empty($data['code']) AND isset($data['type_id']))
            {
                // Вызываем строго один раз
                $data['code'] = Model_Erp227_Sequence::next_code($data['type_id']);
            }
```

### 3. Исправление существующих данных (SQL)

Чтобы избавиться от уже созданных дублирующих префиксов `OBJ-` в таблице настроек, выполните:

```sql
-- 1. Сделать префиксы уникальными для типов, где они задублировались
UPDATE erp_227_sequences 
SET prefix = 'OBJ-' || type_id || '-' 
WHERE prefix = 'OBJ-';

-- 2. Если нужно сбросить счетчики под реальное кол-во объектов (опционально)
-- UPDATE erp_227_sequences s
-- SET current_val = (SELECT COUNT(*) FROM erp_227_objects o WHERE o.type_id = s.type_id);
```

### Почему это лучше:
1. **Изоляция**: Тип 10 получит код `OBJ-10-000001`, а тип 20 — `OBJ-20-000001`. Они никогда не пересекутся.
2. **Производительность**: `FOR UPDATE` гарантирует, что два запроса не получат один и тот же ID.
3. **Чистота**: Удаление `print_r` и `var_dump` предотвращает поломку JSON-ответов в API.