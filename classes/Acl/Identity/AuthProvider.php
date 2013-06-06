<?php

class AuthProvider implements Acl_Identity_ProviderInterface {

	protected $_default_role = 'guest';

	public function __construct(Kohana_Auth $auth)
	{
		$this->_auth = $auth;
	}

	public function get_identity_roles()
	{
		if ($this->_auth->logged_in())
			return array($this->_auth->get_user()->get_role());

		return $this->_default_role;
	}
}