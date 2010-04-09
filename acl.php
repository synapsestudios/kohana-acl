<?php defined('SYSPATH') OR die('No direct access allowed.');
// EXAMPLES:

// For admin/*/* allow only role "admin"
ACL::add_rule(ACL::rule()
	->for_directory('admin')
	->allow_role('admin')
);

// For -/user/{current_action} allow capability "{current_action}_user"
ACL::add_rule(ACL::rule()
	->for_controller('user')
	->for_current_action()
	->allow_auto()
);

// For -/user/delete allow capability "delete_user"
ACL::add_rule(ACL::rule()
	->for_controller('user')
	->for_action('delete')
	->allow_capability('delete-user')
);

// For -/public/* allow all roles
ACL::add_rule(ACL::rule()
	->for_controller('public')
	->allow_all()
);