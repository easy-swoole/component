<?php

namespace EasySwoole\Component\CoroutineRunner;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class ParallelJob
{
    protected Channel $queueJob;

    protected mixed $addTaskCall = null;

    protected mixed $taskCall = null;

    protected mixed $onException = null;

    protected bool $isFinish = false;

    protected bool $jobCallEmpty = false;

    public float $jobWaitTime = 0.01;


    function __construct(int $maxQueueSize = 1024)
    {
        $this->queueJob = new Channel($maxQueueSize);
    }

    function setAddTask(callable $func):ParallelJob
    {
        $this->addTaskCall = $func;
        return $this;
    }

    function setOnTask(callable $func):ParallelJob
    {
        $this->taskCall = $func;
        return $this;
    }

    function setOnException(callable $func):ParallelJob
    {
        $this->onException = $func;
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

        if($this->isFinish){
            return;
        }

        $this->isFinish = false;
        $this->jobCallEmpty = false;

        //单携程pop任务
        Coroutine::create(function ()use($maxJobTry){
            $trys = 0;
            while (!$this->isFinish){
                if($this->queueJob->isFull()){
                    Coroutine::sleep(0.001);
                    continue;
                }
                try {
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
                }catch (\Throwable $exception){
                    if($this->onException){
                        call_user_func($this->onException,$exception);
                    }else{
                        throw $exception;
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
                        try {
                            call_user_func($this->taskCall, $task);
                        }catch (\Throwable $exception){
                            if($this->onException){
                                call_user_func($this->onException,$exception,$task);
                            }else{
                                throw $exception;
                            }
                        }
                    }
                }
            });
        }
    }

    function isFinish():bool
    {
        return $this->isFinish;
    }

    function interrupt():void
    {
        $this->isFinish = true;
    }

    function flushJobQueue():bool
    {
        if($this->isFinish){
            while (!$this->queueJob->isEmpty()){
                $this->queueJob->pop();
            }
            return true;
        }
        return false;
    }

    /**
     * @throws \Exception
     */
    function sync(int $coroutineNum = 64, int $maxJobTry = 3, int $timeOut = -1):bool
    {
        $start = time();
        $this->exec($coroutineNum,$maxJobTry);
        while (!$this->isFinish){
            if($timeOut > 0){
                if(time() - $start > $timeOut){
                    $this->interrupt();
                    return false;
                }
            }
            Coroutine::sleep(0.01);
        }
        return $this->isFinish;
    }
}