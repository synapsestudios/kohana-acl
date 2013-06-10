<?php

use Zend\Permissions\Acl\Resource\ResourceInterface

class Acl_Resource extends ResourceInterface {

	protected $_resource_id = NULL;

	public function getResourceId()
	{
		return $this->_resource_id;
	}

	public function get_resource_id()
	{
		return $this->getResourceId();
	}

}