<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp118_Links extends Controller_Base {

    protected $user_id;

    public function before()
    {
        parent::before();
		
        if (!$this->_current_user) {
            $this->auto_render = FALSE;
            throw new HTTP_Exception_403('Доступ запрещен. Требуются права администратора или менеджера документов.');
        }

        $this->user_id = $this->_current_user->id;

        $menu = array(
            array(
                'title' => 'Управление',
                'href'  => '/erp118_links/manage',
                'icon'  => 'fas fa-cog'
            ),
        );

        $custom_links = ORM::factory('Erp118_Links')->get_user_links($this->user_id, TRUE);

        foreach ($custom_links as $link) {
            $menu[] = array(
                'title' => $link->title,
                'href'  => $link->url,
                'icon'  => $link->icon_class ? $link->icon_class : 'fas fa-link'
            );
        }

        $this->set_menu($menu);
    }

    public function action_index()
    {
        $links = ORM::factory('Erp118_Links')->get_user_links($this->user_id);

        $this->template->title = 'Мои ссылки';
        $this->template->content = View::factory('erp118/links/cards_tailwindcss')
            ->set('user_links', $links);
    }

    public function action_toggle_save()
    {
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json');

        if ($this->request->is_ajax() AND $this->request->method() === Request::POST)
        {
            $url = Arr::get($_POST, 'url');
            if (empty($url))
            {
                $this->response->status(400)->body(json_encode(array('status' => 'error', 'message' => 'URL is required.')));
                return;
            }

            try
            {
                $icon_class = Arr::get($_POST, 'icon_class');
                if (empty($icon_class)) {
                    $icon_class = $this->get_preset_icon_class($url);
                }

                $parsed_url = parse_url($url);
                $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
                $group_name = '';
                if (!empty($path)) {
                    $parts = explode('/', $path);
                    $group_name = $parts[0];
                }

                $data = array(
                    'url'          => $url,
                    'title'        => Arr::get($_POST, 'title', 'Новая ссылка'),
                    'description'  => Arr::get($_POST, 'description'),
                    'is_menu_item' => (int) Arr::get($_POST, 'is_menu_item', 0),
                    'icon_class'   => $icon_class,
                    'group_name'   => empty($group_name) ? NULL : $group_name,
                );

                $result = ORM::factory('Erp118_Links')->toggle_link($this->user_id, $data);

                if ($result['action'] === 'deleted') {
                    $this->response->body(json_encode(array('status' => 'success', 'action' => 'deleted', 'message' => 'Link removed successfully!')));
                } else {
                    $this->response->body(json_encode(array('status' => 'success', 'action' => 'saved', 'message' => 'Link saved successfully!', 'link_id' => $result['id'])));
                }
            }
            catch (ORM_Validation_Exception $e)
            {
                $this->response->status(400)->body(json_encode(array('status' => 'error', 'message' => 'Validation failed', 'errors' => $e->errors('models'))));
            }
            catch (Exception $e)
            {
                $this->response->status(500)->body(json_encode(array('status' => 'error', 'message' => $e->getMessage())));
            }
        }
    }

    public function action_check_link_status()
    {
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json');

        $url = Arr::get($_GET, 'url');
        if (empty($url))
        {
            $this->response->status(400)->body(json_encode(array('status' => 'error', 'message' => 'URL is required.')));
            return;
        }

        $link = ORM::factory('Erp118_Links')
            ->where('user_id', '=', $this->user_id)
            ->and_where('url', '=', $url)
            ->find();

        $this->response->body(json_encode(array('status' => 'success', 'is_saved' => $link->loaded())));
    }

    public function action_manage()
    {
        $this->add_script('https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js');

        $model = ORM::factory('Erp118_Links');
        $links = $model->get_user_links($this->user_id);
        $groups = $model->get_user_groups($this->user_id);
        array_unshift($groups, '');

        $this->template->title = 'Управление ссылками';
        $this->template->content = View::factory('erp118/links/manage_tailwindcss')
            ->set('links', $links)
            ->set('groups', $groups);
    }

    public function action_update()
    {
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json');

        if ($this->request->is_ajax() AND $this->request->method() === Request::POST)
        {
            $link = ORM::factory('Erp118_Links', Arr::get($_POST, 'id'));

            if (!$link->loaded() OR $link->user_id != $this->user_id)
            {
                $this->response->status(404)->body(json_encode(array('status' => 'error', 'message' => 'Link not found.')));
                return;
            }

            try
            {
                $group_name = Arr::get($_POST, 'group_name');
                $link->values(array(
                    'title'        => Arr::get($_POST, 'title'),
                    'description'  => Arr::get($_POST, 'description'),
                    'color'        => Arr::get($_POST, 'color'),
                    'icon_class'   => Arr::get($_POST, 'icon_class'),
                    'group_name'   => empty($group_name) ? NULL : $group_name,
                    'is_menu_item' => (int) Arr::get($_POST, 'is_menu_item', 0),
                ))->save();

                $this->response->body(json_encode(array('status' => 'success', 'message' => 'Link updated successfully!')));
            }
            catch (ORM_Validation_Exception $e)
            {
                $this->response->status(400)->body(json_encode(array('status' => 'error', 'errors' => $e->errors('models'))));
            }
        }
    }

    public function action_delete()
    {
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json');

        if ($this->request->is_ajax() AND $this->request->method() === Request::POST)
        {
            $link = ORM::factory('Erp118_Links', Arr::get($_POST, 'id'));

            if ($link->loaded() AND $link->user_id == $this->user_id)
            {
                $link->delete();
                $this->response->body(json_encode(array('status' => 'success', 'message' => 'Link deleted!')));
            }
            else
            {
                $this->response->status(404)->body(json_encode(array('status' => 'error', 'message' => 'Not found.')));
            }
        }
    }

    public function action_reorder()
    {
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json');

        if ($this->request->is_ajax() AND $this->request->method() === Request::POST)
        {
            $order_data = Arr::get($_POST, 'order');
            if (is_array($order_data))
            {
                try {
                    ORM::factory('Erp118_Links')->update_orders($this->user_id, $order_data);
                    $this->response->body(json_encode(array('status' => 'success')));
                } catch (Exception $e) {
                    $this->response->status(500)->body(json_encode(array('status' => 'error', 'message' => $e->getMessage())));
                }
            }
        }
    }

    public function action_get_links_api()
    {
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json');

        $links = ORM::factory('Erp118_Links')->get_user_links($this->user_id);
        $links_data = array();
        foreach ($links as $link) {
            $links_data[] = $link->as_array();
        }

        $this->response->body(json_encode(array('status' => 'success', 'links' => $links_data)));
    }

    protected function get_preset_icon_class($url)
    {
        $preset_icons = array(
            'lib100' => 'fas fa-robot', 'lib299' => 'fas fa-user', 'lib305' => 'fas fa-address-book',
            'lib306' => 'fas fa-lightbulb', 'lib307' => 'fas fa-database', 'lib308' => 'fas fa-building',
            'lib309' => 'fas fa-dollar-sign', 'lib310' => 'fas fa-box', 'lib312' => 'fas fa-search',
            'lib314' => 'fas fa-cog', 'lib316' => 'fas fa-shield-alt', 'lib319' => 'fas fa-file-contract',
            'lib320' => 'fas fa-book-open', 'lib321' => 'fas fa-comments', 'lib322' => 'fas fa-image',
            'lib323' => 'fas fa-bolt', 'lib325' => 'fas fa-tools', 'lib324' => 'fas fa-medkit',
            'lib329' => 'fas fa-tag', 'lib331' => 'fas fa-project-diagram', 'lib333' => 'fas fa-flask',
            'lib332' => 'fas fa-check-circle', 'lib334' => 'fas fa-sync', 'lib336' => 'fas fa-journal-whills',
            'lib335' => 'fas fa-money-bill-wave', 'lib337' => 'fas fa-comment-alt', 'lib338' => 'fas fa-chart-line',
            'lib339' => 'fas fa-question-circle', 'lib340' => 'fas fa-brain', 'lib341' => 'fas fa-users',
            'lib342' => 'fas fa-id-badge', 'lib343' => 'fas fa-book', 'lib344' => 'fas fa-briefcase',
            'lib345' => 'fas fa-cubes', 'lib346' => 'fas fa-sitemap', 'lib347' => 'fas fa-mobile-alt',
            'lib348' => 'fas fa-file-code', 'lib349' => 'fas fa-wrench', 'lib350' => 'fas fa-globe',
            'lib352' => 'fas fa-compass', 'lib353' => 'fas fa-boxes', 'lib354' => 'fas fa-chart-area',
            'lib355' => 'fas fa-map-marker-alt', 'lib356' => 'fas fa-truck', 'lib357' => 'fas fa-shipping-fast',
            'lib358' => 'fas fa-comment-dots', 'lib359' => 'fas fa-code', 'lib360' => 'fas fa-hammer',
            'lib361' => 'fas fa-microchip', 'lib362' => 'fas fa-language', 'lib363' => 'fas fa-puzzle-piece',
            'lib364' => 'fas fa-globe-europe', 'lib366' => 'fas fa-question', 'lib367' => 'fas fa-gamepad',
            'lib369' => 'fas fa-file-alt', 'lib371' => 'fas fa-lightbulb', 'lib372' => 'fas fa-user-tie',
            'lib370' => 'fas fa-bar-chart', 'lib373' => 'fas fa-book-reader', 'lib374' => 'fas fa-user-friends',
            'lib375' => 'fas fa-assistive-listening-systems', 'lib376' => 'fas fa-plane', 'lib311' => 'fas fa-store',
            'lib328' => 'fas fa-file-signature', 'lib351' => 'fas fa-archive', 'lib378' => 'fas fa-bezier-curve',
            'lib379' => 'fas fa-home', 'lib380' => 'fas fa-seedling', 'lib381' => 'fas fa-leaf',
            'lib382' => 'fas fa-toolbox', 'lib383' => 'fas fa-shopping-basket', 'lib384' => 'fas fa-moon',
            'lib385' => 'fas fa-comment', 'lib386' => 'fas fa-graduation-cap', 'lib387' => 'fas fa-globe-americas',
            'lib388' => 'fas fa-newspaper', 'lib389' => 'fas fa-layer-group', 'lib390' => 'fas fa-city',
            'lib391' => 'fas fa-check-square', 'lib392' => 'fas fa-robot', 'lib393' => 'fas fa-cogs',
            'lib394' => 'fas fa-user-group', 'lib395' => 'fas fa-parachute-box', 'lib397' => 'fas fa-certificate',
            'lib398' => 'fas fa-id-card', 'lib399' => 'fas fa-hand-holding-usd', 'lib401' => 'fas fa-screwdriver',
            'lib402' => 'fas fa-file-invoice', 'lib403' => 'fas fa-tasks', 'lib404' => 'fas fa-chart-bar',
            'lib405' => 'fas fa-shopping-cart', 'lib406' => 'fas fa-recycle', 'lib407' => 'fas fa-charging-station',
            'lib409' => 'fas fa-clipboard-list', 'lib400' => 'fas fa-university', 'lib408' => 'fas fa-hotel',
            'lib410' => 'fas fa-columns', 'lib411' => 'fas fa-magic', 'lib412' => 'fas fa-images',
            'lib1' => 'fas fa-list', 'lib303' => 'fas fa-server', 'lib413' => 'fas fa-pen-nib',
            'lib415' => 'fas fa-photo-video', 'lib500' => 'fas fa-headset', 'lib501' => 'fas fa-info-circle',
            'lib502' => 'fas fa-file-medical-alt', 'lib504' => 'fas fa-file-code', 'sqr_' => 'fas fa-qrcode',
            'lib515' => 'fas fa-tools', 'imagegen' => 'fas fa-magic', 'styleforge' => 'fas fa-image',
        );

        foreach ($preset_icons as $url_part => $icon_class) {
            if (strpos($url, $url_part) !== false) return $icon_class;
        }
        return 'fas fa-link';
    }
}