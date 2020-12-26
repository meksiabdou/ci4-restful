<?php

namespace CI4Restful\Helpers;

use Myth\Auth\Models\LoginModel;

class Token
{

    private $loginModel;
    public $rememberLength;

    public function __construct($rememberLength = 5)
    {
        $this->loginModel = new LoginModel();
        $this->rememberLength = $rememberLength * DAY;
    }

    function generateToken($user)
    {

        unset($user->password_hash);
        unset($user->reset_hash);
        unset($user->reset_at);
        unset($user->reset_expires);
        unset($user->activate_hash);

        
        $user->token = $this->generate_key($user->id);

        return $user;
    }

    private function generate_key($uid)
    {

        $this->loginModel->purgeOldRememberTokens();

        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(20));
        $expires   = date('Y-m-d H:i:s', time() + $this->rememberLength);

        $token = $selector . ':' . $validator;

        // Store it in the database
        $this->loginModel->rememberUser($uid, $selector, hash('sha256', $validator), $expires);

        // Save it to the user's browser in a cookie.

        return $token;
    }
}
