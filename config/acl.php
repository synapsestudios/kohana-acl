<?php defined('SYSPATH') or die('No direct access allowed.');

return array
(
	/**
	 * The name of the pseudo-role that represents non-logged in users
	 */
	'public_role' => 'guest',

	/**
	 * The name of the role that represents complete access
	 */
	'super_role' => 'developer',

	/**
	 * `TRUE` if your application is using capabilities
	 */
	'support_capabilities' => TRUE,

	/**
	 * `TRUE` if a user must have an associated role to have a capability
	 */
	'capabilties_limited_by_role' => FALSE,	
);
