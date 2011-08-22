<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ACL
 *
 * @package    ACL
 * @author     Synapse Studios
 * @author     Jeremy Lindblom <jeremy@synapsestudios.com>
 * @copyright  (c) 2010 Synapse Studios
 */
class Synapse_ACL
{
	/**
	 * Factory method for creating a chainable instance
	 *
	 * @chainable
	 * @static
	 * @return  ACL
	 */
	public static function factory(ACL_Rule_List $rules)
	{
		return new ACL($rules);
	}

	public static function config($key, $default = NULL)
	{
		static $config = array();

		if (empty($config))
		{
			$config = Kohana::$config->load('acl')->as_array();
		}

		return Arr::get($config, $key, $default);
	}

	/**
	 * @var  ACL_Rule_List  The list of rules for ACL
	 */
	protected $_rules;

	/**
	 * Constructs a new ACL object for a request
	 *
	 * @param   Request  The request
	 * @return  void
	 */
	public function __construct(ACL_Rule_List $rules)
	{
		$this->_rules = $rules;
	}

	public function set_rules(ACL_Rule_List $rules)
	{
		$this->_rules = $rules;

		return $this;
	}

	public function get_rules()
	{
		return $this->_rules;
	}

	/**
	 * Checks if a user is authorized to execute the request based on the ACL rules
	 *
	 *     $rules = new ACL_Rule_List;
	 *     $user = Auth::instance()->get_user();
	 *     $request = Request::factory('account/upgrade');
	 *     $allowed = ACL::factory($rules)->is_authorized($user, $request);
	 *
	 * @param   Model_ACL_User  The user to authorize
	 * @param   ACL_Request  The request to authorize the user for
	 * @return  boolean
	 */
	public function is_authorized(Model_ACL_User $user, $params = NULL)
	{
		// Prepare request params for checking
		$params = $this->_prepare_params($params);

		// Compile the rules
		$rule = $this->_rules->compile($params);

		// Check if this user has access to this request
		return $rule->user_is_authorized($user);
	}

	/**
	 * Checks if a user is authorized to execute the request based on the ACL rules.
	 * Callbacks may be executed if authorization fails, and a 404 exception is thrown.
	 *
	 * @throws  HTTP_Exception_401 if user is not authorized
	 * @param   Model_ACL_User  The user to authorize
	 * @param   ACL_Request  The request to authorize the user for
	 * @return  ACL
	 */
	public function assert_authorized(Model_ACL_User $user, $params)
	{
		// Only run checks if the rule list has rules
		if ($this->_rules->is_empty())
			throw new ACL_Exception('No ACL rules were added to the ACL.');

		// Prepare request params for checking
		$params = $this->_prepare_params($params);

		// Compile the rules
		$rule = $this->_rules->compile($params);

		// Check if this user has access to this request
		if ( ! $rule->user_is_authorized($user))
		{
			// Execute the callback (if any) from the compiled rule
			if ($callback = $rule->callback_for_user($user))
			{
				call_user_func_array($callback['function'], $callback['args']);
			}

			// Throw a 401 exception (if the callback has altered program flow
			throw new HTTP_Exception_401('The current user is not authorized to access the requested URL.');
		}

		return $this;
	}

	protected function _prepare_params($input = NULL)
	{
		$request = NULL;
		$params = NULL;

		if (is_array($input))
		{
			// Fetch from request parameters array
			$params = Arr::extract($input, array('action', 'controller', 'directory'));
		}
		elseif ($input instanceof Request)
		{
			// Fetch from a Request object
			$request = $input;
		}
		elseif (is_string($input) AND strpos($input, '/') !== FALSE)
		{
			// Fetch from URL
			$request = Request::factory($input);
		}
		elseif ($input === NULL)
		{
			// Fetch from the current Request object
			$request = Request::current();
		}
		else
		{
			throw new ACL_Exception('ACL request params could not be determined from the provided arguments.');
		}

		if ($request instanceof Request)
		{
			$params = array(
				'action'     => $request->action(),
				'controller' => $request->controller(),
				'directory'  => $request->directory(),
			);
		}

		return $params;
	}
}
