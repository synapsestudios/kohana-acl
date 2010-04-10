<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Acl_User extends Model_Auth_User {

	// Relationships
	
	protected $_has_many = array
	(
		'user_tokens'  => array('model' => 'user_token'),
		'roles'        => array('model' => 'role', 'through' => 'roles_users'),
		'capabilities' => array('model' => 'capability', 'through' => 'capabilities_users'),
	);
	
	// ACL-related methods

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function is_a($role)
	{
		// Get role object
		if ( ! $role instanceOf Model_Role)
		{
			$role = ORM::factory('role', array('name' => $role));
		}
		
		// If object failed to load then throw exception
		if ( ! $role->loaded())
			throw new ACL_Exception('Tried to check for a role that did not exist.');

		// Return whether or not they have the role
		return $user->has('role', $role);
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function can($capability)
	{
		// If the user has the super role, they can!
		$super_role = Kohana::config('acl.super_role');
		if ($super_role AND $user->is_a($super_role))
			return TRUE;
	
		// Get capability object
		if ( ! $capability instanceOf Model_Capability)
		{
			$capability = ORM::factory('capability', array('name' => $capability));
		}
		
		// If object failed to load then throw exception
		if ( ! $capability->loaded())
			throw new Exception('Tried to check for a capability that did not exist.');

		// Return whether or not they have access
		return $user->has('capability', $capability);
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function assign_role($role)
	{
		// Get role object
		if ( ! $role instanceOf Model_Role)
		{
			$role = ORM::factory('role', array('name' => $role));
		}

		// If object failed to load then throw exception
		if ( ! $role->loaded())
			throw new ACL_Exception('Tried to assign a role that did not exist.');

		// Add the role to the user
		$this->add('roles', $role);
		$this->save();

		// Add all of the capabilities associated with the role
		foreach ($role->capabilities->find_all() as $capability)
		{
				$this->add('capabilities', $capability);
		}
		$this->save();

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function remove_role($role)
	{
		// Get role object
		if ( ! $role instanceOf Model_Role)
		{
			$role = ORM::factory('role', array('name' => $role));
		}

		// If object failed to load then throw exception
		if ( ! $role->loaded())
			throw new ACL_Exception('Tried to remove a role that did not exist.');

		// Remove all of the capabilities associated with the role
		foreach ($role->capabilities->find_all() as $capability)
		{
				$this->remove('capabilities', $capability);
		}

		// Remove the role from the user
		$this->remove('roles', $role);
		$this->save();

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function assign_capability($capability)
	{
		// Get capability object
		if ( ! $capability instanceOf Model_Capability)
		{
			$capability = ORM::factory('capability', array('name' => $capability));
		}

		// If object failed to load then throw exception
		if ( ! $capability->loaded())
			throw new Exception('Tried to assign a capability that did not exist.');

		// Add the capability to the user
		$this->add('capabilities', $capability);
		$this->save();

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function remove_capability($capability)
	{
		// Get capability object
		if ( ! $capability instanceOf Model_Capability)
		{
			$capability = ORM::factory('capability', array('name' => $capability));
		}

		// If object failed to load then throw exception
		if ( ! $capability->loaded())
			throw new Exception('Tried to remove a capability that did not exist.');

		// Add the capability to the user
		$this->remove('capabilities', $capability);
		$this->save();

		return $this;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function roles_list($reload = FALSE)
	{
		static $roles = array();

		// Construct the roles list
		if ($reload OR empty($roles))
		{
			// See if the user is logged in or not
			if ( ! Auth::instance()->logged_in())
			{
				$roles[] = Kohana::config('acl.public_role');
				return $roles;
			}

			// Get the name of all the user's roles
			foreach ($this->roles->find_all() as $role)
			{
				$roles[] = $role->name;
			}
		}

		return $roles;
	}

	/**
	 * xx
	 *
	 * @param   type  xx
	 * @return  type
	 */
	public function capabilities_list($reload = FALSE)
	{
		static $capabilities = array();

		// Check for authentication
		$authenticated = Auth::instance()->logged_in();

		// Construct the capabilities list
		if ($reload OR ($authenticated AND empty($capabilities)))
		{
			// Get the name of all the user's capabilities
			foreach ($this->capabilities->find_all() as $capability)
			{
				$capabilities[] = $capability->name;
			}
		}

		return $capabilities;
	}

} // End User Model