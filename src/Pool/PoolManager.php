<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/26
 * Time: 上午12:54
 */

namespace EasySwoole\Component\Pool;


use EasySwoole\Component\Singleton;
use EasySwoole\Trigger\Trigger;

class PoolManager
{
    use Singleton;

    private $pool = [];

    function register(string $className,$maxNum = 20):bool
    {
        try{
            $ref = new \ReflectionClass($className);
            if($ref->isSubclassOf(AbstractPool::class)){
                $this->pool[$this->generateKey($className)] = new $className($maxNum);
                return true;
            }
        }catch (\Throwable $throwable){
            Trigger::throwable($throwable);
        }
        return false;
    }

    function getPool(string $className):?AbstractPool
    {
        $key = $this->generateKey($className);
        if(isset($this->pool[$key])){
            return $this->pool[$key];
        }else{
            return null;
        }
    }

    private function generateKey(string $class):string
    {
        return substr(md5($class), 8, 16);
    }
}