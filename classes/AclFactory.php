<?php

class AclFactory implements Heroine\FactoryInterface
{
	public function createService(Heroine\Heroine $heroine, $service)
	{
		return new Acl($heroine->get('Acl\Config'));
	}
}