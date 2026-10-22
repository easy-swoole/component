<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/25
 * Time: 下午12:42
 */

namespace EasySwoole\Component;


class MultiContainer
{
    private array $container = [];
    private ?array $allowKeys = null;

    function __construct(array|null $allowKeys = null)
    {
        $this->allowKeys = $allowKeys;
    }

    function add(int|string $key,mixed $item):static|bool
    {
        if(is_array($this->allowKeys) && !in_array($key,$this->allowKeys)){
            return false;
        }
        $this->container[$key][] = $item;
        return $this;
    }

    function set(int|string $key,mixed $item):static|bool
    {
        if(is_array($this->allowKeys) && !in_array($key,$this->allowKeys)){
            return false;
        }
        $this->container[$key] = [$item];
        return $this;
    }

    function delete(int|string $key):static
    {
        if(isset($this->container[$key])){
            unset($this->container[$key]);
        }
        return $this;
    }

    function get(int|string $key):?array
    {
        if(isset($this->container[$key])){
            return $this->container[$key];
        }else{
            return null;
        }
    }

    function all():array
    {
        return $this->container;
    }

    function clear():void
    {
        $this->container = [];
    }
}