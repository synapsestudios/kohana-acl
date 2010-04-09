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
	 * @var  type  xx
	 */
	protected static $_instances = array();

	/**
	 * @var  type  xx
	 */
	protected static $_rules = array();

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public static function instance(Request $request = NULL)
	{
		// Set the default rule when creating the first instance
		if (empty(self::$_rules))
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

		// Register the instance if it doesn't exist
		if (is_null(self::$_instances[$key]))
		{
			self::$_instances[$key] = new self($request);
		}

		return self::$_instances[$key];
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public static function rule()
	{
		// Return an ACL rule
		return new ACL_Rule;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
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
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
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
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
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

			if ($rule->auto_mode)
			{
				$rule->auto_capability($scope['controller'], $scope['action']);
			}

			$resolved[$key] = $rule;
		}

		// Relace the keys with the resolved ones
		self::$_rules = $resolved;
	}

	// Some built-in common callbacks

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public static function redirect($url = NULL)
	{
		Request::instance()->redirect($url);
	}



	/**
	 * @var  type  xx
	 */
	protected $request = NULL;

	/**
	 * @var  type  xx
	 */
	protected $user = NULL;

	/**
	 * @var  type  xx
	 */
	protected $rule = NULL;

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
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
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
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
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function authorize()
	{
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
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	protected function user_authorized()
	{
		// Compile the ruleset
		$this->compile();

		// If the user has the super role, then allow access
		$super_role = Kohana::config('acl.super_role');
		if ($super_role AND in_array($super_role, $user->roles_list()))
			return TRUE;

		// If the user is in the user list, then allow access
		if (in_array($this->user->id, $this->rule['users']))
			return TRUE;

		// If the user has all (AND) the capabilities, then allow access
		$difference = array_diff($this->rule['capabilities'], $user->capabilties_list());
		if (empty($difference))
			return TRUE;

		// If there were no capabilities allowed, check the roles
		if (empty($this->rule['capabilities']))
		{
			// If the user has one (OR) the roles, then allow access
			$intersection = array_intersect($this->rule['roles'], $user->roles_list());
			if ( ! empty($intersection))
				return TRUE;
		}

		return FALSE;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	protected function perform_callback(Model_User $user)
	{
		// Loop through the callbacks
		foreach ($this->rule['callbacks'] as $role => $callback)
		{
			// If the user matches the role (or it's a default), execute it
			if ($user->is_a($role) OR $role === ACL::CALLBACK_DEFAULT)
			{
				call_user_func_array($callback['function'], $callback['args']);
				return;
			}
		}
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	protected function compile()
	{
		$applicable_rules = array();
		$scope = $this->scope();

		ACL::resolve_rules($scope);

		// Get all the rules that could apply to this request
		while ( ! empty($scope))
		{
			$key = ACL::key($scope);
			if ($rule = Arr::get(self::$_rules, $key, FALSE))
			{
				$applicable_rules[] = $rule;
			}

			array_pop($scope);
		}

		// Get default rule
		$applicable_rules[] = Arr::get($this->rules, ACL::KEY_SEPARATOR.ACL::KEY_SEPARATOR);

		// Reverse the rules. Start with the default and go up
		$applicable_rules = array_reverse($applicable_rules);

		// Compile the rule
		foreach ($applicable_rules as $rule)
		{
			$this->rule = Arr::overwrite($this->rule, $rule->as_array());
		}
	}

} // End ACL



// Create ACL exception class
class ACL_Exception extends Kohana_Exception {}