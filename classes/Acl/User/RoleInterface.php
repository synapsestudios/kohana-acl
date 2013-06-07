<?php

interface Acl_User_SingleRoleInterface {
	/**
	 * @return array list of the user's roles
	 */
	public function get_roles();
}