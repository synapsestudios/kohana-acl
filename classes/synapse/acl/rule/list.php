<?php defined('SYSPATH') or die('No direct script access.');
/**
 * ACL Rule List
 *
 * @package    ACL
 * @author     Synapse Studios
 * @author     Jeremy Lindblom <jeremy@synapsestudios.com>
 * @copyright  (c) 2010 Synapse Studios
 */
class Synapse_ACL_Rule_List implements Iterator, Countable, Serializable {

	public static function factory()
	{
		return new ACL_Rule_List;
	}

	public static function from_array(array $array)
	{
		// Begin the Rule List
		$rules = new ACL_Rule_List;

		// These are the valid array paths which we will look at
		$paths = array(
			'for.directory',
			'for.controller',
			'for.action',
			'allow.all',
			'allow.role',
			'allow.capability',
			'allow.user',
			'allow.auto',
		);

		// Create rules for each item in the array
		foreach ($array as $item)
		{
			// Only proceed if the
			if ( ! is_array($item))
				continue;

			// Begin the new rule
			$rule = new ACL_Rule;

			// Do all of the for_* and allow_* calls
			foreach ($paths as $path)
			{
				// Make sure the path was defined
				$data = Arr::path($item, $path, FALSE);
				if ($data === FALSE)
					continue;

				// Turn the path into a function call
				$function = array($rule, str_replace('.', '_', $path));
				if (is_array($data))
				{
					call_user_func_array($function, $data);
				}
				else
				{
					call_user_func($function, $data);
				}
			}

			// Now add any callbacks that were defined
			$callbacks = (array) Arr::get($item, 'callbacks', array());
			foreach ($callbacks as $role => $callback)
			{
				if ($function = Arr::get($callback, 'function'))
				{
					$args = Arr::get($callback, 'args', array());
					$rule->add_callback($role, $function, $args);
				}
			}

			// Add the defined rule to the rule list
			$rules->add($rule);
		}
		
		return $rules;
	}

	protected $_rules = array();

	public function add(ACL_Rule $rule)
	{
		$this->_rules[] = $rule;

		return $this;
	}

	/**
	 * Compiles the rule from all applicable rules to this request
	 *
	 * @return  ACL_Rule  The compiled rule
	 */
	public function compile(ACL_Request $request)
	{
		// Resolve and separate multi-action rules
		$resolved_rules = array();
		foreach ($this->_rules as $rule)
		{
			$resolved_rules = array_merge($resolved_rules, $rule->resolve_for_request($request));
		}

		// Create a blank, base rule to compile down to
		$compiled_rule = new ACL_Rule;

		// Merge rules together that apply to this request
		foreach ($resolved_rules as $rule)
		{
			if ($rule->applies_to_request($request))
			{
				$compiled_rule = $compiled_rule->merge($rule);
			}
		}

		return $compiled_rule;
	}

	public function as_array()
	{
		return $this->_rules;
	}

	public function count()
	{
		return count($this->_rules);
	}

	public function current()
	{
		return current($this->_rules);
	}

	public function key()
	{
		return key($this->_rules);
	}

	public function next()
	{
		next($this->_rules);

		return $this;
	}

	public function rewind()
	{
		reset($this->_rules);

		return $this;
	}

	public function valid()
	{
		return (current($this->_rules) !== FALSE);
	}

	public function is_empty()
	{
		return ! count($this->_rules);
	}

	public function clear()
	{
		$this->_rules = array();

		return $this;
	}

	public function serialize()
	{
		return serialize($this->_rules);
	}

	public function unserialize($serialized)
	{
		$this->_rules = unserialize($serialized);
	}

}
