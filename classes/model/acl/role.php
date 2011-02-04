<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Role Model
 *
 * @package    ACL
 * @author     Synapse Studios
 * @author     Jeremy Lindblom <jeremy@synapsestudios.com>
 * @copyright  (c) 2010 Synapse Studios
 */
class Model_ACL_Role extends Model_Auth_Role {
	
	protected $_has_many = array
	(
		'users'        => array('model' => 'user', 'through' => 'roles_users'),
		'capabilities' => array('model' => 'capability'),
	);

} // End Model_ACL_Role