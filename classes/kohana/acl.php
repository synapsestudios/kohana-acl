<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ACL library
 *
 * @package    ACL
 * @author     Synapse Studios
 * @copyright  (c) 2010 Synapse Studios
 */
class Kohana_ACL {

	const CALLBACK_DEFAULT = '{default}';

	/**
	 * @var  array  contains the instances (by request) of ACL
	 */
	protected static $_instances = array();

	/**
	 * @var  array  contains all the ACL rules
	 */
	protected static $_rules = array();

	public static $valid_roles = NULL;

	public static $valid_capabilities = NULL;


	/**
	 * Creates/Retrieves an instance of ACL based on the request. The first time
	 * this is called it also creates the default rule for ACL.
	 *
	 * @param   Request  The Kohana request object
	 * @return  ACL
	 */
	public static function instance(Request $request = NULL)
	{
		// Get list of all roles
		if (self::$valid_roles === NULL)
		{
			self::$valid_roles = array();
			foreach (ORM::factory('role')->find_all() as $role)
			{
				self::$valid_roles[] = $role->name;
			}
		}

		// Get list of all capabilities
		if (self::$valid_capabilities === NULL)
		{
			self::$valid_capabilities = array();
			foreach (ORM::factory('capability')->find_all() as $capability)
			{
				self::$valid_capabilities[] = $capability->name;
			}
		}

		// Get the current request, if a request was not provided
		if ($request === NULL)
		{
			$request = Request::current();
		}

		// The the current request's URI as the key for this instance
		$key = $request->uri;

		// Register the instance if it doesn't exist
		if ( ! isset(self::$_instances[$key]))
		{
			self::$_instances[$key] = new ACL($request);
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
		return self::$_rules[] = new ACL_Rule;
	}
	
//	/**
//	 * Remove all previously-added rules
//	 *
//	 * @return  void
//	 */
//	public static function clear_rules()
//	{
//		// Remove all rules
//		self::$_rules = array();
//
//		// Decompile existing rules for ACL instances
//		ACL::clear_compiled_rules();
//
//		// Re-add a default rule
//		ACL::add_rule(ACL::rule());
//	}
//
//	/**
//	 * Decompile existing rules for ACL instances
//	 *
//	 * @return  void
//	 */
//	public static function clear_compiled_rules()
//	{
//		foreach (self::$_instances as $acl)
//		{
//			$acl->initialize_rule();
//		}
//	}

//	/**
//	 * Creates a unique key from an array of 3 parts representing a rule's scope
//	 *
//	 * @param   mixed  A part or an array of scope parts
//	 * @return  string
//	 */
//	public static function key($directory, $controller = NULL, $action = NULL)
//	{
//		// Get the parts (depends on the arguments)
//		if (is_array($directory) AND count($directory) === 3)
//		{
//			$parts = $directory;
//		}
//		else
//		{
//			$parts = compact('directory', 'controller', 'action');
//		}
//
//		// Create the key
//		$key = implode(ACL::KEY_SEPARATOR, $parts);
//
//		return $key;
//	}
//
//	/**
//	 * This method resolves any wildcards in ACL rules that are created when
//	 * using the `for_current_*()` methods to the actual values from the current
//	 * request.
//	 *
//	 * @param   array  An array of the 3 scope parts
//	 * @return  void
//	 */
//	protected static function resolve_rules($scope)
//	{
//		$resolved = array();
//
//		// Loop through the rules and resolve all wildcards
//		foreach (self::$_rules as $key => $rule)
//		{
//			$rule_key = $key;
//
//			if (strpos($key, ACL::KEY_WILDCARD) !== FALSE)
//			{
//				// Separate the key into its parts
//				$parts = explode(ACL::KEY_SEPARATOR, $key);
//
//				// Resolve the directory
//				if ($parts[0] == ACL::KEY_WILDCARD)
//				{
//					$parts[0] = $scope['directory'];
//				}
//
//				// Resolve the controller
//				if ($parts[1] == ACL::KEY_WILDCARD)
//				{
//					$parts[1] = $scope['controller'];
//				}
//
//				// Resolve the action
//				if ($parts[2] == ACL::KEY_WILDCARD)
//				{
//					$parts[2] = $scope['action'];
//				}
//
//				// Put the key back together
//				$rule_key = ACL::key($parts);
//
//				// Create a key for the scope
//				$scope_key = ACL::key($scope);
//
//				// If the rule is in auto mode and it applies to the current scope, resolve the capability name
//				if ($rule->in_auto_mode() AND $rule_key === $scope_key)
//				{
//					$rule->auto_capability($scope['controller'], $scope['action']);
//				}
//			}
//
//			$resolved[$rule_key] = $rule;
//		}
//
//		// Replace the keys with the resolved ones
//		self::$_rules = $resolved;
//	}



	/**
	 * @var  Request  The request object to which this instance of ACL is for
	 */
	protected $request = NULL;

	/**
	 * @var  Model_User  The current use as retreived by the Auth module
	 */
	protected $user = NULL;

//	/**
//	 * @var  array  Contains the compiled rule that will apply to the user
//	 */
//	protected $rule = NULL;

	/**
	 * Constructs a new ACL object for a request
	 *
	 * @param   Request  The request object
	 * @return  void
	 */
	protected function __construct(Request $request)
	{
		// Store the request for this instance
		$this->request = $request;
		
		// Get the user (via Auth)
		$this->user = Auth::instance()->get_user();
		if ( ! $this->user)
		{
			$this->user = ORM::factory('user');
		}
	}

	/**
	 * This is the procedural method that executes ACL logic and responds
	 *
	 * @return  void
	 */
	public function authorize()
	{
		// Compile the rules
		$rule = $this->compile_rules();
			
		// Check if this user has access to this request
		if ($rule->authorize_user($this->user))
			return TRUE;

		// Set the HTTP status to 403 - Access Denied
		$this->request->status = 403;

		// Execute the callback (if any) from the compiled rule
		$rule->perform_callback($this->user);

		// Throw a 403 Exception if no callback has altered program flow
		throw new Kohana_ACL_Exception('You are not authorized to access this resource.', NULL, 403);
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

	protected function compile_rules()
	{

		

	}

	/**
	 * Compiles the rules based on the scope into a single rule.
	 *
	 * @return  void
	 */
	protected function old_compile_rules()
	{
		// Initialize an array for the applicable rules
		$applicable_rules = array();
		
		// Get the scope for this instance of ACL
		$scope = $this->scope();

		// Resolve rules that currently have wildcards
		ACL::resolve_rules($scope);
		
		// Re-index the scope array with numbers for looping
		$scope = array_values($scope);
		
		// Get all the rules that could apply to this request
		for ($i = 2; $i >= 0; $i--)
		{
			// Get the key for the scope
			$key = ACL::key($scope);
			
			// Look in the rules array for a rule matching the key
			if ($rule = Arr::get(self::$_rules, $key, FALSE))
			{
				$applicable_rules[$key] = $rule;
			}

			// Remove part of the scope so the next iteration can cascade to another rule
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
