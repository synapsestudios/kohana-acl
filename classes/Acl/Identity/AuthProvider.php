<?php

/**
 * Assumes that your user model returned from Kohana Auth has a get_role() method
 */
class AuthProvider implements Acl_Identity_ProviderInterface {

	protected $_default_role = 'guest';

	protected $_authenticated_role = 'authenticated';

	public function __construct(Kohana_Auth $auth)
	{
		$this->_auth = $auth;
	}

	public function get_identity_roles()
	{
		if ( ! $this->_auth->logged_in())
			return array($this->_default_role);

		$user = $this->_auth->get_user();

		if ($user instanceof Acl_User_RoleInterface)
			return $this->_auth->get_user()->get_roles();

		return array($this->_authenticated_role);
	}
}