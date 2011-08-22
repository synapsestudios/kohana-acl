# ACL

*ACL module for Kohana 3.2.x*

- **Module Versions:** 2.0.x
- **Module URL:** <http://github.com/synapsestudios/kohana-acl>
- **Compatible Kohana Version(s):** 3.2.x

## Example Use

	class Controller_Base extends Controller
	{
		public function before()
		{
			parent::before();

			if ( ! method_exists($this, 'action_'.$this->request->action()))
				throw new HTTP_Exception_404('The requested URL :url was not found on this server.',
					array(':url' => $this->request->uri()));

			$this->auth = Auth::instance();
			$this->user = $this->auth->get_user() ?: ORM::factory('user');

			$rules = function_exists('apc_fetch') ? apc_fetch('acl-rules-list') : FALSE;
			if ($rules === FALSE)
			{
				$config = ACL::config('rules');
				$rules = ACL_Rule_List::factory()
					->from_array($config);

				if (function_exists('apc_store'))
				{
					apc_store('acl-rules-list', serialize($rules));
				}
			}
			else
			{
				$rules = unserialize($rules);
			}

			$this->acl = ACL::factory($rules)
				->assert_authorized($this->user, $this->request);
		}
	}
