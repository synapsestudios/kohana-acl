<?php

class Acl_Rule_ConfigProvider implements Acl_Rule_ProviderInterface {

	protected $_rules = array();

	public function __construct(array $config = array())
	{
		$this->_rules = $config;
	}

	public function get_rules()
	{
		return $this->_rules;
	}
}