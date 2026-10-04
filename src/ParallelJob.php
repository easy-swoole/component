<?php

namespace EasySwoole\Component;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class ParallelJob
{
    protected Channel $queueJob;

    protected mixed $addTaskCall = null;

    protected mixed $taskCall = null;

    protected bool $isFinish = false;

    protected bool $jobCallEmpty = false;

    public float $jobWaitTime = 1.0;


    function __construct(int $maxQueueSize = 1024)
    {
        $this->queueJob = new Channel($maxQueueSize);
    }

    function setAddTask(callable $func):ParallelJob
    {
        $this->addTaskCall = $func;
        return $this;
    }

    function setTaskCall(callable $func):ParallelJob
    {
        $this->taskCall = $func;
        return $this;
    }


    /**
     * @throws \Exception
     */
    function exec(int $coroutineNum = 64, int $maxJobTry = 3):void
    {
        if(empty($this->addTaskCall)){
            throw new \Exception('addTaskCall cannot be null');
        }

        if(empty($this->taskCall)){
            throw new \Exception('taskCall cannot be null');
        }

        $this->isFinish = false;
        $this->jobCallEmpty = false;

        //单携程pop任务
        Coroutine::create(function ()use($maxJobTry){
            $trys = 0;
            while (!$this->isFinish){
                $job = call_user_func($this->addTaskCall);
                if($job){
                    $trys = 0;
                    $this->queueJob->push($job,-1);
                }else{
                    $trys++;
                    if($trys >= $maxJobTry){
                        $this->jobCallEmpty = true;
                        break;
                    }
                }
            }
        });

        Coroutine::create(function (){
            while (true){
                if($this->jobCallEmpty && $this->queueJob->isEmpty()){
                    $this->isFinish = true;
                    break;
                }else{
                    Coroutine::sleep(0.01);
                }
            }
        });

        for ($i = 0; $i < $coroutineNum; $i++) {
            Coroutine::create(function (){
                while (!$this->isFinish) {
                    $task = $this->queueJob->pop($this->jobWaitTime);
                    if(!empty($task)){
                        call_user_func($this->addTaskCall, $task);
                    }
                }
            });
        }
    }

    function isFinish():bool
    {
        return $this->isFinish;
    }

    /**
     * @throws \Exception
     */
    function sync(int $coroutineNum = 64, int $maxJobTry = 3, int $timeOut = -1):bool
    {
        $start = time();
        $this->exec($coroutineNum,$maxJobTry);
        while (!$this->isFinish){
            Coroutine::sleep(0.01);
            if($timeOut > 0){
                if(time() - $start > $timeOut){
                    break;
                }
            }
        }
        return $this->isFinish;
    }
}