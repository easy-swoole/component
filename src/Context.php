<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/24
 * Time: 上午12:38
 */

namespace EasySwoole\Component;
use EasySwoole\Trigger\Trigger;
use Swoole\Coroutine as Co;

class Context
{
    use Singleton;

    private $register = [];
    private $context = [];

    function register(string $name,callable $call)
    {
        $this->register[$name] = $call;
        return $this;
    }

    function get(string $name,$cid = null)
    {
        if($cid === null){
            $cid = Co::getUid();
        }
        if(isset($this->context[$cid][$name])){
            return $this->context[$cid][$name];
        }else{
            if(isset($this->register[$name])){
                $call = $this->register[$name];
                try{
                    $res = call_user_func($call);
                    $this->context[$cid][$name] = $res;
                    return $res;
                }catch (\Throwable $throwable){
                    Trigger::throwable($throwable);
                    return null;
                }
            }else{
                return null;
            }
        }
    }

    function set(string $name,$obj,$cid = null):Context
    {
        if($cid === null){
            $cid = Co::getUid();
        }
        $this->context[$cid][$name] = $obj;
        return $this;
    }

    function clear($cid = null):Context
    {
        if($cid === null){
            $cid = Co::getUid();
        }
        unset($this->context[$cid]);
        return $this;
    }

    function clearAll():Context
    {
        $this->context = [];
        return $this;
    }
}