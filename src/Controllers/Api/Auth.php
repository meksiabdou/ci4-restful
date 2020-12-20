<?php


namespace CI4Restful\Controllers\Api;

use CI4Restful\Helpers\RestServer;
use Myth\Auth\Entities\User;
use CI4Restful\Helpers\Email;
use CI4Restful\Models\UserModel;
use Myth\Auth\Models\LoginModel;
use Exception;

class Auth extends RestServer
{

	protected $auth;
	/**
	 * @var Auth
	 */
	protected $config;

	protected $users;

	protected $loginModel;

	protected $email;

	public function __construct()
	{

		$this->email = new Email();
		$this->config = config('Auth');
		$this->auth = service('authentication');
		$this->users = new UserModel();
		$this->loginModel = new LoginModel();
		$this->config->defaultUserGroup = 'user';
		$this->config->requireActivation = false;
		$this->config->activeResetter = true;
		$this->config->allowRemembering = true;
		$this->config->rememberLength = 5 * DAY;
	}


	private function get_data_user($device = 'web', $user = null, $token = null)
	{

		unset($user->password_hash);
		unset($user->reset_hash);
		unset($user->reset_at);
		unset($user->reset_expires);
		unset($user->activate_hash);
		//unset($user->created_on);
		//unset($user->updated_on);
		//unset($user->last_login);
		//unset($user->first_name);
		//unset($user->last_name);
		//unset($user->company);
		//unset($user->user_id);
		//unset($user->username);

		$user->token = $this->generate_key($user->id);
		$user->device = $device;

		return $user;
	}

	private function generate_key($uid, $device = 'web')
	{

		//$this->loginModel->purgeRememberTokens($uid);

		$this->loginModel->purgeOldRememberTokens();
		
		$selector  = bin2hex(random_bytes(12));
		$validator = bin2hex(random_bytes(20));
		$expires   = date('Y-m-d H:i:s', time() + $this->config->rememberLength);

		$token = $selector . ':' . $validator;

		// Store it in the database
		$this->loginModel->rememberUser($uid, $selector, hash('sha256', $validator), $expires);

		// Save it to the user's browser in a cookie.

		return $token;
	}

	public function logout()
	{
		$uid = $this->request->getPost('login');

		$this->loginModel->purgeRememberTokens($uid);

		return $this->response_json([], true);
	}

	public function login()
	{

		$rules = [
			'login'	=> 'required',
			'password' => 'required',
			'device' => 'required',
		];

		if ($this->config->validFields == ['email']) {
			$rules['login'] .= '|valid_email';
		}

		if (!$this->validate($rules)) {
			return $this->response_json(['code' => [3002], 'description' => $this->validator->getErrors()], false);
		}

		$login = $this->request->getPost('login');
		$password = $this->request->getPost('password');
		$device = $this->request->getPost('device');
		$remember = true;


		// Determine credential type
		$type = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

		// Try to log them in...
		if (!$this->auth->attempt([$type => $login, 'password' => $password], $remember)) {
			return $this->response_json(['code' => [3002], 'description' => $this->auth->error()], false);
		}

		$user = $this->get_data_user($device, $this->auth->user());

		return $this->response_json($user, true);
	}


	public function register()
	{
		// Check if registration is allowed
		$this->config->allowRegistration = true;

		// Validate here first, since some things,
		// like the password, can only be validated properly here.
		$rules = [
			'username' => [
				'label'  => 'username',
				'rules'  => "required|alpha_numeric_space|min_length[3]|is_unique[users.username]",
				'errors' => [
					'is_unique' => 3006,
					'valid_email' => 3006,
					'required' => 3008,
					'min_length' => 3008,
					'alpha_numeric_space' => 3008,
				]
			],
			'email' => [
				'label'  => 'email',
				'rules'  => "required|valid_email|is_unique[users.email]",
				'errors' => [
					'is_unique' => 3006,
					'valid_email' => 3006,
					'required' => 3008,
				]
			],
			'phone' => [
				'label'  => 'phone',
				'rules'  => "required|integer|is_unique[users.phone]|min_length[10]|max_length[10]",
				'errors' => [
					'is_unique' => 3007,
					'integer' => 3007,
					'max_length' => 3007,
					'min_length' => 3007,
					'required' => 3008,
				]
			],
			'fullname' => [
				'label'  => 'fullname',
				'rules'  => "required",
				'errors' => [
					'required' => 3008,
				]
			],
			'password' => [
				'label'  => 'password',
				'rules'  => "required|strong_password",
				'errors' => [
					'strong_password' => 3009,
					'required' => 3008,
				]
			],
			'confirmPassword' => [
				'label'  => 'confirmPassword',
				'rules'  => "required|matches[password]",
				'errors' => [
					'matches' => 3009,
					'required' => 3008,
				]
			],
		];


		if (!$this->validate($rules)) {
			$code = $this->validator->getErrors();

			return $this->response_json(['code' => $code, 'description' => 'Validation'], false);
		}

		$this->config->personalFields = ['fullname', 'phone'];

		// Save the user
		$allowedPostFields = array_merge(['password'], $this->config->validFields, $this->config->personalFields);

		$user = new User($this->request->getPost($allowedPostFields));

		$this->config->requireActivation !== false ? $user->generateActivateHash() : $user->activate();

		// Ensure default group gets assigned if set

		$this->config->defaultUserGroup = 'user';

		if (!empty($this->config->defaultUserGroup)) {
			$users = $this->users->withGroup($this->config->defaultUserGroup);
		}

		if (!$users->save($user)) {
			return $this->response_json(['code' => [3002], 'description' => $users->errors()], false);
		}

		if ($this->config->requireActivation !== false) {
			$sent = $this->email->sendActivation($user);
			// Success!
			if ($sent) {
				return $this->response_json(['code' => [2003], 'description' => "Account Successfully Created"], true);
			}
			return $this->response_json(['code' => [3004], 'description' => "mail not send"], false);
		}

		// Success!
		return $this->response_json(['code' => [2003], 'description' => "Account Successfully Created"], true);
	}


	public function reSendActivateAccount()
	{

		$throttler = service('throttler');

		if ($throttler->check($this->request->getIPAddress(), 2, MINUTE) === false) {
			return $this->response_json(['code' => [3029], 'description' => 'tooManyRequests'], false);
		}

		$login = urldecode($this->request->getGet('login'));
		$type = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

		$users = model('UserModel');

		$user = $users->where($type, $login)
			->where('active', 0)
			->first();

		if (is_null($user)) {
			return $this->response_json(['code' => [3002], 'description' => 'activationNoUser'], false);
		}

		$activator = service('activator');
		$sent = $activator->send($user);
		$sent = $this->email->sendActivation($user);

		// Success!
		if ($sent) {
			return $this->response_json(['code' => [2003], 'description' => "Account Successfully Created"], true);
		}

		return $this->response_json(['code' => [3004], 'description' => "mail not send"], false);
	}


	public function forgot()
	{

		$rules = [
			'email'	=> 'required|valid_email',
		];

		if (!$this->validate($rules)) {
			return $this->response_json(['code' => [3002], 'description' => $this->validator->getErrors()], false);
		}

		$user = $this->users->where('email', $this->request->getPost('email'))->first();

		if (is_null($user)) {
			return $this->response_json(['code' => [3002], 'description' => "forgotNoUser"], false);
		}

		// Save the reset hash /
		$user->generateResetHash();
		$this->users->save($user);

		$sent = $this->email->forgotEmailSent($user);

		if (!$sent) {
			return $this->response_json(['code' => [3004], 'description' => "mail not send"], false);
		}

		return $this->response_json(['code' => [2001], 'description' => "forgotEmailSent"], true);
	}

	/**
	 * Verifies the code with the email and saves the new password,
	 * if they all pass validation.
	 *
	 * @return mixed
	 */
	public function reset()
	{

		// First things first - log the reset attempt.
		$this->users->logResetAttempt(
			$this->request->getPost('email'),
			$this->request->getPost('token'),
			$this->request->getIPAddress(),
			(string)$this->request->getUserAgent()
		);

		$rules = [
			'token'		=> 'required',
			'email'		=> 'required|valid_email',
			'password'	 => 'required|strong_password',
			'confirmPassword' => 'required|matches[password]',
		];

		if (!$this->validate($rules)) {
			return $this->response_json(['code' => [3002], 'description' => $this->validator->getErrors()], false);
		}

		$user = $this->users->where('email', $this->request->getPost('email'))
			->where('reset_hash', $this->request->getPost('token'))
			->first();

		if (is_null($user)) {
			return $this->response_json(['code' => [3002], 'description' => 'forgotNoUser'], false);
		}

		// Reset token still valid?
		if (!empty($user->reset_expires) && time() > $user->reset_expires->getTimestamp()) {
			return $this->response_json(['code' => [3002], 'description' => "resetTokenExpired"], false);
		}

		// Success! Save the new password, and cleanup the reset hash.
		$user->password 		= $this->request->getPost('password');
		$user->reset_hash 		= null;
		$user->reset_at 		= date('Y-m-d H:i:s');
		$user->reset_expires    = null;
		$user->force_pass_reset = false;
		$this->users->save($user);

		return $this->response_json(['code' => [2002], 'description' => 'resetSuccess'], true);
	}


	public function update_user()
	{

		if (!$this->request->getPost('uid')) {
			return $this->response_json(['code' => [3002], 'description' => ""], false);
		}

		$uid = $this->request->getPost('uid');

		$rules = [
			'fullname' => [
				'label'  => 'fullname',
				'rules'  => "required",
				'errors' => [
					'required' => 3008,
				]
			],
			'email' => [
				'label'  => 'email',
				'rules'  => "required|valid_email|is_unique[users.email,id,$uid]",
				'errors' => [
					'is_unique' => 3006,
					'valid_email' => 3006,
					'required' => 3008,
				]
			],
			'phone' => [
				'label'  => 'phone',
				'rules'  => "required|integer|is_unique[users.phone,id,$uid]",
				'errors' => [
					'is_unique' => 3007,
					'integer' => 3007,
					'required' => 3008,
				]
			],
		];


		if (!$this->validate($rules)) {
			$code = $this->validator->getErrors();

			return $this->response_json(['code' => $code, 'description' => 'Validation'], false);
		}

		$user = $this->users->where('id', $uid)->first();

		if (!$user) {
			return $this->response_json(['code' => [3002], 'description' => ''], false);
		}

		$user->fullname = $this->request->getPost('fullname');
		$user->phone = $this->request->getPost('phone');
		$user->email = $this->request->getPost('email');

		try {
			$update = $this->users->save($user);

			if (!$update) {
				return $this->response_json(['code' => [3012], 'description' => 'Update unsuccessfully'], false);
			}
			return $this->response_json(['code' => [2002], 'description' => 'Edited successfully'], true);
		} catch (Exception $e) {
			return $this->response_json(['code' => [3012], 'description' => $e->getMessage()], false);
		}
	}


	public function update_password()
	{

		$rules = [
			'email' => [
				'label'  => 'email',
				'rules'  => "required|valid_email",
				'errors' => [
					'valid_email' => 3006,
					'required' => 3008,
				]
			],
			'oldpassword' => [
				'label'  => 'oldpassword',
				'rules'  => "required",
				'errors' => [
					'required' => 3008,
				]
			],
			'password' => [
				'label'  => 'password',
				'rules'  => "required|strong_password",
				'errors' => [
					'strong_password' => 3009,
					'required' => 3008,
				]
			],
			'confirmPassword' => [
				'label'  => 'confirmPassword',
				'rules'  => "required|matches[password]",
				'errors' => [
					'matches' => 3009,
					'required' => 3008,
				]
			],
		];

		if (!$this->validate($rules)) {
			$code = $this->validator->getErrors();

			return $this->response_json(['code' => $code, 'description' => 'Validation'], false);
		}

		$email = $this->request->getPost('email');
		$password = $this->request->getPost('oldpassword');

		if (!$this->auth->attempt(['email' => $email, 'password' => $password], false)) {
			return $this->response_json(['code' => [3002], 'description' => $this->auth->error()], false);
		}

		$user = $this->auth->user();

		$user->password = $this->request->getPost('password');

		try {
			$update = $this->users->save($user);

			if (!$update) {
				return $this->response_json(['code' => [3012], 'description' => 'Update unsuccessfully'], false);
			}
			return $this->response_json(['code' => [2002], 'description' => 'Edited successfully'], true);
		} catch (Exception $e) {
			return $this->response_json(['code' => [3012], 'description' => $e->getMessage()], false);
		}
	}
}
