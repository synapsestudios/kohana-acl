<?php

class Acl implements Heroine\HeroineAwareInterface {
	const TYPE_ALLOW = 'allow';

	const TYPE_DENY = 'deny';

	protected $_acl;

	protected $_role_providers = array();

	protected $_resource_providers = array();

	protected $_rule_providers = array();

	protected $_identity_provider = array();

	protected $_loaded;

	protected $_heroine;

	public function __construct($config)
	{
		$that         = $this;
		$this->_loaded = function() use ($that)
		{
			$that->load();
		};
	}

	public function setHeroine(Heroine\Heroine $heroine)
	{
		$this->_heroine = $heroine;
	}

	public function set_heroine(Heroine\Heroine $heroine)
	{
		$this->setHeroine($heroine);
	}

	public function getHeroine()
	{
		return $this->_heroine;
	}

	public function get_heroine()
	{
		return $this->getHeroine();
	}

	public function add_role_provider(Acl_Role_ProviderInterface $provider)
	{
		$this->_loaded AND $this->_loaded->__invoke();
		$this->role_providers[] = $provider;

		return $this;
	}

	public function add_resource_provider(Acl_Resource_ProviderInterface $provider)
	{
		$this->_loaded AND $this->_loaded->__invoke();
		$this->_resource_providers[] = $provider;

		return $this;
	}

	public function add_rule_provider(Acl_Rule_ProviderInterface $provider)
	{
		$this->_loaded AND $this->_loaded->__invoke();
		$this->_rule_providers = $provider;

		return $this;
	}

	public function set_identity_provider(Acl_Identity_ProviderInterface $provider)
	{
		$this->_loaded AND $this->_loaded->__invoke();
		$this->_identity_provider = $provider;

		return $this;
	}

	public function get_identity()
	{
		$this->_loaded AND $this->_loaded->__invoke();

		return 'user-acl-identity';
	}

	public function can($resource, $privilege = NULL)
	{
		$this->_loaded AND $this->_loaded->__invoke();

		try
		{
			return $this->acl->isAllowed($this->get_identity(), $resource, $privilege);
		}
		catch (InvalidArgumentException $e)
		{
			return FALSE;
		}
	}

	public function get_acl()
	{
		$this->_loaded AND $this->_loaded->__invoke();

		return $this->_acl;
	}

	public function load()
	{
		if ($this->_loaded === NULL)
			return;

		$this->_loaded = NULL;
		$this->_acl    = new Zend\Permissions\Acl;

		foreach ($this->_heroine->get('Acl_RoleProviders') as $provider)
		{
			$this->add_role_provider($provider);
		}

		foreach ($this->_heroine->get('Acl_ResourceProviders') as $provider)
		{
			$this->add_resource_provider($provider);
		}

		foreach ($this->_heroine->get('Acl_RuleProviders') as $provider)
		{
			$this->add_rule_provider($provider);
		}

		$this->set_identity_provider($this->_heroine->get('Acl_IdentityProvider'));

		foreach ($this->_role_providers as $provider)
		{
			$this->_add_roles($provider->get_roles());
		}

		foreach ($this->_resource_providers as $provider)
		{
			$this->_add_resources($provider->get_resources());
		}

		foreach ($this->_rule_providers as $provider)
		{
			$rules = $provider->get_rules();
			if (isset($rules['allow']))
			{
				foreach ($rules['allow'] as $rule)
				{
					$this->_load_rule($rule, static::TYPE_ALLOW);
				}
			}

			if (isset($rules['deny']))
			{
				foreach ($rules['deny'] as $rule)
				{
					$this->_load_rule($rule, static::TYPE_DENY);
				}
			}
		}

		$parent_roles = $this->get_identity_provider()
			->get_identity_roles();

		$this->_acl->addRole($this->_get_identity(), $parent_roles);
	}

	protected function _add_roles($roles)
	{
		if ( ! is_array($roles))
		{
			$roles = array($roles);
		}

		foreach ($roles as $role)
		{
			if ($this->_acl->hasRole($role))
				continue;

			if ($role->getParent() !== NULL)
			{
				$this->_add_roles(array($role->getParent()));
				$this->_acl->addRole($role, $role->getParent());
			}
			else if ( ! $this->_acl->hasRole($role))
			{
				$this->_acl->addRole($role);
			}
		}
	}

	protected function _add_resource(array $resources, $parent = NULL)
	{
		foreach ($resources as $key => $value)
		{
			if (is_string($key))
			{
				$key = new Zend\Permissions\Acl\Resource\GenericResource($key);
			}
			else if (is_int($key))
			{
				$key = new Zend\Permissions\Acl\Resource\GenericResource($value);
			}

			if (is_array($value))
			{
				$this->_acl->addResource($key, $parent);
				$this->_add_resource($value, $key);
			}
			else if (! $this->_acl->hasResource($key))
			{
				$this->_acl->addResource($key, $parent);
			}
		}
	}

	protected function _load_rule(array $rule, $type)
	{
		$privileges = $assertion = NULL;
		$rule_size  = count($rule);

		if ($rule_size === 4)
		{
			list($roles, $resources, $privileges, $assertion) = $rule;
		}
		else if ($rule_size === 3)
		{
			list($roles, $resources, $privileges) = $rule;
		}
		else if ($rule_size === 2)
		{
			list($roles, $resources) = $rule;
		}
		else
		{
			throw new InvalidArgumentException('Invalid rule definition: ' . print_r($rule, TRUE));
		}

		if (is_string($assertion))
		{
			$assertion = $this->_heroine->get($assertion);
		}

		if ($type === static::TYPE_ALLOW)
		{
			$this->_acl->allow($roles, $resources, $privileges, $assertion);
		}
		else
		{
			$this->_acl->deny($roles, $resources, $privileges, $assertion);
		}
	}
}