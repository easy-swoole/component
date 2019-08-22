<?php


namespace EasySwoole\Component\Crypto;


class RSA
{
    protected $publicKey;
    protected $privateKey;

    const RSA_DECRYPT_128 = 128;
    const RSA_ENCRYPT_117 = 117;

    const OPENSSL_ALGO_SHA1 = 'OPENSSL_ALGO_SHA1';
    const OPENSSL_ALGO_MD5 = 'OPENSSL_ALGO_MD5';
    const OPENSSL_ALGO_MD4 = 'OPENSSL_ALGO_MD4';
    const OPENSSL_ALGO_MD2 = 'OPENSSL_ALGO_MD2';
    const OPENSSL_ALGO_DSS1 = 'OPENSSL_ALGO_DSS1';
    const OPENSSL_ALGO_SHA224 = 'OPENSSL_ALGO_SHA224';
    const OPENSSL_ALGO_SHA256 = 'OPENSSL_ALGO_SHA256';
    const OPENSSL_ALGO_SHA384 = 'OPENSSL_ALGO_SHA384';
    const OPENSSL_ALGO_SHA512 = 'OPENSSL_ALGO_SHA512';
    const OPENSSL_ALGO_RMD160 = 'OPENSSL_ALGO_RMD160';

    public function __construct($publicKey,$privateKey)
    {
        $this->publicKey = openssl_get_publickey($this->transform('public',$publicKey));
        $this->privateKey = openssl_get_privatekey($this->transform('private',$privateKey));
    }

    /**
     * 公钥加密
     * @param $data
     * @param $max_encrypt_block int 分断加密字节数
     * @return bool|string
     */
    public function encrypt($data,$max_encrypt_block = RSA::RSA_ENCRYPT_117)
    {
        //加密数据
        $encrypt_data = '';
        $plain_data = str_split(base64_decode($data), $max_encrypt_block);
        foreach ($plain_data as $chunk){
            $str = '';
            $ok = openssl_public_encrypt($chunk, $encrypt_data, $this->publicKey);
            if (!$ok) return false;
            $encrypt_data .= $str;
        }
        return base64_encode($encrypt_data);
    }

    /**
     * 私钥分段解密
     * @param $data string 加密数据
     * @param int $max_decrypt_block 默认128
     * @return bool|string
     */
    public function decrypt(string $data,int $max_decrypt_block = RSA::RSA_DECRYPT_128)
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
     * @param string $signature_alg  加密算法
     * @return bool
     */
    public function isValid(string $data, string $signature,$signature_alg = RSA::OPENSSL_ALGO_SHA1)
    {
        $result = openssl_verify($data, base64_decode($signature), $this->publicKey, $signature_alg);
        if ($result === 1){
            return true;
        }
        return false;
    }

    /**
     * 格式化传入的公私钥
     *
     * @param $type
     * @param $key
     * @return string
     */
    private function transform($type, $key)
    {
        switch ($type)
        {
            case 'private':
                if (strpos($key,'BEGIN RSA PRIVATE KEY')){
                    $str = chunk_split($key, 64, "\n");
                    $key = "-----BEGIN RSA PRIVATE KEY-----\n$str-----END RSA PRIVATE KEY-----\n";
                }
                break;
            case 'public' :
                if (strpos($key,'BEGIN PUBLIC KEY')){
                    $str = chunk_split($key, 64, "\n");
                    $key = "-----BEGIN PUBLIC KEY-----\n$str-----END PUBLIC KEY-----\n";
                }
                break;
        }
        return $key;
    }
}
