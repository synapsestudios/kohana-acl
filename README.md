# ACL

ACL module based on [Zend\Permissions\Acl](https://github.com/zendframework/zf2/tree/master/library/Zend/Permissions/Acl) for Kohana 3.3

Dependencies:
 - Zend\Permissions\Acl ~2.2
 - [Heroine](https://github.com/synapsestudios/heroine) service locator
 - [Castellan](https://github.com/synapsestudios/castellan) event dispatcher

### Controller Guard

In order to use the controller guard, you must extend the base Controller's
execute method to include the following code:

```php
public function execute()
{
	Heroine\Heroine::instance()->get('Controller_Dispatcher')
		->trigger('controller.execute', $this);

	return parent::execute();
}
```

### Sample Service Configuration

```php
use Heroine\Heroine;

$config = array(
	'callables' => array(
		'Acl' => function($heroine) {
			return new Acl(Kohana::$config->load('acl'));
		},
		'Acl_IdentityProvider' => function($heroine) {
			return new Acl_Identity_AuthProvider(Auth::instance());
		}
	),
);

$heroine = Heroine::instance('acl', $config);

$guard = new Acl_Guard_Controller(Kohana::$config->load('acl.controllers'), $heroine->get('Acl'));
$guard->attach(Heroine::instance()->get('Controller_Dispatcher'));
```

### Sample Config

```php
return array(
	'role_providers' => array(
		new Acl_Role_ConfigProvider(array(
			'guest' => array('children' => array(
				'authenticated' => array('children' => array(
					'user' => array('children' => array(
						'admin',
					)),
				)),
			)),
		)),
	),

	'resource_providers' => array(
		new Acl_Resource_ConfigProvider(array(
			'myresource'
		)),
	),

	'rule_providers' => array(
		new Acl_Rule_ConfigProvider(array(
			'allow' => array(
				// allow guests and users (and admins, through inheritance)
				// the "wear" privilege on the resource "pants"
				array(array('guest', 'user'), 'pants', 'wear')
			),
		)),
	),

	'controllers' => array(
		array(
			'controller' => 'user',
			'action'     => array('login'),
			'roles'      => array('guest'),
		),
		array(
			'controller' => 'app',
			'action'     => array('index'),
			'roles'      => array('authenticated'),
		),
	),
);
```