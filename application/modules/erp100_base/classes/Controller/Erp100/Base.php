<?php defined('SYSPATH') or die('No direct script access.');



/**
 * Controller_Erp100_Base
 *
 * Базовый контроллер для всех контроллеров приложения.
 * Инициализирует основной шаблон, загружает общие данные и управляет мета-тегами.
 */
#[AllowDynamicProperties]
abstract class Controller_Erp100_Base extends Controller {

    /**
     * @var  string  Название сайта
     */
    public $site_name;

    /**
     * @var  string  Режим отображения таблиц (div/table)
     */
    public $table_view;

    public $template = 'template_tailwindcss';
    // public $template = 'template_bootstrap_5_3';

    /**
     * @var  boolean  Включить ли автоматический рендеринг шаблона
     */
    public $auto_render = TRUE;

    /**
     * @var  array  Массив для хранения мета-тегов (title, description, keywords)
     */
    protected $_meta = array(
        'title'       => '',
        'description' => '',
        'keywords'    => ''
    );

    /**
     * @var  array  Массив для хранения CSS файлов
     */
    protected $_styles = array();

    /**
     * @var  array  Массив для хранения JavaScript файлов
     */
    protected $_scripts = array();

    /**
     * @var  Model_User|null  Текущий авторизованный пользователь.
     */
    protected $_current_user = NULL;

    /**
     * @var  array  Массив имен ролей текущего авторизованного пользователя.
     */
    protected $_current_user_roles = [];

    public function before()
    {
        parent::before();
      
		// Controller_Robotprotection::check_protection();
      
		$site_config = Kohana::$config->load('site_config');
		   
		$site_name = $site_config->get('site_name');
		View::set_global('site_name', $site_name);
		$this->site_name = $site_name;
        
        $site_logo = $site_config->get('site_logo');
		View::set_global('site_logo', $site_logo);
		
		if ((isset ($_COOKIE["table_view"])) AND (Cookie::get("table_view", 'div')=='table')) $table_view = 'table'; else $table_view = 'div';
		View::set_global('table_view', $table_view);
		$this->table_view = $table_view;

        // Проверяем авторизацию и получаем данные пользователя
        if (Auth::instance()->logged_in('login'))
        {
            $user_model = Auth::instance()->get_user();
            $this->_current_user = $user_model;

            $roles = [];
            foreach ($user_model->roles->find_all() as $role) {
                $roles[] = $role->name;
            }
            $this->_current_user_roles = $roles;
        }
        else
        {
            $this->_current_user = NULL;
            $this->_current_user_roles = [];
        }
		
        View::bind_global('current_user', $this->_current_user);
        View::bind_global('current_user_roles', $this->_current_user_roles); 

        // Режим интерфейса (простой/профессиональный) выбираем до вывода: он пишет cookie.
        Ui_Mode::current();

        // Инициализация шаблона и передача общих данных
        if ($this->auto_render === TRUE)
        {
            $this->template = View::factory($this->template);

          	View::bind_global('styles', $this->_styles); 
            View::bind_global('scripts', $this->_scripts); 
			View::bind_global('title', $this->_meta['title']); 
          
            $this->template->set(array(
                'meta'               => $this->_meta,
                'styles'             => $this->_styles,
                'scripts'            => $this->_scripts,
                'content'            => '',
                'header'             => '',
                'footer'             => '',
                'sidebar'            => '',
                'current_user'       => $this->_current_user,
                'current_user_roles' => $this->_current_user_roles,
            ));
        }
    }
	
    /**
     * Метод для генерации и установки меню в шаблон с поддержкой разрешений (acl)
     * 
     * @param array $menu_items Список элементов меню
     */
    protected function set_menu(array $menu_items)
    {
        $allowed_menu_items = [];
        
        // Блок спец-ссылок (оставлен без изменений согласно логике автора)
        if ((array_key_exists('erp118_links', Kohana::modules())) AND ($this->_current_user)) {
            $allowed_menu_items[] = [
                'title' => '',
                'href'  => '/erp118_links',
                'icon'  => 'fas fa-link',
                'roles' => ['login']
            ];
        }

        foreach ($menu_items as $item) {
            // Добавляем 'acl' в значения по умолчанию
            $item = array_merge(array(
                'title'      => '',
                'href'       => '#',
                'roles'      => [],
                'acl' => '', // Новое поле
                'active'     => 0,
                'icon'       => ''
            ), $item);

            $is_allowed = FALSE;

            // 1. Проверяем наличие 'acl' (Приоритет)
            if (!empty($item['acl'])) {
                if ($item['acl'] === 'all') {
                    $is_allowed = TRUE;
                } else {
                    // Используем встроенный метод can()
                    $is_allowed = $this->can($item['acl']);
                }
            } 
            // 2. Если acl не указан, проверяем 'roles' (Совместимость)
            elseif (!empty($item['roles'])) {
                $is_allowed = $this->has_any_role($item['roles']);
            } 
            // 3. Если ничего не указано — пункт доступен всем
            else {
                $is_allowed = TRUE;
            }

            if ($is_allowed) {
                $allowed_menu_items[] = $item;
            }
        }

        // --- Логика определения активного пункта (без изменений) ---
        $current_uri = '/' . $this->request->uri();
        
        $search_items = $allowed_menu_items;
        usort($search_items, function($a, $b) {
            return strlen($b['href']) - strlen($a['href']);
        });

        $active_href = null;
        foreach ($search_items as $item) {
            if ($item['href'] === '#' || $item['href'] === '') continue;

            $href = rtrim($item['href'], '/');
            if ($current_uri === $href || strpos($current_uri, $href . '/') === 0) {
                $active_href = $item['href'];
                break;
            }
        }

        foreach ($allowed_menu_items as $key => $item) {
            if ($active_href !== null && $item['href'] === $active_href) {
                $allowed_menu_items[$key]['active'] = 1;
                $active_href = null; 
            }
        }

        if ($this->auto_render === TRUE) {
            $this->template->menu_items = $allowed_menu_items;
        }
    }

    public function after()
    {
        if ($this->auto_render === TRUE)
        {
            $this->template->meta = $this->_meta;
            $this->template->styles = $this->_styles;
            $this->template->scripts = $this->_scripts;
            $this->response->body($this->template->render());
        }
		
		// ! В продакшене убрать
		// $this->action_update_declared_classes_cache();

        parent::after();
    }

    protected function has_role($role_name)
    {
        return in_array($role_name, $this->_current_user_roles);
    }

    protected function has_any_role(array $roles)
    {
        foreach ($roles as $role) {
            if ($this->has_role($role)) {
                return true;
            }
        }
        return false;
    }

    protected function set_title($title)
    {
        $this->_meta['title'] = $title;
    }

    protected function set_description($description)
    {
        $this->_meta['description'] = $description;
    }

    protected function set_keywords($keywords)
    {
        $this->_meta['keywords'] = $keywords;
    }

    /**
     * Структурированные данные schema.org для поисковых роботов.
     *
     * Массив уходит в шаблон как есть и печатается в `<script
     * type="application/ld+json">`: поисковику важно понимать, что перед ним
     * программный продукт, а у страницы — ответы на вопросы, а не просто
     * текст. См. https://schema.org/SoftwareApplication и /FAQPage.
     *
     * @param   array  $data
     * @return  void
     */
    protected function set_json_ld(array $data)
    {
        $this->template->json_ld = $data;
    }

    protected function add_style($file_path, $media = 'screen')
    {
        $this->_styles[] = array('file' => $file_path, 'media' => $media);
    }

    protected function add_script($file_path, $defer = FALSE)
    {
        $this->_scripts[] = array('file' => $file_path, 'defer' => $defer);
    }

    protected function add_inline_script($script_code)
    {
        $this->_scripts[] = array('code' => $script_code);
    }

    protected function add_inline_style($style_code)
    {
        $this->_styles[] = array('code' => $style_code);
    }
	
	protected function is_owner($data, $owner_property = 'user_id')
	{
		if (!$this->_current_user) {
			return FALSE;
		}

		$current_user_id = (int) $this->_current_user->id;

		if (is_object($data)) {
			if (isset($data->{$owner_property})) {
				return (int) $data->{$owner_property} === $current_user_id;
			} elseif (method_exists($data, 'get_owner_id')) {
				return (int) $data->get_owner_id() === $current_user_id;
			}
		} elseif (is_array($data)) {
			if (isset($data[$owner_property])) {
				return (int) $data[$owner_property] === $current_user_id;
			}
		}

		return FALSE;
	}
	
    /**
     * Обновляет кэш объявленных классов и возвращает его в формате JSON.
     * Этот метод полезен для получения актуального списка всех классов,
     * доступных в приложении, что может быть использовано для дальнейшей рефлексии.
	 * ! В продакшене убрать
     *
     * @return void
     */
    private function action_update_declared_classes_cache()
    {
        // Собираем информацию о классах старт
        $file_path = APPPATH . 'cache' . DIRECTORY_SEPARATOR . 'declared_classes.json';
        $current_declared_classes = get_declared_classes();

        $existing_cached_classes = [];
        if (file_exists($file_path)) {
            $json_content = file_get_contents($file_path);
            if ($json_content !== false) {
                $decoded_classes = json_decode($json_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_classes)) {
                    $existing_cached_classes = $decoded_classes;
                }
            }
        }

        $all_classes = array_unique(array_merge($existing_cached_classes, $current_declared_classes));
        sort($all_classes);

        $json_content_to_write = json_encode($all_classes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $response_data = [
            'status' => 'error',
            'message' => 'Failed to update cache.',
            'file_path' => $file_path,
            'class_count' => count($all_classes),
        ];

        if ($json_content_to_write === false) {
            $response_data['message'] = 'Failed to encode class list to JSON.';
            //$this->response->status(500);
        } else {
            if (file_put_contents($file_path, $json_content_to_write, LOCK_EX) !== false) {
                $response_data['status'] = 'success';
                $response_data['message'] = 'Declared classes cache successfully updated.';
                //$this->response->status(200);
            } else {
                $response_data['message'] = 'Failed to write class list to cache file.';
                //$this->response->status(500);
            }
        }
        // Собираем информацию о классах end

        //$this->response->headers('Content-Type', 'application/json');
        //$this->response->body(json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
	
    /**
     * Главный метод проверки прав в системе (Единообразно)
     * 
     * @param string $ability Ключ права (например, 'client.edit')
     * @param mixed $object (Опционально) Объект для проверки владения
     */
    /** Object-level data boundary; permission must still be checked separately. */
    public function in_scope($resource_type, $resource_id)
    {
        if ( ! $this->_current_user) return FALSE;
        return Service_Erp100_Access::instance()->allows((int)$this->_current_user->id, $resource_type, (int)$resource_id);
    }

    public function can($ability, $object = NULL)
    {
        // 1. "Просто": Админ всегда может всё
        if ($this->has_role('admin')) {
            return TRUE;
        }

        // 2. "Хорошо": Если передан объект, проверяем владение
        // (Например: редактировать свою задачу можно без спец. прав)
        if ($object !== NULL) {
            if ($this->is_owner($object)) {
                return TRUE;
            }
        }

        // 3. Проверка через системный ACL (маппинг ролей и прав)
        return ACL::check($this->_current_user_roles, $ability);
    }

}