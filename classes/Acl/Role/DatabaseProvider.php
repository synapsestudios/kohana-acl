<?php

class Acl_Role_DatabaseProvider implements Acl_Role_ProviderInterface {

	protected $_database;

	protected $_table_name = 'acl_roles';

	protected $_role_id_field = 'role_id';

	protected $_parent_role_field = 'parent';

	public function __construct(Database $db, $options = array())
	{
		$this->_database   = $db;
		$table             = Arr::get($options, 'table_name');
		$role_id_field     = Arr::get($options, 'role_id_field');
		$parent_role_field = Arr::get($options, 'parent_role_field');

		if ($table !== NULL)
		{
			$this->_table_name = $table;
		}

		if ($role_id_field !== NULL)
		{
			$this->_role_id_field = $role_id_field;
		}

		if ($parent_role_field !== NULL)
		{
			$this->_parent_role_field = $parent_role_field;
		}
	}

	public function getRoles()
	{
		$result = $this->_database->select()
			->from($this->_table_name)
			->execute();

		$roles = array();

		// Round one: build each role object
		foreach ($result as $row)
		{
			$role_id = $row[ $this->_role_id_field ];
			$roles[ $role_id ] = new Acl_Role($role_id, $row[ $this->_parent_role_field ]);
		}

		// Round two: re-inject parent objects to preserve hierarchy
		foreach ($roles as $role)
		{
			$parent = $role->get_parent();

			if ($parent AND $parent->get_role_id())
			{
				$role->set_parent($roles[$parent->get_role_id()]);
			}
		}

		return array_values($roles);
	}
}