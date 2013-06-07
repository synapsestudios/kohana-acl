<?php

class Acl_Role_ConfigProvider implements Acl_Role_ProviderInterface {

	public function __construct(array $config = array())
	{
		$roles = array();

		foreach ($config as $key => $value)
		{
			if (is_numeric($key))
			{
				$roles = array_merge($roles, $this->_load_role($value));
			}
			else
			{
				$roles = array_merge($roles, $this->_load_role($key, $value));
			}
		}

		$this->_roles = $roles;
	}

	protected function _load_role($name, $options = array(), $parent = NULL)
	{
		if (isset($options['children']) && count($options['children']) > 0)
		{
			$children = $options['children'];
		}
		else
		{
			$children = array();
		}

		$roles = array();
		$role = new Acl_Role($name, $parent);
		$roles[] = $role;

		foreach ($children as $key => $value)
		{
			if (is_numeric($key))
			{
				$roles = array_merge($roles, $this->_load_role($value, array(), $role));
			}
			else
			{
				$roles = array_merge($roles, $this->_load_role($key, $value, $role));
			}
		}

		return $roles;
	}

	public function get_roles()
	{
		return $this->_roles;
	}
}