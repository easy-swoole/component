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

    /**
     * 公钥加密
     * @param $data
     * @return bool|string
     */
    public function encrypt($data)
    {
        //加密数据
        $encrypt_data = '';
        if (openssl_public_encrypt($data, $encrypt_data, $this->publicKey)) {
            return base64_encode($encrypt_data);
        } else {
            return false;
        }
    }

    /**
     * 私钥分段解密
     * @param $data string 加密数据
     * @param int $max_decrypt_block 默认128
     * @return bool|string
     */
    public function decrypt(string $data,int $max_decrypt_block = 128)
    {
        $decrypted = '';
        $plain_data = str_split(base64_decode($data), $max_decrypt_block);
        foreach($plain_data as $chunk){
            $str = '';
            //私钥解密
            $ok = openssl_private_decrypt($chunk,$str,$this->privateKey);
            if($ok === false){
                return false;
            }
            $decrypted .= $str;
        }
        return $decrypted;
    }

    /**
     * 私钥签名
     * @param string $data
     * @return string
     */
    public function sign(string $data)
    {
        $signature = '';
        openssl_sign($data, $signature, $this->privateKey);
        openssl_free_key($this->privateKey);
        $signature = base64_encode($signature);
        return $signature;
    }

    /**
     * 公钥验证签名
     * @param string $data  签名数据
     * @param string $signature 签名
     * @param int $signature_alg 加密算法
     * @return bool
     */
    public function isValid(string $data, string $signature,$signature_alg = OPENSSL_ALGO_SHA1)
    {
        $result = openssl_verify($data, base64_decode($signature), $this->publicKey, $signature_alg);
        if ($result === 1){
            return true;
        }
        return false;
    }
}
