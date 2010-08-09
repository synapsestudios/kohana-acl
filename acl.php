<?php defined('SYSPATH') or die('No direct access allowed.');

// Allow all to access the welcome controller
ACL::rule()
	->for_controller('welcome')
	->allow_all();
