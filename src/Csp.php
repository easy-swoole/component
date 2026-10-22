<?php


namespace EasySwoole\Component;


use Swoole\Coroutine\Channel;
use Swoole\Coroutine;

class Csp
{
    private Channel $chan;
    private int $count = 0;
    private int $success = 0;
    private array $task = [];

    function __construct(int $size = 64)
    {
        $this->chan = new Channel($size);
    }

    function add(int|string $itemName,callable $call):Csp
    {
        $this->count = 0;
        $this->success = 0;
        $this->task[$itemName] = $call;
        return $this;
    }

    function successNum():int
    {
        return $this->success;
    }

    function exec(?float $timeout = 5):array
    {
        if($timeout <= 0){
            $timeout = PHP_INT_MAX;
        }
        $this->count = count($this->task);
        foreach ($this->task as $key => $call){
            Coroutine::create(function ()use($key,$call){
                $data = call_user_func($call);
                $this->chan->push([
                    'key'=>$key,
                    'result'=>$data
                ]);
            });
        }
        $result = [];
        $start = microtime(true);
        while($this->count > 0)
        {
            $temp = $this->chan->pop(1);
            if(is_array($temp)){
                $key = $temp['key'];
                $result[$key] = $temp['result'];
                $this->count--;
                $this->success++;
            }
            if(microtime(true) - $start > $timeout){
                break;
            }
        }
        return $result;
    }
}