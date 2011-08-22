<?php defined('SYSPATH') or die('No direct access allowed.');

return array(
	/**
	 * The name of the "pseudo-role" that represents non-logged in users.
	 */
	'public_role' => 'guest',

	/**
	 * The name of the role that represents complete access, including
	 * (possible) access to non-public-facing actions.
	 */
	'super_role' => 'developer',

	/**
	 * Se to `TRUE` if your application is using capabilities. Capabilities
	 * allow for finer grained ACL control based more on what the user can do
	 * instead of who they are.
	 */
	'support_capabilities' => TRUE,

	/**
	 * Set to `TRUE` if a user must have an associated role to have the
	 * capability. Capabilities are associated with roles, but you have the
	 * option of assigning any capability to any user unless this item is set
	 * to TRUE, then the capability can only be assigning to the user if the
	 * have the role that the capability is associated with.
	 */
	'capabilities_limited_by_role' => FALSE,

	/**
	 * ACL rules can be defined here or programmatically. It depends on how the
	 * developer wishes to use the ACL module. These rules determine if users
	 * access to certain actions.
	 */
	'rules' => array(
		'welcome' => array(
			'for' => array('controller' => 'welcome'),
			'allow' => array('all' => TRUE),
		),
	),
);
