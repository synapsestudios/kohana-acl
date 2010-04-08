<?php defined('SYSPATH') OR die('No direct access allowed.');

class ACL_Rule {

	const CALLBACK_DEFAULT = '<<default>>';

	protected static function factory()
	{
		return new self;
	}
	


	protected $directory = '';

	protected $controller = '';

	protected $action = '';

	protected $roles = array();

	protected $capabilities = array();

	protected $callbacks = array();

	public function for_directory($directory)
	{
		$this->directory = $directory;
		
		return $this;
	}

	public function for_controller($controller)
	{
		$this->controller = $controller;

		return $this;
	}

	public function for_action($action)
	{
		$this->action = $action;

		return $this;
	}
	
	public function for_current()
	{
		// Get the request parts
		$parts = ACL::request_parts();

		// Setup rule from parts
		$this->for_directory($parts['directory'])
			->for_controller($parts['controller'])
		    ->for_action($parts['action']);
			
		return $this;
	}

	public function allow_all()
	{
		// Add all roles (including public)
		$this->roles[] = Kohana::config('acl.public_role');
		foreach (ORM::factory('role')->find_all() as $role)
		{
			$this->roles[] = $role->name;
		}

		return $this;
	}

	public function allow_role($role)
	{
		// Allow for multiple roles
		$roles = func_get_args();
		
		// Add these roles to the current set
		$this->roles = array_merge($this->roles, $roles);

		return $this;
	}

	public function allow_capability($capability)
	{
		// Allow for multiple capabilities
		$capabilities = func_get_args();
		
		// Add these capabilities to the current set
		$this->capabilities = array_merge($this->capabilities, $capabilities);

		return $this;
	}
	
	public function allow_auto()
	{
		// Make sure the controller and action are set
		if (empty($this->controller) OR empty($this->action))
			return $this;
	
		// Get capability associated with this request
		$name = strtolower($this->action.'-'.$this->controller);
		$capability = ORM::factory('capability', array('name' => $name));

		// If capability does not exist, throw exception
		if ( ! $capability->loaded())
			throw new Exception('The capability "'.$name.'" does not exist.');
		
		// Allow the capability
		$this->allow_capability($capability->name);
		
		return $this;
	}

	public function add_callback($role, $function, array $args = array())
	{
		// If it is a valid callback, add it to the callbacks list
		if (is_callable($function))
		{
			$role = empty($role) ? self::CALLBACK_DEFAULT : $role;
			$this->callbacks[$role] = array
			(
				'function' => $function,
				'args'     => $args,
			);
		}

		return $this;
	}

	public function key()
	{
		// Construct the key from the request parts
		return $this->directory.'|'.$this->controller.'|'.$this->action;
	}

	public function valid()
	{
		// If an action is defined, a controller must also be defined
		if ( ! empty($this->action))
		{
			return ! empty($this->controller);
		}
		
		return TRUE;
	}

	public function as_array()
	{
		return array
		(
			'roles'        => array_unique($this->roles),
			'capabilities' => array_unique($this->capabilities),
			'callbacks'    => $this->callbacks,
		);
	}

} // End ACL Rule