<?php defined('SYSPATH') OR die('No direct access allowed.');
// EXAMPLES:

$acl = ACL::instance();

// admin/*/*
$acl->add_rule(ACL::rule()
	->for_directory('admin')
	->allow_role('admin')
);

// -/user/{current}
$acl->add_rule(ACL::rule()
	->for_controller('user')
	->for_current_action()
	->allow_auto()
);

// -/user/delete
$acl->add_rule(ACL::rule()
	->for_controller('user')
	->for_action('delete')
	->allow_capability('delete-user')
);

// -/public/*
$acl->add_rule(ACL::rule()
	->for_controller('public')
	->allow_all()
);

$acl->add_rule(ACL::rule()
	->for_controller('public')
	->allow_all()
);