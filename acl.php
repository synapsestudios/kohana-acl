<?php defined('SYSPATH') OR die('No direct access allowed.');
// This file should be executed in the bootstrap before Request::execute is
// run. It is called by: ACL::instance()->import_rules()->control_access();

// admin/*/*
$acl->add_rule(
	ACL_Rule::factory()
		->for_directory('admin')
		->allow_role('admin')
);

// current request
$acl->add_rule(
	ACL_Rule::factory()
		->for_current()
		->allow_auto()
);

// -/user/delete
$acl->add_rule(
	ACL_Rule::factory()
		->for_controller('user')
		->for_action('delete')
		->allow_capability('delete-user')
);

// -/public/*
$acl->add_rule(
	ACL_Rule::factory()
		->for_controller('public')
		->allow_all()
);