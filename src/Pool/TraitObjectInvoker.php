<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-06
 * Time: 19:22
 */

namespace EasySwoole\Component\Pool;


use EasySwoole\Component\Pool\Exception\PoolEmpty;
use EasySwoole\Component\Pool\Exception\PoolUnRegister;

trait TraitObjectInvoker
{
    public static function invoke(callable $call,float $timeout = null)
    {
        $pool = PoolManager::getInstance()->getPool(static::class);
        if($pool instanceof AbstractPool){
            $obj = $pool->getObj($timeout);
            if($obj){
                try{
                    $ret = call_user_func($call,$obj);
                    return $ret;
                }catch (\Throwable $throwable){
                    throw $throwable;
                }finally{
                    $pool->recycleObj($obj);
                }
            }else{
                throw new PoolEmpty(static::class." pool is empty");
            }
        }else{
            throw new PoolUnRegister(static::class." pool is unregister");
        }
    }
}