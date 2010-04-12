<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * ACL library
 *
 * @package    ACL
 * @author     Synapse Studios
 * @copyright  (c) 2010 Synapse Studios
 */
class Kohana_ACL {

	const CALLBACK_DEFAULT = '{default}';
	const KEY_WILDCARD = '?';
	const KEY_SEPARATOR = '|';

	/**
	 * @var  array  contains the instances (by request) of ACL
	 */
	protected static $_instances = array();

	/**
	 * @var  array  contains all the ACL rules
	 */
	protected static $_rules = array();

	/**
	 * Creates/Retrieves an instance of ACL based on the request. The first time
	 * this is called it also creates the default rule for ACL.
	 *
	 * @param   Request  The Kohana request object
	 * @return  ACL
	 */
	public static function instance(Request $request = NULL)
	{
		// Set the default rule when creating the first instance
		if ( ! isset(self::$_rules[ACL::KEY_SEPARATOR.ACL::KEY_SEPARATOR]))
		{
			// Create a default rule
			$default_rule = ACL::rule();

			// Set the callback for the default rule
			if ($callback = Kohana::config('acl.default_callback'))
			{
				$default_rule->add_callback(NULL, $callback['function'], $callback['args']);
			}

			// Add the default rule
			ACL::add_rule($default_rule);
		}

		// If no request was specified, then use the current, main request
		if ($request === NULL)
		{
			$request = Request::instance();
		}

		// Find the key for this request
		$key = ACL::key($request->directory, $request->controller, $request->action);
		// @todo should probably factor in whether or not this is a subrequest

		// Register the instance if it doesn't exist
		if ( ! isset(self::$_instances[$key]))
		{
			self::$_instances[$key] = new self($request);
		}

		return self::$_instances[$key];
	}

	/**
	 * Factory for an ACL rule
	 *
	 * @return  ACL_Rule
	 */
	public static function rule()
	{
		// Return an ACL rule
		return new ACL_Rule;
	}

	/**
	 * Validates and adds an ACL_Rule to the rules array
	 *
	 * @param   ACL_Rule  The rule to add
	 * @return  void
	 */
	public static function add_rule(ACL_Rule $rule)
	{
		// Check if the rule is valid, if not throw an exception
		if ( ! $rule->valid())
			throw new ACL_Exception('The ACL Rule was invalid and could not be added.');

		// Find the rule's key and add it to the array of rules
		$key = $rule->key();
		self::$_rules[$key] = $rule;
	}

	/**
	 * Creates a unique key from an array of 3 parts representing a rule's scope
	 *
	 * @param   mixed  A part or an array of scope parts
	 * @return  string
	 */
	public static function key($parts)
	{
		// Make sure the arguments are correct for calculating the key
		if ( ! is_array($parts) OR count($parts) !== 3)
		{
			$parts = func_get_args();
			if (count($parts) !== 3)
				throw new InvalidArgumentException(__('The ACL::key() method requires exactly 3 parts.'));
		}

		// Create the key
		$key = implode(ACL::KEY_SEPARATOR, $parts);

		return $key;
	}

	/**
	 * This method resolves any wildcards in ACL rules that are created when
	 * using the `for_current_*()` methods to the actual values from the current
	 * request.
	 *
	 * @param   array  An array of the 3 scope parts
	 * @return  void
	 */
	protected static function resolve_rules($scope)
	{
		$resolved = array();

		// Loop through the rules and resolve all wildcards
		foreach (self::$_rules as $key => $rule)
		{
			if (strpos($key, ACL::KEY_WILDCARD) !== FALSE)
			{
				// Separate the key into its parts
				$parts = explode(ACL::KEY_SEPARATOR, $key);

				// Resolve the directory
				if ($parts[0] == ACL::KEY_WILDCARD)
				{
					$parts[0] = $scope['directory'];
				}

				// Resolve the controller
				if ($parts[1] == ACL::KEY_WILDCARD)
				{
					$parts[1] = $scope['controller'];
				}

				// Resolve the action
				if ($parts[2] == ACL::KEY_WILDCARD)
				{
					$parts[2] = $scope['action'];
				}

				// Put the key back together
				$key = ACL::key($parts);
			}

			// If in auto mode (`allow_auto()`), resolve the capability name
			if ($rule->auto_mode)
			{
				$rule->auto_capability($scope['controller'], $scope['action']);
			}

			$resolved[$key] = $rule;
		}

		// Replace the keys with the resolved ones
		self::$_rules = $resolved;
	}

	/**
	 * A static form of the Request's redirect method for use as a callback
	 *
	 * @param   string  The url to redirect to
	 * @return  void
	 */
	public static function redirect($url = NULL)
	{
		Request::instance()->redirect($url);
	}



	/**
	 * @var  Request  The request object to which this instance of ACL is for
	 */
	protected $request = NULL;

	/**
	 * @var  Model_User  The current use as retreived by the Auth module
	 */
	protected $user = NULL;

	/**
	 * @var  array  Contains the compiled rule that will apply to the user
	 */
	protected $rule = NULL;

	/**
	 * Constructs a new ACL object for a request
	 *
	 * @param   Request  The request object
	 * @return  void
	 */
	protected function __construct(Request $request)
	{
		$this->request = $request;
		
		$this->user    = Auth::instance()->get_user();
		if ( ! $this->user)
		{
			$this->user = ORM::factory('user');
		}

		$this->rule    = array
		(
			'roles'        => array(),
			'capabilities' => array(),
			'users'        => array(),
			'callbacks'    => array(),
		);
	}

	/**
	 * Returns the "scope" of this request. These values help determine which
	 * ACL applies to the user
	 *
	 * @return  array
	 */
	public function scope()
	{
		return array
		(
			'directory'  => $this->request->directory,
			'controller' => $this->request->controller,
			'action'     => $this->request->action,
		);
	}

	/**
	 * This is the procedural method that executes ACL logic and responds
	 *
	 * @return  void
	 */
	public function authorize()
	{
		// Compile the rules
		$this->compile();
			
		// Check if this user has access to this request
		if ($this->user_authorized())
			return TRUE;

		// Set the HTTP status to 403 - Access Denied
		$this->request->status = 403;

		// Execute the callback (if any) from the compiled rule
		$this->perform_callback();

		// Throw a 403 Exception if no callback has altered program flow
		throw new Kohana_Request_Exception('You are not authorized to access this resource.', NULL, 403);
	}

	/**
	 * Determines if a user is authorized based on the compiled rule. It
	 * examines things in the following order:
     *
	 * 1. Does the user have the super role?
	 * 2. Is the user's ID in the allow list?
	 * 3. Does the user have all of the required capabilities?
	 * 4. Does the user have at least one of the required roles?
	 *
	 * @return  boolean
	 */
	protected function user_authorized()
	{
		// If the user has the super role, then allow access
		$super_role = Kohana::config('acl.super_role');
		if ($super_role AND in_array($super_role, $this->user->roles_list()))
			return TRUE;
		// If the user is in the user list, then allow access
		if (in_array($this->user->id, $this->rule['users']))
			return TRUE;
			
		// If the user has all (AND) the capabilities, then allow access
		$difference = array_diff($this->rule['capabilities'], $this->user->capabilities_list());
		if ( ! empty($this->rule['capabilities']) AND empty($difference))
			return TRUE;

		// If there were no capabilities allowed, check the roles
		if (empty($this->rule['capabilities']))
		{
			// If the user has one (OR) the roles, then allow access
			$intersection = array_intersect($this->rule['roles'], $this->user->roles_list());
			if ( ! empty($intersection))
				return TRUE;
		}
		
		return FALSE;
	}

	/**
	 * Performs a matching callback as defined in he compiled rule. It looks at
	 * all the callbacks and executes the first one that matches the user's
	 * role or the default callback if defined. Otherwise, it does nothing.
	 *
	 * @return  void
	 */
	protected function perform_callback()
	{		
		// Loop through the callbacks
		foreach ($this->rule['callbacks'] as $role => $callback)
		{
			// If the user matches the role (or it's a default), execute it
			if ($role === ACL::CALLBACK_DEFAULT OR $this->user->is_a($role))
			{
				call_user_func_array($callback['function'], $callback['args']);
				return;
			}
		}
	}

	/**
	 * Compiles the rules based on the scope into a single rule.
	 *
	 * @return  void
	 */
	protected function compile()
	{
		// Initialize an array for the applicable rules
		$applicable_rules = array();
		
		// Get the scope for this instance of ACL
		$scope = $this->scope();

		// Resolve rules that currently have wildcards
		ACL::resolve_rules($scope);
		
		// Get all the rules that could apply to this request
		$scope = array_values($scope);
		for ($i=2; $i>=0; $i--)
		{
			$key = ACL::key($scope);
			
			if ($rule = Arr::get(self::$_rules, $key, FALSE))
			{
				$applicable_rules[$key] = $rule;
			}

			$scope[$i] = '';
		}
		
		// Get default rule
		$default_key = ACL::KEY_SEPARATOR.ACL::KEY_SEPARATOR;
		$applicable_rules[$default_key] = Arr::get(self::$_rules, $default_key);

		// Reverse the rules. Compile from the bottom up
		$applicable_rules = array_reverse($applicable_rules);

		// Compile the rule
		foreach ($applicable_rules as $rule)
		{
			$this->rule = Arr::overwrite($this->rule, $rule->as_array());
		}
	}

} // End ACL