<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ACL Rule
 *
 * @package    ACL
 * @author     Synapse Studios
 * @author     Jeremy Lindblom <jeremy@synapsestudios.com>
 * @copyright  (c) 2010 Synapse Studios
 */
class Synapse_ACL_Rule implements Serializable {

	const DEFAULT_CALLBACK = '{DEFAULT}';
	const CURRENT_ACTION = '{CURRENT}';

	public static function valid_roles()
	{
		static $roles = array();

		if (empty($roles))
		{
			// Add public pseudo-role to list
			if ($public_role = ACL::config('public_role'))
			{
				$roles[] = $public_role;
			}

			// Add other roles from database
			foreach (ORM::factory('role')->find_all() as $role)
			{
				$roles[] = $role->name;
			}
		}

		return $roles;
	}

	public static function valid_capabilities()
	{
		static $capabilities = array();

		if (empty($capabilities) AND ACL::config('support_capabilities'))
		{
			foreach (ORM::factory('capability')->find_all() as $capability)
			{
				$capabilities[] = $capability->name;
			}
		}

		return $capabilities;
	}

	/**
	 * @var  boolean  TRUE if the rule needs to be resolved with a capability
	 */
	protected $_auto_mode = '';

	/**
	 * @var  string  The requested directory
	 */
	protected $_directory = '';

	/**
	 * @var  string  The requested controller
	 */
	protected $_controller = '';

	/**
	 * @var  string  The requested action
	 */
	protected $_action = array();

	/**
	 * @var  integer  Indicates how specific a rule is
	 */
	protected $_specificity = 0;

	/**
	 * @var  array  An array of all added callbacks
	 */
	protected $_callbacks = array();

	/**
	 * @var  array  An array of all added roles
	 */
	protected $_roles = array();

	/**
	 * @var  array  An array of all added capabilities
	 */
	protected $_capabilities = array();

	/**
	 * @var  array  An array of all added users
	 */
	protected $_users = array();

	/**
	 * Factory method for creating chainable instance
	 *
	 * @chainable
	 * @static
	 * @return ACL_Rule
	 */
	public static function factory()
	{
		return new ACL_Rule;
	}

	/**
	 * Sets the directory for which the rule applies
	 *
	 * @chainable
	 * @param   string  The directory name
	 * @return  ACL_Rule
	 */
	public function for_directory($directory)
	{
		$this->_directory = $directory;
		$this->_specificity += 1;
		
		return $this;
	}

	/**
	 * Sets the controller for which the rule applies
	 *
	 * @chainable
	 * @param   string  The controller name
	 * @return  ACL_Rule
	 */
	public function for_controller($controller)
	{
		$this->_controller = $controller;
		$this->_specificity += 2;

		return $this;
	}

	/**
	 * Sets the action for which the rule applies. Allows for multiple actions.
	 *
	 * @chainable
	 * @param   string  The action name
	 * @return  ACL_Rule
	 */
	public function for_action($action)
	{
		$actions = func_get_args();
		$this->_action = array_merge($this->_action, $actions);
		$this->_specificity += 3;

		return $this;
	}

	/**
	 * Add all roles to the array of allowed roles
	 *
	 * @chainable
	 * @return  ACL_Rule
	 */
	public function allow_all()
	{
		// Add all roles (including public)
		$this->_roles = ACL_Rule::valid_roles();

		return $this;
	}

	/**
	 * Add a role(s) to the array of allowed roles
	 *
	 * @chainable
	 * @param   string  The name of a role
	 * @return  ACL_Rule
	 */
	public function allow_role($role)
	{
		// Allow for multiple roles
		$roles = func_get_args();

		// Check for invalid roles
		$invalid = array_values(array_diff($roles, ACL_Rule::valid_roles()));
		if ( ! empty($invalid))
			throw new ACL_Exception('":role" is an invalid role, and cannot be added to an ACL rule.', array(':role' => $invalid[0]));

		// Add these roles to the current set
		$this->_roles = array_merge($this->_roles, $roles);

		return $this;
	}

	/**
	 * Add a capability(s) to the array of allowed capabilities
	 *
	 * @chainable
	 * @param   string  The name of a capability
	 * @return  ACL_Rule
	 */
	public function allow_capability($capability)
	{
		// Do not allow this method if capabilities are not supported
		if ( ! ACL::config('support_capabilities'))
			throw new ACL_Exception('Capabilities are not supported in this configuration of the ACL module.');

		// Allow for multiple capabilities
		$capabilities = func_get_args();

		// Check for invalid capabilities
		$invalid = array_diff($capabilities, ACL_Rule::valid_capabilities());
		if ( ! empty($invalid))
			throw new ACL_Exception('":capability" is an invalid role, and cannot be added to an ACL rule.', array(':capability' => $invalid[0]));
		
		// Add these capabilities to the current set
		$this->_capabilities = array_merge($this->_capabilities, $capabilities);

		return $this;
	}

	/**
	 * Add a user(s) to the array of allowed users. Users can be added by `id`,
	 * `Model_ACL_User` object, or unique key (usually `email`).
	 *
	 * @chainable
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
				$this->_users[] = $user;
				continue;
			}

			// If a string was passed in, get the User object
			if (is_string($user))
			{
				$username = $user;
				$user = ORM::factory('user');
				$unique = $user->unique_key($username);
				$user->where($unique, "=", $username)->find();
			}

			// Get the ID from the User object and add it
			if ($user instanceof Model_ACL_User AND $user->loaded())
			{
				$this->_users[] = $user->id;
			}
		}

		return $this;
	}

	/**
	 * Sets the rule to auto mode. The actual capability to be allowed is
	 * determined during rule compilation
	 *
	 * @chainable
	 * @return  ACL_Rule
	 */
	public function allow_auto()
	{
		// Do not allow this method if capabilities are not supported
		if ( ! ACL::config('support_capabilities'))
			throw new ACL_Exception('Capabilities are not supported in this configuration of the ACL module.');

		// Make sure the controller and action are set
		if (empty($this->_action))
		{
			$this->for_action(ACL_Rule::CURRENT_ACTION);
		}

		// Set auto mode to TRUE to the capability can be resolved later
		$this->_auto_mode = TRUE;

		return $this;
	}

	/**
	 * Add a callback to be executed when the user is not authorized
	 *
	 * @chainable
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
		$role = empty($role) ? ACL_Rule::DEFAULT_CALLBACK : $role;
		$this->_callbacks[$role] = array(
			'function' => $function,
			'args'     => $args,
		);

		return $this;
	}

	/**
	 * Returns a matching callback as defined in the compiled rule. It looks at
	 * all the callbacks and return the first one that matches the user's
	 * role or the default callback if defined. Otherwise, it returns null.
	 *
	 * @param   Model_ACL_User  The user for which to get a callback for
	 * @return  null
	 */
	public function callback_for_user(Model_ACL_User $user)
	{
		// Loop through the callbacks
		foreach ($this->_callbacks as $role => $callback)
		{
			// If the user matches the role (or it's a default), execute it
			if ($role === ACL_Rule::DEFAULT_CALLBACK OR $user->is_a($role))
				return $callback;
		}

		return NULL;
	}

	/**
	 * Resolves a rule into one or more complete rules before authorization.
	 *
	 * @param   Request  The current request
	 * @return  array   An array of resolved rules
	 */
	public function resolve_for_request(array $params)
	{
		// This will store all of the resolved rules created here
		$resolved = array();

		// Get all of the actions
		$actions = $this->_action;

		// If no actions, then copy the rule and set the action to empty
		if (empty($actions))
		{
			$rule = clone $this;
			$rule->_action = '';
			return array($rule);
		}

		// For all the actions defined, split them up into additional rule objects
		foreach ($actions as $action)
		{
			// Clone the rule, so we do not alter the original
			$rule = clone $this;

			// Set the action for this rule, and handle the special "current action" case for `allow_auto`
			$rule->_action = ($action == ACL_Rule::CURRENT_ACTION) ? Arr::get($params, 'action') : $action;

			// Set the action and resolve the capability for `allow_auto`
			$resolved[] = $rule->_resolve_capability();
		}

		return $resolved;
	}

	/**
	 * Determines whether or not the rule applies to the specified request
	 *
	 * @param   Request  The request for which to test the rule
	 * @return  boolean
	 */
	public function applies_to_request(array $params)
	{
		// Make sure the rule is valid
		if ($this->_action AND ! $this->_controller)
			return FALSE;

		// Make sure the directory matches
		if ($this->_directory AND Arr::get($params, 'directory') != $this->_directory)
			return FALSE;

		// Make sure the controller matches
		if ($this->_controller AND Arr::get($params, 'controller') != $this->_controller)
			return FALSE;

		// Make sure the action matches
		if ($this->_action AND Arr::get($params, 'action') != $this->_action)
			return FALSE;

		return TRUE;
	}

	/**
	 * Merge/Overwrite another rule with this one based on specificity, like CSS
	 *
	 * @param   ACL_Rule  The rule to merge with this one
	 * @return  ACL_Rule  The new rule, after merging
	 */
	public function merge(ACL_Rule $rule)
	{
		// Decide which rule is the more specific
		if ($rule->_specificity >= $this->_specificity)
		{
			$rule_left = $this;
			$rule_right = $rule;
		}
		else
		{
			$rule_left = $rule;
			$rule_right = $this;
		}

		// Set the new specificity (the greater of the two rules)
		$rule_left->_specificity = max($rule_left->_specificity, $rule_right->_specificity);

		// Merge roles
		if ( ! empty($rule_right->_roles))
		{
			$rule_left->_roles = $rule_right->_roles;
		}

		// Merge capabilities
		if ( ! empty($rule_right->_capabilities))
		{
			$rule_left->_capabilities = $rule_right->_capabilities;
		}

		// Merge users
		if ( ! empty($rule_right->_users))
		{
			$rule_left->_users = $rule_right->_users;
		}

		// Merge callbacks
		if ( ! empty($rule_right->_callbacks))
		{
			$rule_left->_callbacks = $rule_right->_callbacks;
		}

		return $rule_left;
	}

	/**
	 * Evaluates whether or not the user is authorized based on the rule
	 *
	 * @param   Model_ACL_User  The user that is being authorized
	 * @return  boolean
	 */
	public function user_is_authorized(Model_ACL_User $user)
	{
		// If the user has the super role, then allow access
		if (ACL::config('super_role') AND $user->is_a(ACL::config('super_role')))
			return TRUE;

		// If the user is in the user list, then allow access
		if (in_array($user->id, $this->_users))
			return TRUE;

		// If the user has all (AND) the capabilities, then allow access
		$difference = array_diff($this->_capabilities, $user->capabilities_list());
		if ( ! empty($this->_capabilities) AND empty($difference))
			return TRUE;

		// If there were no capabilities allowed, check the roles
		if (empty($this->_capabilities))
		{
			// If the user has one (OR) the roles, then allow access
			$intersection = array_intersect($this->_roles, $user->roles_list());
			if ( ! empty($intersection))
				return TRUE;
		}

		return FALSE;
	}

	public function serialize()
	{
		return serialize(array(
			$this->_auto_mode, $this->_directory, $this->_controller,
			$this->_action, $this->_specificity, $this->_callbacks,
			$this->_roles, $this->_capabilities, $this->_users,
		));
	}

	public function unserialize($serialized)
	{
		list($this->_auto_mode, $this->_directory, $this->_controller,
			$this->_action, $this->_specificity, $this->_callbacks,
			$this->_roles, $this->_capabilities, $this->_users
		) = unserialize($serialized);
	}

	/**
	 * Does `allow_capability` based on a controller and action. Used in rule
	 * compilation
	 *
	 * @chainable
	 * @return  ACL_Rule
	 */
	protected function _resolve_capability()
	{
		// Only run this method if in auto mode and capabilities are supported
		if ( ! $this->_auto_mode OR ! ACL::config('support_capabilities'))
			return $this;

		// Get capability associated with this request. Format: <action>_(<directory>_)<controller>
		$directory_part = ( ! empty($this->_directory)) ? $this->_directory.'_' : '';
		$capability_name = strtolower($this->_action.'_'.$directory_part.$this->_controller);

		// Allow the capability
		$this->allow_capability($capability_name);

		return $this;
	}

}
