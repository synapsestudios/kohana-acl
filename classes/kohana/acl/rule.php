<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ACL Rule library
 *
 * @package    ACL
 * @author     Synapse Studios
 * @copyright  (c) 2010 Synapse Studios
 */
class Kohana_ACL_Rule {

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
	protected $actions      = array();

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
	 * @var  integer  Indicates how specific a rule is 
	 */
	protected $specificity  = 0;

	/**
	 * Sets the directory for which the rule applies
	 *
	 * @param   string  The directory name
	 * @return  ACL_Rule
	 */
	public function for_directory($directory)
	{
		$this->directory = $directory;

		$this->specificity++;
		
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

		$this->specificity++;

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
		// Allow for multiple actions
		$actions = func_get_args();

		$this->actions = array_merge($this->actions, $actions);

		$this->specificity++;

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
		$this->roles   = ACL::$valid_roles;
		$this->roles[] = Kohana::config('acl.public_role');

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

		// Check for invalid roles
		$invalid_roles = array_diff($roles, ACL::$valid_roles);
		if ( ! empty($invalid_roles))
			throw new Kohana_ACL_Exception ('An invalid role, :role, was added to an ACL rule.',
				array(':role' => $invalid_roles[0]));

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

		// Check for invalid capabilities
		$invalid_capabilities = array_diff($capabilities, ACL::$valid_capabilities);
		if ( ! empty($invalid_capabilities))
			throw new Kohana_ACL_Exception ('An invalid capability, :capability, was added to an ACL rule.',
				array(':capability' => $invalid_capabilities[0]));
		
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
				$username = $user;
				$user = ORM::factory('user');
				$user->where($user->unique_key($username), "=", $username)->find();
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
			throw new Kohana_ACL_Exception('An invalid callback was added to the ACL rule.');
		
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
	 * Performs a matching callback as defined in he compiled rule. It looks at
	 * all the callbacks and executes the first one that matches the user's
	 * role or the default callback if defined. Otherwise, it does nothing.
	 *
	 * @return  void
	 */
	public function perform_callback(Model_User $user)
	{
		// Loop through the callbacks
		foreach ($this->callbacks as $role => $callback)
		{
			// If the user matches the role (or it's a default), execute it
			if ($role === ACL::CALLBACK_DEFAULT OR $user->is_a($role))
			{
				call_user_func_array($callback['function'], $callback['args']);
				return;
			}
		}
	}

	/**
	 * Returns the specificity of the rule
	 *
	 * @return  integer
	 */
	public function specificity($specificity = NULL)
	{
		if (is_int($specificity))
		{
			$this->specificity = $specificity;
		}

		return $this->specificity;
	}

	/**
	 * Determines if the rule is valid
	 *
	 * @return  boolean
	 */
	public function valid()
	{
		// If an action is defined, a controller must also be defined
		if ( ! empty($this->actions))
		{
			return ! empty($this->controller);
		}
		
		return TRUE;
	}

	/**
	 * Determines whether or not the rule applies to the specified request
	 *
	 * @param   Request  The request for which to test the rule
	 * @return  boolean
	 */
	public function applies_to(Request $request)
	{
		$directory_matches  = empty($this->directory) OR $request->directory == $this->directory;
		$controller_matches = empty($this->controller) OR $request->controller == $this->controller;
		$action_matches     = empty($this->actions) OR in_array($request->action, $this->actions);

		return $directory_matches AND $controller_matches AND $action_matches;
	}

	/**
	 * Merge another rule with this one based on specificity
	 *
	 * @param   ACL_Rule  The rule to merge with this one
	 * @return  ACL_Rule
	 */
	public function merge_rule(ACL_Rule $rule)
	{
		$rule_left  = ($rule->specificity() >= $this->specificity()) ? $this : $rule;
		$rule_right = ($rule->specificity() >= $this->specificity()) ? $rule : $this;

		$rule_left->specificity(max(
			$rule_left->specificity(),
			$rule_right->specificity()
		));

		if ( ! empty($rule_right->roles))
		{
			$rule_left->roles = $rule_right->roles;
		}

		if ( ! empty($rule_right->capabilities))
		{
			$rule_left->capabilities = $rule_right->capabilities;
		}

		if ( ! empty($rule_right->users))
		{
			$rule_left->users = $rule_right->users;
		}

		return $rule_left;
	}

	/**
	 * Evaluates whether or not the user is authorized based on the rule
	 *
	 * @param   Model_User  The user that is being authorized
	 * @return  boolean
	 */
	public function authorize_user(Model_User $user)
	{
		// If the user has the super role, then allow access
		$super_role = Kohana::config('acl.super_role');
		if ($super_role AND in_array($super_role, $user->roles_list()))
			return TRUE;

		// If the user is in the user list, then allow access
		if (in_array($user->id, $this->users))
			return TRUE;

		// If the user has all (AND) the capabilities, then allow access
		$difference = array_diff($this->capabilities, $user->capabilities_list());
		if ( ! empty($this->capabilities) AND empty($difference))
			return TRUE;

		// If there were no capabilities allowed, check the roles
		if (empty($this->capabilities))
		{
			// If the user has one (OR) the roles, then allow access
			$intersection = array_intersect($this->roles, $user->roles_list());
			if ( ! empty($intersection))
				return TRUE;
		}

		return FALSE;
	}

} // End ACL Rule
