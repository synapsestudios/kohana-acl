<?php

use Zend\Permissions\Acl\Role\RoleInterface;

class Acl_Role implements RoleInterface {
	protected $_role_id;
	protected $_parent;

	public function __construct($role_id = NULL, $parent = NULL)
	{
		if ($role_id !== NULL)
		{
			$this->set_role_id($role_id);
		}

		if ($parent !== NULL)
		{
			$this->set_parent($parent);
		}
	}

	public function getRoleId()
	{
		return $this->_role_id;
	}

	public function get_role_id()
	{
		return $this->getRoleId();
	}

	public function set_role_id($role_id)
	{
		$this->_role_id = (string) $role_id;
		return $this;
	}

	public function get_parent()
	{
		return $this->_parent;
	}

	public function set_parent($parent)
	{
		if ($parent === NULL)
		{
			$this->_parent = NULL;
			return $this;
		}

		if (is_string($parent))
		{
			$this->_parent = new Acl_Role($parent);
			return $this;
		}

		if ($parent instanceof RoleInterface)
		{
			$this->_parent = $parent;
			return $this;
		}

		throw new InvalidArgumentException('Invalid argument for parent');
	}
}