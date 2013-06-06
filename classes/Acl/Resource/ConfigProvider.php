<?php

class Acl_Resource_ConfigProvider implements Acl_Resource_ProviderInterface {

	protected $_resources;

	public function __construct(array $config = array())
	{
		$this->_resources = $config;
	}

	public function get_resources()
	{
		return $this->_resources;
	}
}