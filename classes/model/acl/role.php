<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Acl_Role extends Model_Auth_Role {

	// Relationships
	
	protected $_has_many = array
	(
		'users'        => array('model' => 'role', 'through' => 'roles_users'),
		'capabilities' => array('model' => 'capability'),
	);

} // End Role Model