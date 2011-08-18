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
		$rules = new ACL_Rule_List;

		$paths = array(
			'request.directory',
			'request.controller',
			'request.action',
			'allow.all',
			'allow.roles',
			'allow.capabilities',
			'allow.users',
			'allow.auto',
		);

		foreach ($array as $item)
		{
			if ( ! is_array($item))
				continue;

			$rule = new ACL_Rule;

			foreach ($paths as $path)
			{
				$data = Arr::path($item, $path, FALSE);
				if ($data === FALSE)
					continue;

				list($prefix, $suffix) = explode('.', $path, 2);
				$prefix = str_replace('request', 'for', $prefix);
				$suffix = Inflector::singular($suffix);
				$function = array($rule, $prefix.'_'.$suffix);
				if (is_array($data))
				{
					call_user_func_array($function, $data);
				}
				else
				{
					call_user_func($function, $data);
				}
			}

			foreach (Arr::get($item, 'callbacks', array()) as $role => $callback)
			{
				if ($function = Arr::get($callback, 'function'))
				{
					$args = Arr::get($callback, 'args', array());
					$rule->add_callback($role, $function, $args);
				}
			}
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
		return (bool) count($this->_rules);
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
