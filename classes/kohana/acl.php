<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ACL library
 *
 * @package    ACL
 * @author     Synapse Studios
 * @copyright  (c) 2010 Synapse Studios
 */
class Kohana_ACL {

	/**
	 * @var  array  contains the instances (by request) of ACL
	 */
	protected static $instances       = array();

	/**
	 * @var  array  contains all the ACL rules
	 */
	protected static $rules           = array();

	/**
	 * @var  array  An array containing all valid role names
	 */
	public static $valid_roles        = NULL;

	/**
	 * @var  array  An array containing all valid capability names
	 */
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
		if (Kohana::config('acl.support_capabilities') AND self::$valid_capabilities === NULL)
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

		// Load rules from a file
		if (empty(self::$rules) AND Kohana::config('acl.rule_declarations'))
		{
			$path = APPPATH.Kohana::config('acl.rule_declarations').EXT;
		}

		// The the current request's URI as the key for this instance
		$key = $request->uri;

		// Register the instance if it doesn't exist
		if ( ! isset(self::$instances[$key]))
		{
			self::$instances[$key] = new ACL($request);
		}

		return self::$instances[$key];
	}


	/**
	 * Factory for an ACL rule
	 *
	 * @return  ACL_Rule
	 */
	public static function rule(ACL_Rule $rule = NULL)
	{
		if ($rule === NULL)
		{
			$rule = new ACL_Rule;
		}

		return self::$rules[] = $rule;
	}


	/**
	 * Remove all previously-added rules
	 *
	 * @return  void
	 */
	public static function clear_rules()
	{
		// Remove all rules
		self::$rules = array();
	}


	/**
	 * @var  Request  The request object to which this instance of ACL is for
	 */
	protected $request = NULL;


	/**
	 * @var  Model_User  The current use as retreived by the Auth module
	 */
	protected $user    = NULL;


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

		// Validate the request. Throws a 404 if controller or action do not exist
		$this->validate_request();
			
		// Check if this user has access to this request
		if ($rule->authorize_user($this->user))
			return TRUE;

		// Set the HTTP status to 403 - Access Denied
		$this->request->status = 403;

		// Execute the callback (if any) from the compiled rule
		$rule->perform_callback($this->user);

		// Throw an exception (403) if no callback has altered program flow
		throw new Kohana_Request_Exception('You are not authorized to access this resource.', NULL, 403);
	}


	/**
	 * Compliles the rule from all applicable rules to this request
	 *
	 * @return  ACL_Rule  The compiled rule
	 */
	protected function compile_rules()
	{
		// Create a blank, base rule
		$compiled_rule = new ACL_Rule;

		// Resolve and separate multi-action rules
		$defined_rules = self::$rules;
		foreach ($defined_rules as $rule)
		{
			$rule->resolve($this->request);
		}
		
		// Merge rules together that apply to this request
		foreach (self::$rules as $rule)
		{
			if ($rule->valid() AND $rule->applies_to($this->request))
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
	 * @throws  Kohana_Request_Exception
	 * @return  void
	 */
	public function validate_request()
	{
		// Create the class prefix
		$prefix = 'controller_';

		if ( ! empty($this->request->directory))
		{
			// Add the directory name to the class prefix
			$prefix .= str_replace(array('\\', '/'), '_', trim($this->request->directory, '/')).'_';
		}

		try
		{
			// Load the controller using reflection
			$class = new ReflectionClass($prefix.$this->request->controller);

			// Create a new instance of the controller
			$controller = $class->newInstance($this->request);

			// Determine the action to use
			$action = empty($this->request->action) 
					? Route::$default_action
					: $this->request->action;

			// See if the action exists
			$class->getMethod('action_'.$action);
		}
		catch (Exception $e)
		{
			$this->request->status = 404;

			// Throw an exception (404) if controller or action does not exist
			throw new Kohana_Request_Exception('The request was invalid. Either the controller or action involved with this request did not exist.', NULL, 404);
		}
	}

} // End ACL
