<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Генератор каркаса модуля (см. docs/MODULE_GUIDE.md).
 *
 *   bin/console module:scaffold --id=402 --slug=orders --name="Заказы"
 *
 * Создаёт application/modules/erp402_orders с README, init.php, контроллером,
 * сервисом, моделью, миграцией PostgreSQL, view и языковым файлом.
 * Существующий модуль не перезаписывает (для этого нужен --force).
 *
 * @package Erp218
 */
class Service_Erp218_Scaffolder {

    /**
     * @var Service_Erp218_Scaffolder
     */
    protected static $_instance;

    /**
     * @return Service_Erp218_Scaffolder
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct() {}

    /**
     * Проверяет входные параметры.
     *
     * @param   integer $id
     * @param   string  $slug
     * @return  void
     * @throws  Kohana_Exception
     */
    public function validate($id, $slug)
    {
        if ( ! preg_match('/^\d{3}$/', (string) $id))
        {
            throw new Kohana_Exception('ID модуля должен состоять из трёх цифр, например 401');
        }

        if ( ! preg_match('/^[a-z][a-z0-9_]*$/', $slug))
        {
            throw new Kohana_Exception('Слаг модуля: строчные латинские буквы, цифры и подчёркивание (например, orders)');
        }
    }

    /**
     * Генерирует модуль.
     *
     * @param   array   $params  ['id' => int, 'slug' => string, 'name' => string, 'force' => bool]
     * @return  array   ['path' => string, 'files' => array, 'created' => array]
     */
    public function generate(array $params)
    {
        $id    = (int) Arr::get($params, 'id');
        $slug  = Arr::get($params, 'slug');
        $name  = Arr::get($params, 'name', $slug);
        $force = (bool) Arr::get($params, 'force', FALSE);

        $this->validate($id, $slug);

        $module  = sprintf('erp%d_%s', $id, $slug);
        $path    = APPPATH.'modules/'.$module;

        $created = [];

        if (is_dir($path) AND ! $force)
        {
            throw new Kohana_Exception('Модуль :module уже существует (используйте --force для перезаписи)', [
                ':module' => $module,
            ]);
        }

        $pascal = $this->pascal_case($slug);
        $label  = ($name === NULL) ? $slug : $name;

        $files = [
            'README.md' => $this->readme($module, $id, $slug, $label),
            'init.php' => $this->init($id, $slug, $label),
            'classes/Controller/Erp'.$id.'/Main.php' => $this->controller($id, $slug, $label),
            'classes/Service/Erp'.$id.'/'.$pascal.'.php' => $this->service($id, $pascal, $label),
            'classes/Model/Erp'.$id.'/'.$pascal.'.php' => $this->model($id, $slug, $pascal),
            'migrations_pg/pgMigration_001_Erp'.$id.'_'.$pascal.'_CreateTables.php' => $this->migration($id, $slug, $pascal),
            'views/erp'.$id.'/main/index.php' => $this->view($id, $label),
            'i18n/ru.php' => $this->i18n($id, $label),
        ];

        foreach ($files as $relative => $contents)
        {
            $file = $path.'/'.$relative;

            if ( ! is_dir(dirname($file)))
            {
                mkdir(dirname($file), 0775, TRUE);
            }

            if (is_file($file) AND ! $force)
            {
                continue;
            }

            file_put_contents($file, $contents);
            $created[] = $module.'/'.$relative;
        }

        return [
            'path'    => $path,
            'module'  => $module,
            'created' => $created,
        ];
    }

    /**
     * slug_name => SlugName
     *
     * @param   string  $slug
     * @return  string
     */
    protected function pascal_case($slug)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $slug)));
    }

    // -- Шаблоны ---------------------------------------------------------------

    protected function header()
    {
        return "<?php defined('SYSPATH') or die('No direct script access.');\n\n";
    }

    protected function readme($module, $id, $slug, $name)
    {
        return <<<MD
# {$module} — {$name}

## Назначение

Кратко: какую задачу решает модуль, кто пользователь, какие процессы автоматизирует.

## Состав

| Слой | Класс | Назначение |
|---|---|---|
| Контроллер | `Controller_Erp{$id}_Main` | страница/интерфейс |
| Сервис | `Service_Erp{$id}_Xxx` | бизнес-логика (единственный вход в данные модуля) |
| Модель | `Model_Erp{$id}_Xxx` | ORM-доступ к таблицам `erp_{$id}_*` |
| Миграция | `pgMigration_001_Erp{$id}_Xxx_CreateTables` | схема PostgreSQL |

## Таблицы

* `erp_{$id}_...` — описание.

## События

* Публикует: `erp{$id}.<object>.<action>`
* Подписан на: —

## Права (application/config.dist + acl)

* `erp{$id}_{$slug}.view` — просмотр
* `erp{$id}_{$slug}.edit` — изменение

## HTTP / API

* `GET /erp{$id}/main/index` — список
* `GET /erp{$id}/api/items` — JSON-выгрузка

## Как запустить

```bash
bin/console db:migrate --module={$module}
bin/console dev:doctor
```
MD;
    }

    protected function init($id, $slug, $name)
    {
        return $this->header().<<<PHP
/**
 * Модуль {$name} (ID {$id}).
 * Регистрация роутов и подписок на события шины.
 */

Route::set('erp{$id}', 'erp{$id}(/<controller>(/<action>(/<id>)))', ['id' => '[0-9]+'])
    ->defaults([
        'directory'  => 'Erp{$id}',
        'controller' => 'Main',
        'action'     => 'index',
    ]);

// Пример подписки на событие другого модуля:
// Event::listen('erp100.user.created', function (\$payload) {
//     // ...
// });
PHP;
    }

    protected function controller($id, $slug, $name)
    {
        $pascal = $this->pascal_case($slug);

        return $this->header().<<<PHP
/**
 * Контроллер модуля {$name}.
 *
 * Правила: контроллер не работает с БД напрямую — только через Service.
 */
class Controller_Erp{$id}_Main extends Controller_Erp100_Base {

    public function action_index()
    {
        \$items = Service_Erp{$id}_{$pascal}::instance()->all();

        \$this->set_title('{$name}');
        \$this->template->content = View::factory('erp{$id}/main/index')
            ->set('items', \$items);
    }
}
PHP;
    }

    protected function service($id, $pascal, $name)
    {
        return $this->header().<<<PHP
/**
 * Сервис модуля {$name}: единственная точка доступа к данным модуля.
 *
 * Модули не обращаются к таблицам друг друга напрямую — только через сервисы.
 */
class Service_Erp{$id}_{$pascal} {

    /**
     * @var Service_Erp{$id}_{$pascal}
     */
    protected static \$_instance;

    /**
     * @return Service_Erp{$id}_{$pascal}
     */
    public static function instance()
    {
        if (self::\$_instance === NULL)
        {
            self::\$_instance = new self;
        }

        return self::\$_instance;
    }

    protected function __construct() {}

    /**
     * Список записей.
     *
     * @return array
     */
    public function all()
    {
        return ORM::factory('Erp{$id}_{$pascal}')
            ->order_by('id', 'DESC')
            ->find_all()
            ->as_array();
    }
}
PHP;
    }

    protected function model($id, $slug, $pascal)
    {
        return $this->header().<<<PHP
/**
 * Модель таблицы erp_{$id}_{$slug}.
 */
class Model_Erp{$id}_{$pascal} extends ORM {

    protected \$_table_name = 'erp_{$id}_{$slug}';

    protected \$_table_columns = [
        'id'         => ['type' => 'int'],
        'name'       => ['type' => 'string'],
        'created_at' => ['type' => 'string'],
    ];

    public function rules()
    {
        return [
            'name' => [
                ['not_empty'],
                ['max_length', [':value', 255]],
            ],
        ];
    }
}
PHP;
    }

    protected function migration($id, $slug, $pascal)
    {
        return $this->header().<<<PHP
/**
 * Схема модуля {$slug} ({$pascal}).
 *
 * Миграции PromERP: PostgreSQL, каждая — в транзакции, откат в down().
 */
class pgMigration_001_Erp{$id}_{$pascal}_CreateTables extends pgMigration {

    public function up()
    {
        \$this->run_query("
            CREATE TABLE IF NOT EXISTS erp_{$id}_{$slug} (
                id          SERIAL PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP DEFAULT NULL
            );
        ");
    }

    public function down()
    {
        \$this->run_query("DROP TABLE IF EXISTS erp_{$id}_{$slug} CASCADE;");
    }
}
PHP;
    }

    protected function view($id, $name)
    {
        return $this->header().<<<PHP
<?php /** @var array \$items */ ?>
<div class="p-4">
    <h1 class="text-xl font-semibold mb-4">{$name}</h1>

    <?php if (empty(\$items)): ?>
        <p class="text-gray-500">Пока нет данных.</p>
    <?php else: ?>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-2">ID</th>
                    <th class="py-2">Название</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (\$items as \$item): ?>
                <tr class="border-t">
                    <td class="py-2"><?= \$item->id ?></td>
                    <td class="py-2"><?= HTML::chars(\$item->name) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
PHP;
    }

    protected function i18n($id, $name)
    {
        return $this->header().<<<PHP
/**
 * Языковые строки модуля {$name} (ID {$id}).
 */
return [
    'module' => '{$name}',
];
PHP;
    }
}
