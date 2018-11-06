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


    function register(string $className, $maxNum = 20,$intervalCheckTime = 30*1000,$idleGCTime = 15):bool
    {
        $ref = new \ReflectionClass($className);
        if($ref->isSubclassOf(AbstractPool::class)){
            $this->pool[$this->generateKey($className)] = [$className,$maxNum,$intervalCheckTime,$idleGCTime];
            return true;
        }else{
            return false;
        }
    }

    /*
     * 请在进程克隆后，也就是worker start后，每个进程中独立使用
     */
    function getPool(string $className):?AbstractPool
    {
        $key = $this->generateKey($className);
        if(isset($this->pool[$key]) && is_array($this->pool[$key])){
            $args = $this->pool[$key];
            $className = array_shift($args);
            $obj = new $className(...$args);
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