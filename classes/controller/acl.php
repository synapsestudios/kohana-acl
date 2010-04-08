<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Acl extends Controller {

	public function action_index()
	{
		$a = array('a', 'b', 'd', 'z');
		$b = array('a', 'c', 'd', 'e', 'y', 'z');
		$c = array_unique(array_merge($a, $b));
		print_r($c);

		Arr::

		$this->request->response = 'nada';
	}

	public function action_routes()
	{
		$route = Route::get('default');
		$defaults = new ReflectionProperty('Route', '_defaults');
		$defaults->setAccessible(TRUE);
		$defaults = $defaults->getValue($route);
		if ( ! isset($defaults['action']) OR empty($defaults['action']))
		{
			$defaults['action'] = Route::$default_action;
		}

		echo '<pre>';
		print_r($defaults);
		echo '</pre>';
	}

} // End Acl
