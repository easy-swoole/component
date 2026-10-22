<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 15:53
 */

namespace EasySwoole\Component;


use Swoole\Atomic;
use Swoole\Atomic\Long;


class AtomicManager
{
    use Singleton;

    private array $list = [];
    private array $listForLong = [];

    function add(int|string $name,int $int = 0):void
    {
        if(!isset($this->list[$name])){
            $a = new Atomic($int);
            $this->list[$name] = $a;
        }
    }

    function addLong(int|string $name,int $int = 0):void
    {
        if(!isset($this->listForLong[$name])){
            $a = new Long($int);
            $this->listForLong[$name] = $a;
        }
    }

    function getLong(int|string $name):?Long
    {
        if(isset($this->listForLong[$name])){
            return $this->listForLong[$name];
        }else{
            return null;
        }
    }

    function get(int|string $name):?Atomic
    {
        if(isset($this->list[$name])){
            return $this->list[$name];
        }else{
            return null;
        }
    }
}
