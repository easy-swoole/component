<?php


namespace EasySwoole\Component\Crypto;


class RSA
{
    protected $publicKey;
    protected $privateKey;
    public function __construct($publicKey,$privateKey)
    {
        $this->publicKey = openssl_get_publickey($publicKey);
        $this->privateKey = openssl_get_privatekey($privateKey);
    }
}