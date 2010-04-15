<?php defined('SYSPATH') or die('No direct access allowed.');

// Allow all to access the userguide
ACL::add_rule(ACL::rule()
	->for_controller('userguide')
	->allow_all()
);

// Allow all to access the welcome controller
ACL::add_rule(ACL::rule()
	->for_controller('welcome')
	->allow_all()
);