<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Capability Model
 *
 * @package    ACL
 * @author     Synapse Studios
 * @author     Jeremy Lindblom <jeremy@synapsestudios.com>
 * @copyright  (c) 2010 Synapse Studios
 */
class Model_ACL_Capability extends ORM {
	
	protected $_has_many = array
	(
		'users' => array('model' => 'user', 'through' => 'capabilities_users'),
	);

	protected $_belongs_to = array
	(
		'role' => array('model' => 'role')
	);

} // End Model_ACL_Capability