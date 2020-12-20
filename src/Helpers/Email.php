<?php

namespace CI4Restful\Helpers;

use Config\Email as CEmail;
use CodeIgniter\Config\Services;

class Email
{

    protected $email;

    protected $config;

    protected $user;

    protected $subject;

    protected $message;

    public function __construct()
    {

        $this->config = new CEmail();

        $this->config->fromEmail = "meksiabdou@dz-web.eu";
        $this->config->fromName = "Meksi Abdennour";
        $this->config->protocol = "smtp";
        //$this->config->SMTPHost = "in-v3.mailjet.com";
        //$this->config->SMTPUser = "ab77dd8bf4bc067fc0c4f69f6f356112";
        //$this->config->SMTPPass = "75da9e35d70eab15f9028e5e3a4773e8";
        $this->config->SMTPHost = "dz-web.eu";
        $this->config->SMTPUser = "meksiabdou@dz-web.eu";
        $this->config->SMTPPass = "Zs86MB4mvS";
        $this->config->SMTPPort = 25;
        $this->config->SMTPTimeout = 30;
        $this->config->SMTPCrypto = "";


        $this->email = Services::email();

        $this->email->initialize($this->config);
    }

    public function send(): bool
    {

        $sent = $this->email->setFrom($this->config->fromEmail, $this->config->fromName)
            ->setTo($this->user->email)
            ->setSubject($this->subject)
            ->setMessage($this->message)
            ->setMailType('html')
            ->send();

        if (!$sent) {
            return false;
        }

        return true;
    }


    /**
     * Sends an activation email
     *
     * @param User $user
     *
     * @return mixed
     */

    public function sendActivation($user = null): bool
    {

        if (!$user) {
            return false;
        }

        $this->user = $user;
        $this->subject = "Activate your account";
        $this->message = view('emails/activation', ['hash' => $this->user->activate_hash]);

        return $this->send();
    }

    public function forgotEmailSent($user = null): bool
    {
        if (!$user) {
            return false;
        }

        $this->user = $user;
        $this->subject = "Password Reset Instructions";
        $this->message = view('emails/forgot', ['hash' => $this->user->reset_hash]);

        return $this->send();
    }
}
