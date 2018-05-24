<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/24
 * Time: 下午4:12
 */

namespace EasySwoole\Component;

use \Swoole\Process;

class Invoker
{
    public static function exec(callable $callable,$timeOut = 100 * 1000,...$params)
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, function () {
            Process::alarm(-1);
            throw new \RuntimeException('func timeout');
        });
        try
        {
            Process::alarm($timeOut);
            $ret = self::callUserFunc($callable,...$params);
            Process::alarm(-1);
            return $ret;
        }
        catch(\Throwable $throwable)
        {
            throw $throwable;
        }
    }


    public static function callUserFunc(callable $callable,...$params)
    {
        if(SWOOLE_VERSION >1){
            if($callable instanceof \Closure){
                return $callable(...$params);
            }else if(is_array($callable) && is_object($callable[0])){
                $class = $callable[0];
                $method = $callable[1];
                return $class->$method(...$params);
            }else if(is_array($callable) && is_string($callable[0])){
                $class = $callable[0];
                $method = $callable[1];
                return $class::$method(...$params);
            }else if(is_string($callable)){
                return $callable(...$params);
            }else{
                return null;
            }
        }else{
            return call_user_func($callable,...$params);
        }
    }

    public static function callUserFuncArray(callable $callable,array $params)
    {
        if(SWOOLE_VERSION > 1){
            if($callable instanceof \Closure){
                return $callable(...$params);
            }else if(is_array($callable) && is_object($callable[0])){
                $class = $callable[0];
                $method = $callable[1];
                return $class->$method(...$params);
            }else if(is_array($callable) && is_string($callable[0])){
                $class = $callable[0];
                $method = $callable[1];
                return $class::$method(...$params);
            }else if(is_string($callable)){
                return $callable(...$params);
            }else{
                return null;
            }
        }else{
            return call_user_func_array($callable,$params);
        }
    }
}