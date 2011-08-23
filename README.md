# ACL

*ACL module for Kohana 3.2.x*

- **Module Versions:** 2.0.x
- **Module URL:** <http://github.com/synapsestudios/kohana-acl>
- **Compatible Kohana Version(s):** 3.2.x

## Description

The ACL module for Kohana `3.2.x` builds upon and extends the Auth module to add
ACL functionality to Kohana. You do not have to change anything about Auth to
use this module, and it does not affect the behavior of Auth in any way.

The ACL module includes a new type of model called a "capability". These are
similar to roles, but are more specific. Roles are used to represent what a
user **IS**, and capabilities are used to represent what a user can **DO**.
Capabilities will most likely correspond to an action on a controller. For
example, you might protect the `delete` action on the `article` controller by
enforcing the rule that the current user must have a capability called
`delete_article` (or something similar).

ACL rules are defined in such a way that they have a specific scope, and this
scope can cascade onto less specific rules. This cascading system allows
multiple rules that could apply to any one request, that get merged together.
Each request has 3 parts: a `directory`, a `controller`, and an `action`. These
3 parts represent 3 levels of the cascading rule system where a rule protecting
the action is the most specific. ACL, by default, blocks access to all requests,
therefore, the ACL rules are used to white-list users. You can allow users
specifically by ID (or username), by the capabilities they have, or by the
roles they have.

## Requirements and Installation

The ACL module requires that the following Kohana modules already be installed
and setup:

- Database
- ORM
- Auth (ORM driver)
- Cache (optional, but recommended for caching ACL rule lists)

In order for the ACL module to work properly, the following things
must be done:

1. The ACL module needs to be enabled in bootstrap (it should appear in the
modules list *before* `auth`)
1. The SQL in the `acl.sql` file needs to be executed to add the ACL module's
database tables and relationships.
1. If you are overwriting the role, capability, or user models in your
application, be sure to extend `Model_ACL_Role`, `Model_ACL_Capability` and
`Model_ACL_User` classes, respectively.
1. You must insert code into your application to define the ACL rules and
execute the ACL system. As of Kohana `3.2.x`, the `before()` method in a
controller is the best place to do this. This controller should be extended by
the other controllers in your application. See the **Setting Up and Executing
ACL** section below.

## Example Uses

### Setting Up and Executing ACL

	abstract class Controller_Base extends Controller
	{
		public function before()
		{
			parent::before();

			// Check for 404s
			if ( ! method_exists($this, 'action_'.$this->request->action()))
				throw new HTTP_Exception_404('The requested URL :url was not found on this server.',
					array(':url' => $this->request->uri()));

			// Get the current user
			$this->auth = Auth::instance();
			$this->user = $this->auth->get_user() ?: ORM::factory('user');

			// Setup the ACL rules (from the cache if possible)
			$cache = Cache::instance('file');
			$rules = $cache->get('acl-rules-list');
			if ( ! $rules)
			{
				$rules = ACL_Rule_List::factory()
					->add(ACL_Rule::factory()
						->for_controller('welcome')
						->allow_all());

				$cache->set('acl-rules-list', $rules, Date::MONTH);
			}

			// Create the ACL and enforce the authorization of the user for this request
			$this->acl = ACL::factory($rules)
				->assert_authorized($this->user, $this->request);
		}
	}

### Checking if a User Can Visit a URL

	// Get the URI via reverse routing
	$uri = Route::get('default')->uri(array(
		'controller' => 'article',
		'action'     => 'edit',
		'id'         => 15,
	));

	// Assuming $this->acl has a instance of `ACL` and $this->user has an
	// instance of `Model_ACL_User`, let's make sure that the user is authorized
	// to visit the URI.
	if ($this->acl->is_authorized($this->user, $uri))
	{
		$this->request->redirect($uri);
	}
	else
	{
		$this->request->redirect('login');
	}

### Checking Roles and Capabilities

	// Fetch the user
	$user = Auth::instance()->get_user() ?: ORM::factory('user');

	// Check if the user has the admin role
	echo Debug::vars($user->is_a('admin'));
	
	// Check if the user has the edit_article capability
	echo Debug::vars($user->can('edit_article'));

	// Check if an article belongs_to a user
	$article = ORM::factory('article', 15);
	echo Debug::vars($user->owns($article));