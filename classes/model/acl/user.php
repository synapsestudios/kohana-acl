<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * User Model - Extend this to include ACL functionality
 *
 * @package    ACL
 * @author     Synapse Studios
 * @author     Jeremy Lindblom <jeremy@synapsestudios.com>
 * @copyright  (c) 2010 Synapse Studios
 */
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
	 * Determines whether or not a user has (is) a particular role
	 *
	 * @param   mixed  Role to check for
	 * @return  boolean
	 */
	public function is_a($role)
	{
		// Handle guests
		if ($role === Kohana::config('acl.public_role'))
		{
			$login_role = ORM::factory('role', array('name' => 'login'));
			if ( ! $this->loaded() OR ! $this->has('roles', $login_role))
				return TRUE;
			else
				return FALSE;
		}

		// Get role object
		if ( ! $role instanceof Model_Role)
		{
			$role = ORM::factory('role', array('name' => $role));
		}
		
		// If object failed to load then throw exception
		if ( ! $role->loaded())
			throw new UnexpectedValueException('Tried to check for a role that did not exist.');

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
		if (Kohana::config('acl.support_capabilities') === FALSE)
			throw new Kohana_ACL_Exception ('Capabilities are not supported in this configuration of the ACL module.');

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
			throw new UnexpectedValueException('Tried to check for a capability that did not exist.');

		// Return whether or not they have access
		return (bool) $user->has('capability', $capability);
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
	 * Assigns a role (and associatted capabilities) to a User
	 *
	 * @param   mixed  Role to assign
	 * @return  Model_User
	 */
	public function add_role($role)
	{
		// Get role object
		if ( ! $role instanceOf Model_Role)
		{
			$role = ORM::factory('role', array('name' => $role));
		}

		// If object failed to load then throw exception
		if ( ! $role->loaded())
			throw new UnexpectedValueException('Tried to assign a role that did not exist.');

		// Add the role to the user
		$this->add('roles', $role);

		// Add all of the capabilities associated with the role
		foreach ($role->capabilities->find_all() as $capability)
		{
			$this->add('capabilities', $capability);
		}

		return $this;
	}

	/**
	 * Removes a role (and associatted capabilities) from a User
	 *
	 * @param   mixed  Role to remove
	 * @return  Model_User
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
			throw new UnexpectedValueException('Tried to remove a role that did not exist.');

		// Remove all of the capabilities associated with the role
		foreach ($role->capabilities->find_all() as $capability)
		{
			$this->remove('capabilities', $capability);
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
		if (Kohana::config('acl.support_capabilities') === FALSE)
			throw new Kohana_ACL_Exception ('Capabilities are not supported in this configuration of the ACL module.');

		// Get capability object
		if ( ! $capability instanceOf Model_Capability)
		{
			$capability = ORM::factory('capability', array('name' => $capability));
		}

		// If object failed to load then throw exception
		if ( ! $capability->loaded())
			throw new UnexpectedValueException('Tried to assign a capability that did not exist.');

		// Capabilities can only be assigned when a user has the associated role
		if (Kohana::config('acl.capabilties_limited_by_role'))
		{
			if ($capability->role_id !== NULL AND ! $this->has('roles', $capability->role))
				throw new UnexpectedValueException('Tried to assign the :capability capability to a user without the required :role role.',
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
		if (Kohana::config('acl.support_capabilities') === FALSE)
			throw new Kohana_ACL_Exception ('Capabilities are not supported in this configuration of the ACL module.');

		// Get capability object
		if ( ! $capability instanceOf Model_Capability)
		{
			$capability = ORM::factory('capability', array('name' => $capability));
		}

		// If object failed to load then throw exception
		if ( ! $capability->loaded())
			throw new UnexpectedValueException('Tried to remove a capability that did not exist.');

		// Add the capability to the user
		$this->remove('capabilities', $capability);

		return $this;
	}

	/**
	 * Retrieves a list of the names of the roles a user has
	 *
	 * @param   boolean  If TRUE, then re-query the database for this list
	 * @return  array
	 */
	public function roles_list($reload = FALSE)
	{
		static $roles = array();

		// Construct the roles list
		if ($reload OR ! isset($roles[$this->id]))
		{
			// Create array of roles for this user
			$roles[$this->id] = array();
		
			// See if the user is logged in or not
			if ( ! Auth::instance()->logged_in())
			{
				$roles[$this->id][] = Kohana::config('acl.public_role');
				return $roles[$this->id];
			}

			// Get the name of all the user's roles
			foreach ($this->roles->find_all() as $role)
			{
				$roles[$this->id][] = $role->name;
			}
		}

		return Arr::get($roles, $this->id, array());
	}

	/**
	 * Retrieves a list of the names of the capabilities a user has
	 *
	 * @param   boolean  If TRUE, then re-query the database for this list
	 * @return  array
	 */
	public function capabilities_list($reload = FALSE)
	{
		static $capabilities = array();

		// Check for authentication
		$authenticated = Auth::instance()->logged_in();

		// Construct the capabilities list
		if ($reload OR ($authenticated AND ! isset($capabilities[$this->id])))
		{
			// Create array of capabilities for this user
			$capabilities[$this->id] = array();
			
			// Get the name of all the user's capabilities
			if (Kohana::config('acl.support_capabilities'))
			{
				foreach ($this->capabilities->find_all() as $capability)
				{
					$capabilities[$this->id][] = $capability->name;
				}
			}
		}

		return Arr::get($capabilities, $this->id, array());
	}

} // End User Model