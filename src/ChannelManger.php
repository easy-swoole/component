<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 15:54
 */

namespace EasySwoole\Component;


use Swoole\Coroutine\Channel;

class ChannelManger
{
    use Singleton;
    private array $list = [];

    function add(int|string $name,int $size = 1024):void
    {
        if(!isset($this->list[$name])){
            $chan = new Channel($size);
            $this->list[$name] = $chan;
        }
    }

    function get(int|string $name):?Channel
    {
        if(isset($this->list[$name])){
            return $this->list[$name];
        }else{
            return null;
        }
    }
}