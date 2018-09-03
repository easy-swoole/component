<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/26
 * Time: 上午12:54
 */

namespace EasySwoole\Component\Pool;


use EasySwoole\Component\Singleton;

class PoolManager
{
    use Singleton;

    private $pool = [];

    /**
     * @param string $className
     * @param int $maxNum
     * @return bool
     * @throws \Throwable
     */
    function register(string $className, $maxNum = 20):bool
    {
        $ref = new \ReflectionClass($className);
        if($ref->isSubclassOf(AbstractPool::class)){
            $this->pool[$this->generateKey($className)] = [$className,$maxNum];
            return true;
        }else{
            return false;
        }
    }

    function getPool(string $className):?AbstractPool
    {
        $key = $this->generateKey($className);
        if(isset($this->pool[$key]) && is_array($this->pool[$key])){
            $className = $this->pool[$key][0];
            $max = $this->pool[$key][1];
            $obj = new $className($max);
            $this->pool[$key] = $obj;
            return $obj;
        }else if(isset($this->pool[$key])){
            return  $this->pool[$key];
        }else{
            return null;
        }
    }

    private function generateKey(string $class):string
    {
        return substr(md5($class), 8, 16);
    }
}