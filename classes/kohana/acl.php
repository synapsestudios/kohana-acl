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
	protected static $instances = array();

	/**
	 * @var  array  contains all the ACL rules
	 */
	protected static $rules = array();

	/**
	 * @var  array  An array containing all valid role names
	 */
	public static $valid_roles = NULL;

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
	public static function rule()
	{
		// Return an ACL rule
		return self::$rules[] = new ACL_Rule;
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
	protected $user = NULL;

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
	 * Compliles the rule from all applicable rules to this request
	 *
	 * @return  ACL_Rule  The compiled rule
	 */
	protected function compile_rules()
	{
		// Find out which rules are applicable to this request
		$applicable_rules = array();
		foreach (self::$rules as $rules)
		{
			if ($rule->valid() AND $rule->applies_to($this->request))
			{
				$applicable_rules[] = $rule;
			}
		}

		// Create a blank rule
		$compiled_rule = new ACL_Rule;

		// Merge applicable rules together
		foreach ($applicable_rules as $rule)
		{
			$compiled_rule = $compiled_rule->merge_rule($rule);
		}
		
		return $compiled_rule;
	}

} // End ACL
