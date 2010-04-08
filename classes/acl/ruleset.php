<?php defined('SYSPATH') OR die('No direct access allowed.');

class ACL_Ruleset {

	protected static function create()
	{
		// Create the ruleset
		$ruleset = new self;
		
		// Create a default rule
		$default_rule = ACL::rule();

		// Set the callback for the default rule
		if ($callback = Kohana::config('acl.default_callback'))
		{
			$default_rule->add_callback(NULL, $callback['function'], $callback['args']);
		}

		// Add the default rule to the ruleset
		$ruleset->add($default_rule);
		
		return $ruleset;
	}
	
	
	
	protected $rules = array();

	protected $roles = array();

	protected $capabilities = array();

	protected $callbacks = array();
	
	public function add(ACL_Rule $rule)
	{
		// Check if the rule is valid, if not throw an exception
		if ( ! $rule->valid())
			throw new Kohana_Exception('The ACL Rule was invalid and could not be added.');

		// Find the rule's and add it to the array of rules
		$key = $rule->key();
		$this->rules[$key] = $rule;

		return $this;
	}

	public function allows(Model_User $user)
	{
		// Compile the ruleset
		$this->compile();
	
		// If the user has the super role, then allow access
		$super_role = Kohana::config('acl.super_role');
		if ($super_role AND in_array($super_role, $user->roles_list()))
			return TRUE;

		// If the user has all (AND) the capabilities, then allow access
		$difference = array_diff($this->capabilities, $user->capabilties_list());
		if (empty($difference))
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

	public function do_callback_for(Model_User $user)
	{
		// Loop through the callbacks
		foreach ($this->callbacks as $role => $callback)
		{
			// If the user matches the role (or it's a default), execute it
			if ($user->is_a($role) OR $role === ACL_Rule::CALLBACK_DEFAULT)
			{
				call_user_func_array($callback['function'], $callback['args']);
				return;
			}
		}
	}
	
	protected function compile()
	{
		$rules = array();
		$parts = ACL::request_parts();

		// Get all the rules that could apply to this request
		while ( ! empty($parts))
		{
			$key = implode('|', $parts);
			if ($rule = Arr::get($this->rules, $key, FALSE))
			{
				$rules[] = $rule;
			}

			array_pop($parts);
		}

		// Get default rule
		$rules[] = Arr::get($this->rules, '||');

		// Reverse the rules. Start with the default and go up
		$rules = array_reverse($rules);

		// Construct a default array
		$compiled_rule = array
		(
			'roles'        => array(),
			'capabilities' => array(),
			'callbacks'    => array(),
		);

		// Compile the rules
		foreach ($rules as $rule)
		{
			$compiled_rule = Arr::overwrite($compiled_rule, $rule->as_array());
		}

		// Set the results
		$this->roles        = $compiled_rule['roles'];
		$this->capabilities = $compiled_rule['capabilities'];
		$this->callbacks    = $compiled_rule['callbacks'];
	}

} // End ACL Ruleset