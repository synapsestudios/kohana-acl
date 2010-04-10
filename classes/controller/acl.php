<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Acl extends Controller {

	public function action_index()
	{
		$data = array();
		echo Kohana::debug($data);
		$this->request->repsonse = 'ACL Module';
	}

	public function action_login($username)
	{
		$auth = Auth::instance();
		if ($auth->logged_in())
		{
			$auth->logout();
		}

		$user = ORM::factory('user', array('username' => $username));
		if ($user->loaded())
		{
			$auth->force_login($user);
			echo $auth->get_user()->username;
		}

		$this->request->response = Kohana::debug($_SESSION);
	}

	public function action_logout()
	{
		Session::instance()->destroy();
		$this->request->response = Kohana::debug($_SESSION);
	}

	public function action_add_users()
	{
		$user1 = ORM::factory('user')
			->values(array(
				'id' => 1,
				'email' => 'jeremy+user1@synapsestudios.com',
				'username' => 'userlogin1',
				'password' => 'acltest',
			))
			->save()
			->assign_role('login')
			->remove_capability('search-test');

		$user2 = ORM::factory('user')
			->values(array(
				'id' => 2,
				'email' => 'jeremy+user2@synapsestudios.com',
				'username' => 'userlogin2',
				'password' => 'acltest',
			))
			->save()
			->assign_role('login');

		$user3 = ORM::factory('user')
			->values(array(
				'id' => 3,
				'email' => 'jeremy+user3@synapsestudios.com',
				'username' => 'useradmin',
				'password' => 'acltest',
			))
			->save()
			->assign_role('login')
			->assign_role('admin');

		$user4 = ORM::factory('user')
			->values(array(
				'id' => 4,
				'email' => 'jeremy+user4@synapsestudios.com',
				'username' => 'userdeveloper',
				'password' => 'acltest',
			))
			->save()
			->assign_role('login')
			->assign_role('developer');

		$this->request->response = 'Users were added.';
	}

	public function action_delete_users()
	{
		DB::delete('users')->where('id', 'IN', array(1,2,3,4))->execute();

		$this->request->response = 'Users were deleted.';
	}

} // End Acl
