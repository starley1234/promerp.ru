<?php defined('SYSPATH') OR die('No direct script access.');
 
class HTTP_Exception_404 extends Kohana_HTTP_Exception_404 {
	
    public $site_name;
    
    public function get_response()
    {
		$template = View::factory('template_tailwindcss');
		$template->content = View::factory('errors/404');
		
		$site_config = Kohana::$config->load('site_config');
		   
		$site_name = $site_config->get('site_name');
		View::set_global('site_name', $site_name);
		$this->site_name = $site_name;
		
		$site_logo = $site_config->get('site_logo');
		View::set_global('site_logo', $site_logo);
		
		
        $response = Response::factory()
		->status(404)
		->body($template->render())
		;
        return $response;
    }
}