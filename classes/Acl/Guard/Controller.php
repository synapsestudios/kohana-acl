<?php

class Acl_Guard_Controller implements Acl_Rule_ProviderInterface, Acl_Resource_ProviderInterface
{
	protected $_rules;

	protected $_acl_service;

	protected $_castellan;

	public function __construct(array $rules, $acl_service)
	{
		$this->_acl_service = $acl_service;

		foreach ($rules as $rule)
		{
			if ( ! is_array($rule['roles']))
			{
				$rule['roles'] = array($rule['roles']);
			}

			$rule['action'] = Arr::get($rule, 'action', array(null));

			foreach ((array) $rule['controller'] as $controller)
			{
				foreach ($rule['action'] as $action)
				{
					$this->_rules[$this->get_resource_name($controller, $action)] = $rule['roles'];
				}
			}
		}

		$this->_acl_service->add_resource_provider($this);
		$this->_acl_service->add_rule_provider($this);
	}

	public function attach(Castellan\Castellan $castellan)
	{
		$this->_castellan = $castellan;
		$this->_attached  = TRUE;
		$castellan->addListener('controller.execute', array($this, 'on_execute'));
	}

	public function get_resources()
	{
		$resources = array();

		foreach (array_keys($this->_rules) as $resource)
		{
			$resources[] = $resource;
		}

		return $resources;
	}

	public function get_rules()
	{
		$rules = array();
		foreach ($this->_rules as $resource => $roles) {
			$rules[] = array($roles, $resource);
		}

		return array('allow' => $rules);
	}

	public function get_resource_name($controller, $action = NULL)
	{
		if ($action) {
			return strtolower(sprintf('controller/%s:%s', $controller, $action));
		}

		return strtolower(sprintf('controller/%s', $controller));
	}

	public function on_execute(Castellan\Event $e)
	{
		$acl        = $this->_acl_service;
		$controller = $e->target;
		$request    = $controller->request;

		$directory       = $request->directory();
		$controller_name = $request->controller();
		$action          = $request->action();
		$method          = $request->method();

		$authorized = $acl->can($this->_resource_name($directory, $controller_name))
			|| $acl->can($this->_resource_name($directory, $controller_name, $action))
			|| ($method AND $acl->can($this->_resource_name($directory, $controller_name, $method)));

		if ($authorized)
			return;

		if ($this->_attached)
		{
			$this->_castellan->trigger('controller.unauthorized', $this);
		}

		throw HTTP_Exception::factory(403, 'You are not allowed to access :resource', array(
			':resource' => $this->_resource_name($directory, $controller_name, $action),
		));
	}

	protected function _resource_name($directory, $controller, $action = NULL)
	{
		if ($directory)
		{
			$directory = str_replace('/', '_', $directory).'_';
		}

		return $this->get_resource_name($directory.$controller, $action);
	}
}