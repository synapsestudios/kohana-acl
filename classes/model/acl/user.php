<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * User Model - Extend this to include ACL functionality
 *
 * @package    ACL
 * @author     Synapse Studios
 * @author     Jeremy Lindblom <jeremy@synapsestudios.com>
 * @copyright  (c) 2010 Synapse Studios
 */
class Model_ACL_User extends Model_Auth_User {

	protected $_roles_list = array();

	protected $_capabilities_list = array();

	protected $_has_many = array(
		'user_tokens'  => array('model' => 'user_token'),
		'roles'        => array('model' => 'role', 'through' => 'roles_users'),
		'capabilities' => array('model' => 'capability', 'through' => 'capabilities_users'),
	);

	/**
	 * Determines whether or not a user has (is) a particular role
	 *
	 * @param   mixed  Role to check for
	 * @return  boolean
	 */
	public function is_a($role)
	{
		// Handle guests
		if ($role === ACL::config('public_role'))
		{
			$login_role = ORM::factory('role', array('name' => 'login'));
			if ( ! $this->loaded() OR ! $this->has('roles', $login_role))
				return TRUE;
			else
				return FALSE;
		}

		// Get role object
		if ($role instanceof Model_ACL_Role)
		{
			$name = $role->name;
		}
		else
		{
			$name = $role;
			$role = ORM::factory('role', array('name' => $name));
		}
		
		// If object failed to load then throw exception
		if ( ! $role->loaded())
			throw new ACL_Exception('Tried to check for the role, ":name", which did not exist.', array(':name' => $name));

		// Return whether or not they have the role
		return (bool) $this->has('roles', $role);
	}

	/**
	 * Determines whether or not a user has a particular capability
	 *
	 * @param   mixed  Role to check for
	 * @return  boolean
	 */
	public function can($capability)
	{
		// Do not allow this method if capabilities are not supported
		if ( ! ACL::config('support_capabilities'))
			throw new ACL_Exception('Capabilities are not supported in this configuration of the ACL module.');

		// If the user has the super role, they can!
		if (ACL::config('super_role') AND $this->is_a(ACL::config('super_role')))
			return TRUE;
	
		// Get capability object
		if ($capability instanceof Model_ACL_Capability)
		{
			$name = $capability->name;
		}
		else
		{
			$name = $capability;
			$capability = ORM::factory('capability', array('name' => $name));
		}
		
		// If object failed to load then throw exception
		if ( ! $capability->loaded())
			throw new ACL_Exception('Tried to check for the capability, ":name", which did not exist.', array(':name' => $name));

		// Return whether or not they have access
		return (bool) $this->has('capabilities', $capability);
	}

	/**
	 * Checks to see if the owns a specified model. This theoretically works
	 * for any relationship type.
	 *
	 * @param   ORM      The object that might be owned
	 * @return  boolean  Whether or not the model is owned by this user
	 */
	public function owns(ORM $model)
	{
		// Get a list of all applicable relationships
		$relationships = $model->belongs_to();
		foreach ($model->has_many() as $alias => $has_many)
		{
			if ( ! empty($has_many['through']))
			{
				$relationships[$alias] = $has_many;
			}
		}

		// Check each applicable relationship
		foreach ($relationships as $alias => $relationship)
		{
			// Make sure the relationship is to the correct model
			if ($relationship['model'] != $this->object_name())
				continue;

			// Check the foreign keys to verify a relationship
			if (isset($relationship['far_key']))
			{
				if ($model->has($alias, $this))
					return TRUE;
			}
			elseif ($model->{$relationship['foreign_key']} == $this->id)
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Assigns a role (and associated capabilities) to a User
	 *
	 * @param   mixed  Role to assign
	 * @return  Model_User
	 */
	public function add_role($role)
	{
		// Get role object
		if ( ! $role instanceof Model_ACL_Role)
		{
			$role = ORM::factory('role', array('name' => $role));
		}

		// If object failed to load then throw exception
		if ( ! $role->loaded())
			throw new ACL_Exception('Tried to assign a role that did not exist.');

		// Add the role to the user
		$this->add('roles', $role);

		// Add all of the capabilities associated with the role
		if (ACL::config('support_capabilities'))
		{
			foreach ($role->capabilities->find_all() as $capability)
			{
				$this->add('capabilities', $capability);
			}
		}

		return $this;
	}

	/**
	 * Removes a role (and associated capabilities) from a User
	 *
	 * @param   mixed  Role to remove
	 * @return  Model_User
	 */
	public function remove_role($role)
	{
		// Get role object
		if ( ! $role instanceof Model_ACL_Role)
		{
			$role = ORM::factory('role', array('name' => $role));
		}

		// If object failed to load then throw exception
		if ( ! $role->loaded())
			throw new ACL_Exception('Tried to remove a role that did not exist.');

		// Remove all of the capabilities associated with the role
		if (ACL::config('support_capabilities'))
		{
			foreach ($role->capabilities->find_all() as $capability)
			{
				$this->remove('capabilities', $capability);
			}
		}

		// Remove the role from the user
		$this->remove('roles', $role);

		return $this;
	}

	/**
	 * Assigns a capability to a User
	 *
	 * @param   mixed  Capability to assign
	 * @return  Model_User
	 */
	public function add_capability($capability)
	{
		// Do not allow this method if capabilities are not supported
		if ( ! ACL::config('support_capabilities'))
			throw new ACL_Exception('Capabilities are not supported in this configuration of the ACL module.');

		// Get capability object
		if ( ! $capability instanceof Model_ACL_Capability)
		{
			$capability = ORM::factory('capability', array('name' => $capability));
		}

		// If object failed to load then throw exception
		if ( ! $capability->loaded())
			throw new ACL_Exception('Tried to assign a capability that did not exist.');

		// Capabilities can only be assigned when a user has the associated role
		if (ACL::config('capabilities_limited_by_role'))
		{
			if ($capability->role_id !== NULL AND ! $this->has('roles', $capability->role))
				throw new ACL_Exception('Tried to assign the :capability capability to a user without the required :role role.',
					array(':capability' => $capability->name, ':role' => $capability->role->name));
		}

		// Add the capability to the user
		$this->add('capabilities', $capability);

		return $this;
	}

	/**
	 * Removes a capability from a User
	 *
	 * @param   mixed  Capability to remove
	 * @return  Model_User
	 */
	public function remove_capability($capability)
	{
		// Do not allow this method if capabilities are not supported
		if ( ! ACL::config('support_capabilities'))
			throw new ACL_Exception('Capabilities are not supported in this configuration of the ACL module.');

		// Get capability object
		if ( ! $capability instanceof Model_ACL_Capability)
		{
			$capability = ORM::factory('capability', array('name' => $capability));
		}

		// If object failed to load then throw exception
		if ( ! $capability->loaded())
			throw new ACL_Exception('Tried to remove a capability that did not exist.');

		// Add the capability to the user
		$this->remove('capabilities', $capability);

		return $this;
	}

	/**
	 * Retrieves a list of the names of the roles a user has
	 *
	 * @return  array
	 */
	public function roles_list()
	{
		if (empty($this->_roles_list))
		{
			// See if the user is logged in or not
			if ( ! Auth::instance()->logged_in())
			{
				$this->_roles_list[] = ACL::config('public_role');
				return $this->_roles_list;
			}

			// Get the name of all the user's roles
			foreach ($this->roles->find_all() as $role)
			{
				$this->_roles_list[] = $role->name;
			}
		}

		return $this->_roles_list;
	}

	/**
	 * Retrieves a list of the names of the capabilities a user has
	 *
	 * @return  array  list of capabilities
	 */
	public function capabilities_list()
	{
		if (empty($this->_capabilities_list))
		{
			// Get the name of all the user's capabilities
			if (Auth::instance()->logged_in() AND ACL::config('support_capabilities'))
			{
				foreach ($this->capabilities->find_all() as $capability)
				{
					$this->_capabilities_list[] = $capability->name;
				}
			}
		}

		return $this->_capabilities_list;
	}

}
