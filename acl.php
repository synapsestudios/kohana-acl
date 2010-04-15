<?php defined('SYSPATH') or die('No direct access allowed.');

// Allow
ACL::add_rule(ACL::rule()
	->for_controller('userguide')
	->allow_all()
);

// Allow
ACL::add_rule(ACL::rule()
	->for_controller('welcome')
	->allow_all()
);