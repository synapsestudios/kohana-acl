<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ACL Rule library
 *
 * @package    ACL
 * @author     Synapse Studios
 * @copyright  (c) 2010 Synapse Studios
 */
class Kohana_ACL_Rule {

	const DEFAULT_CALLBACK = '{DEFAULT}';
	const CURRENT_ACTION   = '{CURRENT}';

	/**
	 * @var  boolean  TRUE if the rule needs to be resolved with a capability
	 */
	protected $auto_mode = '';

	/**
	 * @var  string  The requested directory
	 */
	protected $directory = '';

	/**
	 * @var  string  The requested controller
	 */
	protected $controller = '';

	/**
	 * @var  string  The requested action
	 */
	protected $action = array();

	/**
	 * @var  array  An array of all added callbacks
	 */
	protected $callbacks = array();

	/**
	 * @var  array  An array of all added roles
	 */
	public $roles = array();

	/**
	 * @var  array  An array of all added capabilities
	 */
	public $capabilities = array();

	/**
	 * @var  array  An array of all added users
	 */
	public $users = array();

	/**
	 * @var  integer  Indicates how specific a rule is 
	 */
	public $specificity = 0;


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

		$this->action = array_merge($this->action, $actions);

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
		$this->roles = ACL::$valid['roles'];

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
		$invalid = array_diff($roles, ACL::$valid['roles']);
		if ( ! empty($invalid))
			throw new Kohana_ACL_Exception ('An invalid role, :role, was added to an ACL rule.',
				array(':role' => $invalid[0]));

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
		// Do not allow this method if capabilities are not supported
		if (Kohana::config('acl.support_capabilities') === FALSE)
			throw new Kohana_ACL_Exception('Capabilities are not supported in this configuration of the ACL module.');

		// Allow for multiple capabilities
		$capabilities = func_get_args();

		// Check for invalid capabilities
		$invalid = array_diff($capabilities, ACL::$valid['capabilities']);
		if ( ! empty($invalid))
			throw new Kohana_ACL_Exception ('An invalid capability, :capability, was added to an ACL rule.',
				array(':capability' => $invalid[0]));
		
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
				$user     = ORM::factory('user');
				$unique   = $user->unique_key($username);
				$user->where($unique, "=", $username)->find();
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
		// Do not allow this method if capabilities are not supported
		if (Kohana::config('acl.support_capabilities') === FALSE)
			throw new Kohana_ACL_Exception ('Capabilities are not supported in this configuration of the ACL module.');

		// Make sure the controller and action are set
		if (empty($this->action))
		{
			$this->for_action(ACL_Rule::CURRENT_ACTION);
		}

		// Set auto mode to TRUE to the capability can be resolved later
		$this->auto_mode = TRUE;

		return $this;
	}


	/**
	 * Does `allow_capability` based on a controller and action. Used in rule
	 * compilation
	 *
	 * @return  ACL_Rule
	 */
	protected function resolve_capability()
	{
		// Only run this method if in auto mode and capabilities are supported
		if ( ! $this->auto_mode OR Kohana::config('acl.support_capabilities') === FALSE)
			return;

		// Get capability associated with this request
		$capability_name = strtolower(str_ireplace(
			array('{controller}', '{action}'),
			array($this->controller, $this->action),
			Kohana::config('acl.auto_format')
		));

		// Allow the capability
		$this->allow_capability($capability_name);

		return $this;
	}


	/**
	 * Sets the action of a rule
	 *
	 * @param  string  The action for this rule to be set to
	 */
	public function set_action($action)
	{
		$this->action = $action;

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
		$role = empty($role) ? ACL_Rule::DEFAULT_CALLBACK : $role;
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
			if ($role === ACL_Rule::DEFAULT_CALLBACK OR $user->is_a($role))
			{
				call_user_func_array($callback['function'], $callback['args']);
				return;
			}
		}
	}

	/**
	 * Resolves a rule to its correct values befire authorization
	 *
	 * @param   Request  The current request
	 * @return  void
	 */
	public function resolve(Request $request)
	{
		// Get all of the actions
		$actions = $this->action;

		// If no actions, then set to empty and be done
		if (empty($actions))
		{
			$this->set_action('');
			return;
		}

		// Pop the first action off the array and set it
		$this->set_action(array_shift($actions));

		// Handle the special "cuurent action" case for `allow_auto`
		if ($this->action == ACL_Rule::CURRENT_ACTION)
		{
			$this->set_action($request->action);
		}

		// Resolve capability for `allow_auto`
		$this->resolve_capability();

		// For all additional rules, create new rule objects and set the action
		foreach ($actions as $action)
		{
			// Clone the original rule
			$rule = clone $this;

			// Set the action
			$rule->set_action($action);

			// Resolve capability for `allow_auto`
			$rule->resolve_capability();

			// Add this rule to the ACL rule definitions
			ACL::rule($rule);
		}
	}


	/**
	 * Determines if the rule is valid
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
	 * Determines whether or not the rule applies to the specified request
	 *
	 * @param   Request  The request for which to test the rule
	 * @return  boolean
	 */
	public function applies_to(array $parts)
	{
		$directory_matches  = (empty($this->directory) OR $parts['directory'] == $this->directory);
		$controller_matches = (empty($this->controller) OR $parts['controller'] == $this->controller);
		$action_matches     = (empty($this->action) OR $parts['action'] == $this->action);

		return (bool) ($directory_matches AND $controller_matches AND $action_matches);
	}


	/**
	 * Merge another rule with this one based on specificity
	 *
	 * @param   ACL_Rule  The rule to merge with this one
	 * @return  ACL_Rule
	 */
	public function merge(ACL_Rule $rule)
	{
		$rule_left  = ($rule->specificity >= $this->specificity) ? $this : $rule;
		$rule_right = ($rule->specificity >= $this->specificity) ? $rule : $this;

		$rule_left->specificity = max($rule_left->specificity, $rule_right->specificity);

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
