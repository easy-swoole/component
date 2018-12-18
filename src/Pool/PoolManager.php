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


    function register(string $className, $maxNum = 20):?PoolConf
    {
        $ref = new \ReflectionClass($className);
        if($ref->isSubclassOf(AbstractPool::class)){
            $conf = new PoolConf($className);
            $conf->setMaxObjectNum($maxNum);
            $this->pool[$this->generateKey($className)] = $conf;
            return $conf;
        }else{
            return null;
        }
    }

    /*
     * 请在进程克隆后，也就是worker start后，每个进程中独立使用
     */
    function getPool(string $className):?AbstractPool
    {
        $key = $this->generateKey($className);
        if(isset($this->pool[$key])){
            $item = $this->pool[$key];
            if($item instanceof AbstractPool){
                return $item;
            }else if($item instanceof PoolConf){
                $className = $item->getClass();
                $obj = new $className($item);
                $this->pool[$key] = $obj;
                return $obj;
            }
        }
        return null;
    }

    private function generateKey(string $class):string
    {
        return substr(md5($class), 8, 16);
    }
}