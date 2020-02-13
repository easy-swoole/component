<?php


namespace EasySwoole\Component;


use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class ChannelLock
{
    protected $list;
    use Singleton;

    function lock(string $lockName,float $timeout = -1):bool
    {
        if(!isset($this->list[$lockName])){
            $this->list[$lockName] = new Channel(1);
        }
        /** @var Channel $channel */
        $channel = $this->list[$lockName];
        return $channel->push(1,$timeout);
    }

    function unlock(string $lockName,float $timeout = -1):bool
    {
        if(!isset($this->list[$lockName])){
            $this->list[$lockName] = new Channel(1);
        }
        /** @var Channel $channel */
        $channel = $this->list[$lockName];
        if($channel->isEmpty()){
            return true;
        }else{
            $channel->pop($timeout);
            return true;
        }
    }

    function deferLock(string $lockName,float $timeout = -1):bool
    {
        $lock = $this->lock($lockName,$timeout);
        if($lock){
            Coroutine::defer(function ()use($lockName){
               $this->unlock($lockName);
            });
        }
        return $lock;
    }

}