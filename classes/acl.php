<?php defined('SYSPATH') OR die('No direct access allowed.');

class ACL {

	protected static $_instance = NULL;

	public static function instance()
	{
		if (is_null(self::$_instance))
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public static function request_parts()
	{
		static $parts = array();

		if (empty($parts))
		{
			// Get route parts from Request object
			$request = Request::instance();
			$parts = array
			(
				'directory'  => $request->directory,
				'controller' => $request->controller,
				'action'     => $request->action,
			);
		}

		return $parts;
	}
	
	// Some built-in common callbacks

	public static function redirect($url = NULL)
	{
		Request::instance()->redirect($url);
	}



	protected $user;
	
	protected $ruleset;

	protected function __construct()
	{
		// Set the member variables
		$this->user    = Auth::instance()->get_user() ?: ORM::factory('user');
		$this->ruleset = ACL_Ruleset::factory();
	}

	public function add_rule(ACL_Rule $rule)
	{
		// Add rule to ruleset
		$this->ruleset->add($rule);

		return $this;
	}
	
	public function import_rules($file = NULL)
	{
		// If no file, use the default
		if ( ! $file)
		{
			$file =  APPPATH.'acl'.EXT;
		}
		
		// If relative path used, prepend with APPPATH
		if (strpos($file, APPPATH) !== 0)
		{
			$file =  APPPATH.ltrim($file, '/');
		}
		
		// Include the file of ACL rules
		$acl = $this;
		require $file;
		
		return $this;
	}

	public function control_access()
	{
		// Check if this user has access to this request
		if ($this->ruleset->allows($this->user))
			return TRUE;

		// Set the HTTP status to 403 - Access Denied
		Request::instance()->status = 403;

		// Execute the callback (if any) from the ruleset
		$this->ruleset->do_callback_for($this->user);

		// Throw a 403 Exception if no callback has altered program flow
		throw new Kohana_Request_Exception('You are not authorized to access this resource.', NULL, 403);
	}

} // End ACL