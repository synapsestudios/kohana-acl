<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Acl_Capability extends ORM {

	// Relationships
	
	protected $_has_many = array
	(
		'users' => array('model' => 'user', 'through' => 'capabilities_users'),
	);

	protected $_belongs_to = array
	(
		'role' => array('model' => 'role')
	);

} // End Capability Model