<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/24
 * Time: 上午12:38
 */

namespace EasySwoole\Component;
use Swoole\Coroutine as Co;

class Context
{
    use Singleton;

    private $register = [];
    private $context = [];

    function register(string $name,$object)
    {
        $this->register[$name] = $object;
        return $this;
    }

    /**
     * @param string $name
     * @param null $cid
     * @return mixed|null
     * @throws \Throwable
     */
    function get(string $name, $cid = null,...$params)
    {
        if($cid === null){
            $cid = Co::getUid();
        }
        if(isset($this->context[$cid][$name])){
            return $this->context[$cid][$name];
        }else{
            if(isset($this->register[$name])){
                $obj = $this->register[$name];
                if(is_object($obj) || is_callable($obj)){
                    return $obj;
                }else if(is_string($obj) && class_exists($obj)){
                    try{
                        $this->context[$cid][$name] = new $obj(...$params);
                        return $this->context[$cid][$name];
                    }catch (\Throwable $throwable){
                        throw $throwable;
                    }
                }else{
                    return $obj;
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