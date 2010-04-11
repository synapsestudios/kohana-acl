<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Role Model
 *
 * @package    ACL
 * @author     Synapse Studios
 * @copyright  (c) 2010 Synapse Studios
 */
class Model_Acl_Role extends Model_Auth_Role {

	// Relationships
	
	protected $_has_many = array
	(
		'users'        => array('model' => 'user', 'through' => 'roles_users'),
		'capabilities' => array('model' => 'capability'),
	);

} // End Role Model