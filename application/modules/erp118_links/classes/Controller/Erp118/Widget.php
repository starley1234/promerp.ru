<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp118_Widget extends Controller_Base {
	
    protected $auth_required = FALSE; 
    
    public function action_bookmark_button()
    {
		$this->auto_render = FALSE;
		
		// 2. Если не залогинен — ничего не выводим
		if (!$this->_current_user) return;

        // 3. Передаем данные во вьюшку виджета
        $view = View::factory('erp118/links/widgets/bookmark_button');
        
        // Можно передать дополнительные параметры, если нужно
        $this->response->body($view);
		
    }
}