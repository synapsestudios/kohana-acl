<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ACL library
 *
 * @package    ACL
 * @author     Synapse Studios
 * @author     Jeremy Lindblom <jeremy@synapsestudios.com>
 * @copyright  (c) 2010 Synapse Studios
 */
class Kohana_ACL {

	/**
	 * @var  array  contains the instances (by request) of ACL
	 */
	protected static $_instances = array();

	/**
	 * @var  array  contains all the ACL rules
	 */
	protected static $_rules     = array();

	/**
	 * @var  array  An array containing all valid items
	 */
	public static $valid         = NULL;


	/**
	 * Creates/Retrieves an instance of ACL based on a request. 
	 *
	 * @param   mixed  The Request object, or an array of request parts
	 * @return  ACL
	 */
	public static function instance($parts = NULL)
	{
		// Initialize the $valid array
		self::_initialize_valid_items();

		// Get the request parts, if they were not provided
		if ($parts === NULL)
		{
			$request = Request::current();
			$parts = array
			(
				'directory'  => $request->directory,
				'controller' => $request->controller,
				'action'     => $request->action,
			);
		}
		else
		{
			$parts = Arr::extract($parts, array('directory', 'controller', 'action'));
		}

		// Use the imploded request parts as the key for this instance
		$key = implode('/', $parts);

		// Register the instance if it doesn't exist
		if ( ! isset(self::$_instances[$key]))
		{
			self::$_instances[$key] = new ACL($parts);
		}

		return self::$_instances[$key];
	}


	/**
	 * Factory for an ACL rule. Stores it in the rules array, automatically.
	 *
	 * @return  ACL_Rule
	 */
	public static function rule(ACL_Rule $rule = NULL)
	{
		// Initialize the $valid array
		self::_initialize_valid_items();

		// If no rule provided, use a new, blank one
		if ($rule === NULL)
		{
			$rule = new ACL_Rule;
		}

		// Return the rule after storing in the rules array
		return self::$_rules[] = $rule;
	}


	/**
	 * Remove all previously-added rules
	 *
	 * @return  void
	 */
	public static function clear()
	{
		// Remove all rules
		self::$_rules = array();
	}


	/**
	 * Initializes the `$valid` arrays for roles and capabilities.
	 *
	 * @return  void
	 */
	protected static function _initialize_valid_items()
	{
		// Get list of all valid items
		if (self::$valid === NULL)
		{
			// Setup the array
			self::$valid = array();

			// Get the valid roles
			self::$valid['roles'] = array();

			if ($public_role = Kohana::config('acl.public_role'))
			{
				self::$valid['roles'][] = $public_role;
			}

			foreach (ORM::factory('role')->find_all() as $role)
			{
				self::$valid['roles'][] = $role->name;
			}

			// Get the valid capabilities
			if (Kohana::config('acl.support_capabilities'))
			{
				self::$valid['capabilities'] = array();
				foreach (ORM::factory('capability')->find_all() as $capability)
				{
					self::$valid['capabilities'][] = $capability->name;
				}
			}
		}
	}


	/**
	 * @var  array  The request object to which this instance of ACL is for
	 */
	protected $_parts = NULL;


	/**
	 * @var  Model_User  The current use as retreived by the Auth module
	 */
	protected $_user  = NULL;


	/**
	 * Constructs a new ACL object for a request
	 *
	 * @param   array  The request parts
	 * @return  void
	 */
	protected function __construct(array $parts)
	{
		// Store the request for this instance
		$this->_parts = $parts;
		
		// Get the user (via Auth)
		$this->_user = Auth::instance()->get_user() ?: ORM::factory('user');
	}


	/**
	 * Check if a user is allowed to the request based on the ACL rules
	 *
	 *     $uri_parts = array('controller' => 'account', 'action' => 'update');
	 *     $allowed   = ACL::instance($uri_parts)->allows_user($user);
	 *
	 * @param   Model_User  The user to allow
	 * @return  boolean
	 */
	public function allows_user(Model_User $user = NULL)
	{
		// Use the object's user, unless another is provided
		$user = $user ?: $this->_user;

		// Compile the rules
		$rule = $this->_compile_rules();

		// Check if this user has access to this request
		return $rule->allows_user($user);
	}


	/**
	 * This is the procedural method that executes ACL logic and responses
	 *
	 * @return  boolean
	 * @throws  Kohana_Request_Exception
	 * @uses    ACL::allows_user
	 * @uses    ACL::verify_request
	 */
	public function authorize()
	{
		// Initialize request if this hasn't happened yet
		Request::instance();

		// Current request
		$request = Request::current();

		// Validate the request. Throws a 404 if controller or action do not exist
		$this->verify_request($request);
			
		// Check if this user has access to this request
		if ( ! empty(self::$_rules) && $this->allows_user($this->_user))
			return TRUE;

		// Set the HTTP status to 403 - Access Denied
		$request->status = 403;

		// Execute the callback (if any) from the compiled rule
		$rule->perform_callback($this->_user);

		// Throw an exception (403) if no callback has altered program flow
		throw new Kohana_Request_Exception('You are not authorized to access this resource.', NULL, 403);
	}


	/**
	 * Compliles the rule from all applicable rules to this request
	 *
	 * @return  ACL_Rule  The compiled rule
	 */
	protected function _compile_rules()
	{
		// Create a blank, base rule
		$compiled_rule = new ACL_Rule;

		// Resolve and separate multi-action rules
		$defined_rules = self::$_rules;
		foreach ($defined_rules as $rule)
		{
			$rule->resolve($this->_parts);
		}
		
		// Merge rules together that apply to this request
		foreach (self::$_rules as $rule)
		{
			if ($rule->valid() AND $rule->applies_to($this->_parts))
			{
				$compiled_rule = $compiled_rule->merge($rule);
			}
		}
		
		return $compiled_rule;
	}


	/**
	 * This method compensates for the poor organization of the Request
	 * execution of Kohana. Much of this code was directly copied from the
	 * Request::execution method and is used to check if a controller and action
	 * exist before trying to authorize a user for the request.
	 *
	 * @return  void
	 * @throws  Kohana_Request_Exception
	 */
	public function verify_request(Request $request)
	{
		// Create the class prefix
		$prefix = 'controller_';

		if ( ! empty($request->directory))
		{
			// Add the directory name to the class prefix
			$prefix .= str_replace(array('\\', '/'), '_', trim($request->directory, '/')).'_';
		}

		try
		{
			// Load the controller using reflection
			$class = new ReflectionClass($prefix.$request->controller);

			// Create a new instance of the controller
			$controller = $class->newInstance($request);

			// Determine the action to use
			$action = $request->action ?: Route::$default_action;

			// See if the action exists
			$class->getMethod('action_'.$action);
		}
		catch (Exception $e)
		{
			$request->status = 404;

			// Throw an exception (404) if controller or action does not exist
			throw new Kohana_Request_Exception('The request was invalid. Either the controller or action involved with this request did not exist.', NULL, 404);
		}
	}

} // End ACL
