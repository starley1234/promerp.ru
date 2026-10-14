<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Единый сервис кадрового справочника и организационной структуры.
 * Пользователь — учётная запись, сотрудник — человек, назначение — его место
 * в структуре на период. Эти сущности намеренно не смешиваются.
 */
class Service_Erp310_Workforce
{
    protected static $_instance;

    public static function instance()
    {
        if (self::$_instance === NULL) self::$_instance = new self;
        return self::$_instance;
    }

    protected function __construct() {}

    public function stats()
    {
        $scalar = function ($sql) {
            $row = DB::query(Database::SELECT, $sql)->execute()->current();
            return (int) Arr::get($row, 'total', 0);
        };

        $staffing = $this->staffing_rows(TRUE);
        $planned = $occupied = 0.0;
        foreach ($staffing as $row) { $planned += (float) $row['planned_fte']; $occupied += (float) $row['occupied_fte']; }
        return array(
            'employees' => $scalar("SELECT COUNT(*) total FROM erp_310_employees WHERE status = 'active'"),
            'unlinked' => $scalar("SELECT COUNT(*) total FROM erp_310_employees WHERE status = 'active' AND user_id IS NULL"),
            'departments' => $scalar("SELECT COUNT(*) total FROM erp_310_departments WHERE status = 'active'"),
            'positions' => $scalar("SELECT COUNT(*) total FROM erp_310_positions WHERE status = 'active'"),
            'planned_fte' => $planned, 'vacant_fte' => max(0, $planned - $occupied),
        );
    }

    public function departments($active_only = TRUE)
    {
        $query = ORM::factory('Erp310_Department');
        if ($active_only) $query->where('status', '=', 'active');
        return $query->order_by('parent_id', 'ASC')->order_by('sort_order', 'ASC')->order_by('name', 'ASC')->find_all()->as_array();
    }

    public function positions($department_id = NULL, $active_only = TRUE)
    {
        $query = ORM::factory('Erp310_Position');
        if ($active_only) $query->where('status', '=', 'active');
        if ($department_id) $query->where('department_id', '=', (int) $department_id);
        return $query->order_by('name', 'ASC')->find_all()->as_array();
    }

    public function staffing_rows($active_only = FALSE)
    {
        $where = $active_only ? "WHERE s.status='active' AND s.valid_from<=CURRENT_DATE AND (s.valid_to IS NULL OR s.valid_to>=CURRENT_DATE)" : '';
        return DB::query(Database::SELECT, "SELECT s.id, s.uuid, s.department_id, s.position_id,
                s.planned_fte, s.valid_from, s.valid_to, s.status, s.note,
                d.name department_name, p.name position_name, p.code position_code,
                coalesce(sum(a.rate) FILTER (WHERE a.valid_to IS NULL AND e.status='active'), 0) occupied_fte,
                s.planned_fte - coalesce(sum(a.rate) FILTER (WHERE a.valid_to IS NULL AND e.status='active'), 0) vacant_fte
            FROM erp_310_staff_units s
            JOIN erp_310_departments d ON d.id=s.department_id
            JOIN erp_310_positions p ON p.id=s.position_id
            LEFT JOIN erp_310_assignments a ON a.department_id=s.department_id AND a.position_id=s.position_id
            LEFT JOIN erp_310_employees e ON e.id=a.employee_id
            $where
            GROUP BY s.id, d.name, p.name, p.code
            ORDER BY d.name, p.name, s.valid_from DESC")->execute()->as_array();
    }

    public function save_staff_unit(array $data, $actor_id = NULL)
    {
        $id = (int) Arr::get($data, 'id');
        $row = $id ? ORM::factory('Erp310_StaffUnit', $id) : ORM::factory('Erp310_StaffUnit');
        if ($id AND ! $row->loaded()) throw new Kohana_Exception('Строка штатного расписания не найдена');
        $department_id = (int) Arr::get($data, 'department_id');
        $position_id = (int) Arr::get($data, 'position_id');
        $department = ORM::factory('Erp310_Department', $department_id);
        $position = ORM::factory('Erp310_Position', $position_id);
        if ( ! $department->loaded() OR $department->status !== 'active') throw new Kohana_Exception('Выберите действующее подразделение');
        if ( ! $position->loaded() OR $position->status !== 'active') throw new Kohana_Exception('Выберите действующую должность');
        if ($position->department_id AND (int) $position->department_id !== $department_id)
            throw new Kohana_Exception('Должность не относится к выбранному подразделению');
        $planned_fte = (float) Arr::get($data, 'planned_fte');
        if ($planned_fte <= 0 OR $planned_fte > 999) throw new Kohana_Exception('Количество ставок должно быть от 0,01 до 999');
        $values = array(
            'department_id'=>$department_id, 'position_id'=>$position_id, 'planned_fte'=>$planned_fte,
            'valid_from'=>Arr::get($data, 'valid_from') ?: $this->database_today(),
            'valid_to'=>Arr::get($data, 'valid_to') ?: NULL, 'status'=>Arr::get($data, 'status') ?: 'active',
            'note'=>trim((string) Arr::get($data, 'note')) ?: NULL,
        );
        if ( ! $id) $values['created_by'] = $actor_id ? (int) $actor_id : NULL;
        $row->values($values);
        $id ? $row->update() : $row->create();
        $this->publish($id ? 'erp310.staff_unit.updated' : 'erp310.staff_unit.created', array(
            'id'=>(int)$row->id, 'department_id'=>$department_id, 'position_id'=>$position_id,
            'planned_fte'=>$planned_fte, 'actor_id'=>$actor_id,
        ));
        return $row;
    }

    public function archive_staff_unit($id, $actor_id = NULL)
    {
        $row = ORM::factory('Erp310_StaffUnit', (int) $id);
        if ( ! $row->loaded()) throw new Kohana_Exception('Строка штатного расписания не найдена');
        if ($row->status === 'archived') return $row;
        $row->status = 'archived';
        $row->valid_to = max($row->valid_from, $this->database_today());
        $row->update();
        $this->publish('erp310.staff_unit.archived', array('id'=>(int)$row->id, 'actor_id'=>$actor_id));
        return $row;
    }

    public function position_rows($active_only = FALSE)
    {
        $sql = "SELECT p.id, p.uuid, p.code, p.name, p.category, p.status,
                       p.department_id, d.name department_name,
                       COUNT(a.id) FILTER (WHERE a.valid_to IS NULL) current_employees
                FROM erp_310_positions p
                LEFT JOIN erp_310_departments d ON d.id=p.department_id
                LEFT JOIN erp_310_assignments a ON a.position_id=p.id
                ".($active_only ? "WHERE p.status='active'" : '')."
                GROUP BY p.id, d.name ORDER BY d.name NULLS LAST, p.name";
        return DB::query(Database::SELECT, $sql)->execute()->as_array();
    }

    /** Standard contract for SmartReference controls; ACL is enforced by the endpoint. */
    public function references($type, $term, $limit = 15, $current_id = NULL, $exclude_id = NULL, $allowed_department_ids = NULL)
    {
        $type = (string) $type;
        $term = trim((string) $term);
        $limit = max(1, min(30, (int) $limit));

        if ($type === 'employee')
        {
            $filters=array('term' => $term, 'status' => 'active');
            if ($allowed_department_ids !== NULL) $filters['department_ids']=$allowed_department_ids;
            $result = $this->search($filters, $limit, 0);
            $items = array();
            foreach ($result['rows'] as $row)
            {
                if ($exclude_id AND (int) $row['id'] === (int) $exclude_id) continue;
                $items[] = array(
                    'id' => (int) $row['id'],
                    'label' => trim($row['last_name'].' '.$row['first_name'].' '.$row['middle_name']),
                    'context' => $row['personnel_no'].' · '.($row['position_name'] ?: 'без должности').' · '.($row['department_name'] ?: 'без подразделения'),
                );
            }
            return $items;
        }
        if ($type === 'user')
        {
            $params = array(':term' => '%'.mb_strtolower($term, 'UTF-8').'%', ':limit' => $limit, ':current' => (int) $current_id);
            $rows = DB::query(Database::SELECT, "SELECT u.id, u.username, u.email
                FROM users u LEFT JOIN erp_310_employees e ON e.user_id=u.id
                WHERE (e.id IS NULL OR u.id=:current)
                  AND (lower(u.username) LIKE :term OR lower(coalesce(u.email,'')) LIKE :term)
                ORDER BY u.username LIMIT :limit")->parameters($params)->execute()->as_array();
            $items = array();
            foreach ($rows as $row) $items[] = array(
                'id' => (int) $row['id'], 'label' => $row['username'],
                'context' => $row['email'] ?: 'e-mail не указан',
            );
            return $items;
        }
        throw new Kohana_Exception('Неизвестный тип ссылки');
    }

    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $where = array('1=1');
        $params = array();
        $term = mb_strtolower(trim((string) Arr::get($filters, 'term')), 'UTF-8');

        if ($term !== '')
        {
            // Кластер PostgreSQL может работать в locale C, где lower() не
            // складывает регистр кириллицы. Нормализованная строка пишется PHP.
            $where[] = "e.search_text LIKE :term";
            $params[':term'] = '%'.$term.'%';
        }
        if (Arr::get($filters, 'status'))
        {
            $where[] = 'e.status = :status';
            $params[':status'] = Arr::get($filters, 'status');
        }
        if ((int) Arr::get($filters, 'department_id') > 0)
        {
            $where[] = 'a.department_id = :department_id';
            $params[':department_id'] = (int) Arr::get($filters, 'department_id');
        }
        if (Arr::get($filters, 'department_ids') !== NULL)
        {
            $ids = array_values(array_filter(array_map('intval', (array) Arr::get($filters, 'department_ids'))));
            $where[] = $ids ? 'a.department_id IN ('.implode(',', $ids).')' : '1=0';
        }

        $from = " FROM erp_310_employees e
            LEFT JOIN erp_310_assignments a ON a.employee_id = e.id AND a.is_primary = 1 AND a.valid_to IS NULL
            LEFT JOIN erp_310_departments d ON d.id = a.department_id
            LEFT JOIN erp_310_positions p ON p.id = a.position_id
            LEFT JOIN users u ON u.id = e.user_id
            WHERE ".implode(' AND ', $where);

        $count = DB::query(Database::SELECT, 'SELECT COUNT(*) total'.$from)->parameters($params)->execute()->current();
        $sql = "SELECT e.id, e.uuid, e.personnel_no, e.last_name, e.first_name, e.middle_name,
                    e.email, e.phone, e.status, e.hired_at, e.dismissed_at, e.user_id,
                    d.id department_id, d.name department_name, p.id position_id, p.name position_name,
                    u.username
                ".$from." ORDER BY e.last_name, e.first_name LIMIT :limit OFFSET :offset";
        $rows = DB::query(Database::SELECT, $sql)
            ->parameters($params + array(':limit' => max(1, min(100, (int) $limit)), ':offset' => max(0, (int) $offset)))
            ->execute()->as_array();

        return array('rows' => $rows, 'total' => (int) Arr::get($count, 'total', 0));
    }

    public function get($id)
    {
        $result = $this->search_by_id((int) $id);
        if ( ! $result) throw new Kohana_Exception('Сотрудник #:id не найден', array(':id' => (int) $id));
        return $result;
    }

    protected function search_by_id($id)
    {
        $row = DB::query(Database::SELECT, "
            SELECT e.*, a.id assignment_id, a.department_id, a.position_id, a.manager_id,
                   a.valid_from assignment_from, a.rate, d.name department_name, p.name position_name,
                   u.username, u.email user_email,
                   concat_ws(' ', m.last_name, m.first_name, m.middle_name) manager_name
            FROM erp_310_employees e
            LEFT JOIN erp_310_assignments a ON a.employee_id=e.id AND a.is_primary=1 AND a.valid_to IS NULL
            LEFT JOIN erp_310_departments d ON d.id=a.department_id
            LEFT JOIN erp_310_positions p ON p.id=a.position_id
            LEFT JOIN users u ON u.id=e.user_id
            LEFT JOIN erp_310_employees m ON m.id=a.manager_id
            WHERE e.id=:id LIMIT 1")
            ->param(':id', (int) $id)->execute()->current();
        return $row ?: NULL;
    }

    public function save_employee(array $data, $actor_id = NULL)
    {
        $id = (int) Arr::get($data, 'id');
        $employee = $id ? ORM::factory('Erp310_Employee', $id) : ORM::factory('Erp310_Employee');
        if ($id AND ! $employee->loaded()) throw new Kohana_Exception('Сотрудник не найден');

        $values = array();
        foreach (array('personnel_no','last_name','first_name','middle_name','email','phone','hired_at','dismissed_at','status','note') as $key)
        {
            $value = trim((string) Arr::get($data, $key));
            $values[$key] = $value === '' ? NULL : $value;
        }
        $values['personnel_no'] = mb_strtoupper((string) $values['personnel_no'], 'UTF-8');
        $values['last_name'] = (string) $values['last_name'];
        $values['first_name'] = (string) $values['first_name'];
        $values['status'] = $values['status'] ?: 'active';
        $values['user_id'] = (int) Arr::get($data, 'user_id') ?: NULL;
        if ($values['user_id'] AND ! ORM::factory('User', $values['user_id'])->loaded())
        {
            throw new Kohana_Exception('Выбранная учётная запись не найдена');
        }
        $values['search_text'] = mb_strtolower(trim(implode(' ', array_filter(array(
            $values['personnel_no'], $values['last_name'], $values['first_name'],
            $values['middle_name'], $values['email'], $values['phone'],
        )))), 'UTF-8');
        if ( ! $id) $values['created_by'] = $actor_id ? (int) $actor_id : NULL;

        DB::query(NULL, 'BEGIN')->execute();
        try
        {
            $employee->values($values);
            $id ? $employee->update() : $employee->create();
            $this->assign($employee->id, $data, $actor_id);
            DB::query(NULL, 'COMMIT')->execute();
        }
        catch (Exception $e)
        {
            DB::query(NULL, 'ROLLBACK')->execute();
            throw $e;
        }

        $this->publish($id ? 'erp310.employee.updated' : 'erp310.employee.created', array(
            'id' => (int) $employee->id, 'personnel_no' => $employee->personnel_no,
            'user_id' => $employee->user_id ? (int) $employee->user_id : NULL, 'actor_id' => $actor_id,
        ));
        return $employee;
    }

    public function assign($employee_id, array $data, $actor_id = NULL)
    {
        $department_id = (int) Arr::get($data, 'department_id');
        if ($department_id <= 0) return NULL;

        $current = ORM::factory('Erp310_Assignment')->where('employee_id', '=', (int) $employee_id)
            ->where('is_primary', '=', 1)->where('valid_to', 'IS', NULL)->find();
        $position_id = (int) Arr::get($data, 'position_id') ?: NULL;
        if ($position_id)
        {
            $position = ORM::factory('Erp310_Position', $position_id);
            if ( ! $position->loaded() OR ($position->department_id AND (int) $position->department_id !== $department_id))
            {
                throw new Kohana_Exception('Должность не относится к выбранному подразделению');
            }
        }
        $manager_id = (int) Arr::get($data, 'manager_id') ?: NULL;
        if ($manager_id)
        {
            $manager = ORM::factory('Erp310_Employee', $manager_id);
            if ( ! $manager->loaded() OR $manager->status !== 'active')
                throw new Kohana_Exception('Выбранный руководитель не найден или не работает');
            if ((int) $manager_id === (int) $employee_id)
                throw new Kohana_Exception('Сотрудник не может быть руководителем самому себе');
        }
        $valid_from = Arr::get($data, 'assignment_from') ?: $this->database_today();
        $rate = (float) Arr::get($data, 'rate', 1);

        if ($current->loaded() AND (int) $current->department_id === $department_id
            AND (int) $current->position_id === (int) $position_id AND (int) $current->manager_id === (int) $manager_id)
        {
            return $current;
        }
        if ($current->loaded())
        {
            $end = date('Y-m-d', strtotime($valid_from.' -1 day'));
            if ($end < $current->valid_from) $end = $current->valid_from;
            $current->valid_to = $end;
            $current->update();
        }

        $assignment = ORM::factory('Erp310_Assignment')->values(array(
            'employee_id' => (int) $employee_id, 'department_id' => $department_id,
            'position_id' => $position_id, 'manager_id' => $manager_id,
            'valid_from' => $valid_from, 'is_primary' => 1, 'rate' => $rate > 0 ? $rate : 1,
            'created_by' => $actor_id ? (int) $actor_id : NULL,
        ))->create();
        return $assignment;
    }

    public function assignment_history($employee_id)
    {
        return DB::query(Database::SELECT, "
            SELECT a.id, a.valid_from, a.valid_to, a.rate, a.is_primary,
                   d.name department_name, d.code department_code,
                   p.name position_name, p.code position_code,
                   concat_ws(' ', m.last_name, m.first_name, m.middle_name) manager_name
            FROM erp_310_assignments a
            JOIN erp_310_departments d ON d.id=a.department_id
            LEFT JOIN erp_310_positions p ON p.id=a.position_id
            LEFT JOIN erp_310_employees m ON m.id=a.manager_id
            WHERE a.employee_id=:employee_id
            ORDER BY a.valid_from DESC, a.id DESC")
            ->param(':employee_id', (int) $employee_id)->execute()->as_array();
    }

    public function dismiss_employee($id, $dismissed_at, $actor_id = NULL)
    {
        $employee = ORM::factory('Erp310_Employee', (int) $id);
        if ( ! $employee->loaded()) throw new Kohana_Exception('Сотрудник не найден');

        $dismissed_at = trim((string) $dismissed_at);
        $parsed = DateTime::createFromFormat('!Y-m-d', $dismissed_at);
        if ( ! $parsed OR $parsed->format('Y-m-d') !== $dismissed_at)
            throw new Kohana_Exception('Укажите корректную дату увольнения');
        if ($employee->hired_at AND $dismissed_at < $employee->hired_at)
            throw new Kohana_Exception('Дата увольнения не может быть раньше даты приёма');
        if ($employee->status === 'dismissed' AND $employee->dismissed_at === $dismissed_at)
            return $employee;

        DB::query(NULL, 'BEGIN')->execute();
        try
        {
            $employee->status = 'dismissed';
            $employee->dismissed_at = $dismissed_at;
            $employee->update();
            DB::update('erp_310_assignments')->set(array('valid_to' => $dismissed_at))
                ->where('employee_id', '=', $employee->id)->where('valid_to', 'IS', NULL)->execute();
            DB::query(NULL, 'COMMIT')->execute();
        }
        catch (Exception $e)
        {
            DB::query(NULL, 'ROLLBACK')->execute();
            throw $e;
        }

        $this->publish('erp310.employee.dismissed', array(
            'id' => (int) $employee->id, 'personnel_no' => $employee->personnel_no,
            'dismissed_at' => $dismissed_at, 'user_id' => $employee->user_id ? (int) $employee->user_id : NULL,
            'actor_id' => $actor_id,
        ));
        return $employee;
    }

    public function save_position(array $data, $actor_id = NULL)
    {
        $id = (int) Arr::get($data, 'id');
        $row = $id ? ORM::factory('Erp310_Position', $id) : ORM::factory('Erp310_Position');
        if ($id AND ! $row->loaded()) throw new Kohana_Exception('Должность не найдена');
        $row->values(array(
            'code' => mb_strtoupper(trim((string) Arr::get($data, 'code')), 'UTF-8'),
            'name' => trim((string) Arr::get($data, 'name')),
            'department_id' => (int) Arr::get($data, 'department_id') ?: NULL,
            'category' => Arr::get($data, 'category', 'specialist'),
            'status' => Arr::get($data, 'status', 'active'),
        ));
        $id ? $row->update() : $row->create();
        $this->publish($id ? 'erp310.position.updated' : 'erp310.position.created', array(
            'id' => (int) $row->id, 'code' => $row->code, 'actor_id' => $actor_id,
        ));
        return $row;
    }

    public function archive_position($id, $actor_id = NULL)
    {
        $row = ORM::factory('Erp310_Position', (int) $id);
        if ( ! $row->loaded()) throw new Kohana_Exception('Должность не найдена');
        $used = ORM::factory('Erp310_Assignment')->where('position_id', '=', $row->id)
            ->where('valid_to', 'IS', NULL)->count_all();
        if ($used > 0) throw new Kohana_Exception('Нельзя архивировать должность с действующими назначениями');
        $row->status = 'archived';
        $row->update();
        $this->publish('erp310.position.archived', array('id' => (int) $row->id, 'code' => $row->code, 'actor_id' => $actor_id));
        return $row;
    }

    public function save_department(array $data, $actor_id = NULL)
    {
        $id = (int) Arr::get($data, 'id');
        $row = $id ? ORM::factory('Erp310_Department', $id) : ORM::factory('Erp310_Department');
        if ($id AND ! $row->loaded()) throw new Kohana_Exception('Подразделение не найдено');
        $parent_id = (int) Arr::get($data, 'parent_id') ?: NULL;
        if ($id AND $parent_id === $id) throw new Kohana_Exception('Подразделение не может быть родителем самого себя');
        $row->values(array(
            'code' => mb_strtoupper(trim((string) Arr::get($data, 'code')), 'UTF-8'),
            'name' => trim((string) Arr::get($data, 'name')), 'parent_id' => $parent_id,
            'kind' => Arr::get($data, 'kind', 'department'), 'status' => Arr::get($data, 'status', 'active'),
            'sort_order' => (int) Arr::get($data, 'sort_order', 100),
        ));
        if ( ! $id) $row->created_by = $actor_id ? (int) $actor_id : NULL;
        $id ? $row->update() : $row->create();
        $this->publish($id ? 'erp310.department.updated' : 'erp310.department.created', array('id' => (int) $row->id, 'code' => $row->code, 'actor_id' => $actor_id));
        return $row;
    }

    /** Keep effective-date defaults aligned with PostgreSQL CURRENT_DATE near timezone boundaries. */
    protected function database_today()
    {
        return (string) DB::query(Database::SELECT, 'SELECT CURRENT_DATE AS today')->execute()->get('today');
    }

    protected function publish($name, array $payload)
    {
        try { Event::fire($name, $payload); } catch (Exception $e) {
            Kohana::$log->add(Log::ERROR, 'erp310: событие :name не опубликовано: :error', array(':name' => $name, ':error' => $e->getMessage()));
        }
    }
}
