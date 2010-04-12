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
	 * @var  boolean  Auto mode is `TRUE` when the `auto_allow` method is run
	 */
	public $auto_mode       = FALSE;

	/**
	 * @var  string  The requested directory
	 */
	protected $directory    = '';

	/**
	 * @var  string  The requested controller
	 */
	protected $controller   = '';

	/**
	 * @var  string  The requested action
	 */
	protected $action       = '';

	/**
	 * @var  array  An array of all added roles
	 */
	protected $roles        = array();

	/**
	 * @var  array  An array of all added capabilities
	 */
	protected $capabilities = array();

	/**
	 * @var  array  An array of all added users
	 */
	protected $users        = array();

	/**
	 * @var  array  An array of all added callbacks
	 */
	protected $callbacks    = array();

	/**
	 * Sets the directory for which the rule applies
	 *
	 * @param   string  The directory name
	 * @return  ACL_Rule
	 */
	public function for_directory($directory)
	{
		$this->directory = $directory;
		
		return $this;
	}

	/**
	 * Sets the controller for which the rule applies
	 *
	 * @param   string  The controller name
	 * @return  ACL_Rule
	 */
	public function for_controller($controller)
	{
		$this->controller = $controller;

		return $this;
	}

	/**
	 * Sets the action for which the rule applies
	 *
	 * @param   string  The action name
	 * @return  ACL_Rule
	 */
	public function for_action($action)
	{
		$this->action = $action;

		return $this;
	}

	/**
	 * Sets the directory to resolve later to the current request's
	 *
	 * @return  ACL_Rule
	 */
	public function for_current_directory()
	{
		$this->for_directory(ACL::KEY_WILDCARD);
			
		return $this;
	}

	/**
	 * Sets the controller to resolve later to the current request's
	 *
	 * @return  ACL_Rule
	 */
	public function for_current_controller()
	{
		$this->for_controller(ACL::KEY_WILDCARD);
			
		return $this;
	}

	/**
	 * Sets the action to resolve later to the current request's
	 *
	 * @return  ACL_Rule
	 */
	public function for_current_action()
	{
		$this->for_action(ACL::KEY_WILDCARD);
			
		return $this;
	}

	/**
	 * Add all roles to the array of allowed roles
	 *
	 * @return  ACL_Rule
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
	 * Add a role(s) to the array of allowed roles
	 *
	 * @param   string  The name of a role
	 * @return  ACL_Rule
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
	 * Add a capability(s) to the array of allowed capabilities
	 *
	 * @param   string  The name of a capability
	 * @return  ACL_Rule
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
	 * Add a user(s) to the array of allowed users
	 *
	 * @param   mixed  The user
	 * @return  ACL_Rule
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
	 * Sets the rule to auto mode. The actual capability to be allowed is 
	 * determined during rule compilation
	 *
	 * @return  ACL_Rule
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
	 * Does `allow_capability` based on a controller and action. Used in rule
	 * compilation
	 *
	 * @param   string  The controller's name
	 * @param   string  The action's name
	 * @return  ACL_Rule
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
			return $this; // Or should I throw the Exception?
			//throw new ACL_Exception('The capability :name does not exist.', array(':name' => $name));

		// Allow the capability
		$this->allow_capability($capability->name);

		return $this;
	}

	/**
	 * Add a callback to be executed when the user is not authorized
	 *
	 * @param   mixed     The role the callback is tied to
	 * @param   Callable  A callable function
	 * @param   array     The callback's arguments
	 * @return  ACL_Rule
	 */
	public function add_callback($role, $function, array $args = array())
	{
		// Check if the function is a valid callback
		if ( ! is_callable($function))
			throw new ACL_Exception('An invalid callback was added to the ACL rule.');
		
		// Add the callback to the callbacks list
		$role = empty($role) ? ACL::CALLBACK_DEFAULT : $role;
		$this->callbacks[$role] = array
		(
			'function' => $function,
			'args'     => $args,
		);

		return $this;
	}

	/**
	 * Calulates the key of this rule based on its scope parts
	 *
	 * @return  string
	 */
	public function key()
	{
		return ACL::key($this->directory, $this->controller, $this->action);
	}

	/**
	 * Determines if the rule is valid.
	 *
	 * @return  boolean
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
	 * Creates an array representing the rule's behavior. Used in compilation.
	 *
	 * @return  array
	 */
	public function as_array()
	{
		$array = array();
		
		if ($this->roles = array_unique($this->roles))
		{
			$array['roles'] = $this->roles;
		}
		
		if ($this->capabilities = array_unique($this->capabilities))
		{
			$array['capabilities'] = $this->capabilities;
		}
		
		if ($this->users = array_unique($this->users))
		{
			$array['users'] = $this->users;
		}
		
		if ($this->callbacks)
		{
			$array['callbacks'] = $this->callbacks;
		}
		
		return $array;
	}

} // End ACL Rule