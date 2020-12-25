<?php

namespace CI4Restful\Helpers;

use CodeIgniter\RESTful\ResourceController;
use CI4Restful\Models\QueryModel;
use Config\App as Configs;

class RestServer extends ResourceController
{

    protected $request;

    protected $token;

    protected $token_app;

    protected $array_methods = [
        'login',
        'register',
        'logout',
        'reSendActivateAccount',
        'forgot',
        'reset',
        "stores",
        "coupons",
        "storesById",
        "categories",
        "getPages"
    ];

    protected $users;

    public function __construct()
    {
        $this->request = service('request');
    }

    public function _remap($method, ...$params)
    {
 
        if (!$this->auth($method)) {
            return $this->respond(["status" => false, "error" => ""], 403);
        }

        return $this->$method(...$params);
    }

    private function auth($method)
    {
        if ($this->request->getHeader('token')) {

            $config = new Configs();
            $this->token_app = (isset($config->token_app)) ? $config->token_app : '';
            $this->token = $this->request->getHeader('token')->getValue();

            if (in_array($method, $this->array_methods)) {
                if (!in_array($this->token, $this->token_app)) {
                    return false;
                }
                return true;
            } else {

                $query = new QueryModel('auth_tokens');

                $_token = explode(':', $this->token);

                if (is_array($_token)) {

                    $selector = $_token[0];
                    $validator = hash('sha256', $_token[1]);

                    $getToken =  $query->where('selector', $selector)
                        ->where('hashedValidator', $validator)
                        ->where('expires >=', date('Y-m-d H:i:s'))
                        ->get()
                        ->getRow();

                    if ($getToken) {
                        return true;
                    }
                }

                return false;
            }
        }
        return false;
    }

    public function response_json($data = [], $status = true, $statusCode = 200)
    {
        $this->format = 'json';
        return $this->respond(["status" => $status, "results" => $data], $statusCode);
    }

}
