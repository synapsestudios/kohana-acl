<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * ACL Rule library
 *
 * @package    ACL
 * @author     Synapse Studios
 * @copyright  (c) 2010 Synapse Studios
 */
class Kohana_ACL_Rule {

	/**
	 * @var  type  xx
	 */
	public $auto_mode       = FALSE;

	/**
	 * @var  type  xx
	 */
	protected $directory    = '';

	/**
	 * @var  type  xx
	 */
	protected $controller   = '';

	/**
	 * @var  type  xx
	 */
	protected $action       = '';

	/**
	 * @var  type  xx
	 */
	protected $roles        = array();

	/**
	 * @var  type  xx
	 */
	protected $capabilities = array();

	/**
	 * @var  type  xx
	 */
	protected $users        = array();

	/**
	 * @var  type  xx
	 */
	protected $callbacks    = array();

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function for_directory($directory)
	{
		$this->directory = $directory;
		
		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function for_controller($controller)
	{
		$this->controller = $controller;

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function for_action($action)
	{
		$this->action = $action;

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function for_current_directory()
	{
		$this->for_directory(ACL::KEY_WILDCARD);
			
		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function for_current_controller()
	{
		$this->for_controller(ACL::KEY_WILDCARD);
			
		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function for_current_action()
	{
		$this->for_action(ACL::KEY_WILDCARD);
			
		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
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

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function allow_role($role)
	{
		// Allow for multiple roles
		$roles = func_get_args();
		
		// Add these roles to the current set
		$this->roles = array_merge($this->roles, $roles);

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function allow_capability($capability)
	{
		// Allow for multiple capabilities
		$capabilities = func_get_args();
		
		// Add these capabilities to the current set
		$this->capabilities = array_merge($this->capabilities, $capabilities);

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function allow_user($user)
	{
		// Allow for multiple users
		$users = func_get_args();

		// Add the new user IDs to the current set
		foreach ($users as $user)
		{
			// If an ID was passed it, add it
			if (is_int($user))
			{
				$this->users[] = $user;
				continue;
			}

			// If a string was passed in, get the User object
			if (is_string($user))
			{
				$user = ORM::factory('user', array('username' => $user));
			}

			// Get the ID from the User object and add it
			if ($user instanceOf Model_User AND $user->loaded())
			{
				$this->users[] = $user->id;
			}
		}

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function allow_auto()
	{
		// Make sure the controller and action are set
		if ( ! $this->controller OR ! $this->action)
			throw new ACL_Exception('You can only do allow_auto on rules where the scope for both the controller and action are defined.');
	
		$this->auto_mode = TRUE;
		
		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function auto_capability($controller, $action)
	{
		// Get capability associated with this request
		$name = strtolower(str_ireplace(
			array('{controller}', '{action}'),
			array($controller, $action),
			Kohana::config('acl.auto_format')
		));

		// Get capability object
		$capability = ORM::factory('capability', array('name' => $name));

		// If capability does not exist, throw exception
		if ( ! $capability->loaded())
			throw new ACL_Exception('The capability :name does not exist.', array(':name' => 'name'));

		// Allow the capability
		$this->allow_capability($capability->name);

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function add_callback($role, $function, array $args = array())
	{
		// If it is a valid callback, add it to the callbacks list
		if (is_callable($function))
		{
			$role = empty($role) ? ACL::CALLBACK_DEFAULT : $role;
			$this->callbacks[$role] = array
			(
				'function' => $function,
				'args'     => $args,
			);
		}

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function key()
	{
		return ACL::key($this->directory, $this->controller, $this->action);
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function valid()
	{
		// If an action is defined, a controller must also be defined
		if ( ! empty($this->action))
		{
			return ! empty($this->controller);
		}
		
		return TRUE;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function as_array()
	{
		return array
		(
			'roles'        => array_unique($this->roles),
			'capabilities' => array_unique($this->capabilities),
			'users'        => array_unique($this->users),
			'callbacks'    => $this->callbacks,
		);
	}

} // End ACL Rule