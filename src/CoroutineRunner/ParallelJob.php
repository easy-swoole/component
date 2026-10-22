<?php

namespace EasySwoole\Component\CoroutineRunner;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class ParallelJob
{
    protected Channel $queueJob;

    /** @var callable(): mixed|null */
    protected mixed $taskProducer = null;

    /** @var callable(mixed): mixed|null */
    protected mixed $taskHandler = null;

    protected mixed $onException = null;

    protected bool $isFinish = false;

    protected bool $interrupt = false;

    protected bool $jobCallEmpty = false;

    public float $jobWaitTime = 0.01;


    function __construct(int $maxQueueSize = 1024)
    {
        $this->queueJob = new Channel($maxQueueSize);
    }

    /** 设置生产者；返回 null 表示本次没有任务。 */
    function setTaskProducer(callable $func):ParallelJob
    {
        $this->taskProducer = $func;
        return $this;
    }

    /** 设置消费者，接收生产者返回的非 null 任务值。 */
    function setTaskHandler(callable $func):ParallelJob
    {
        $this->taskHandler = $func;
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
        if(empty($this->taskProducer)){
            throw new \Exception('taskProducer cannot be null');
        }

        if(empty($this->taskHandler)){
            throw new \Exception('taskHandler cannot be null');
        }

        if($this->isFinish){
            return;
        }

        $this->isFinish = false;
        $this->jobCallEmpty = false;
        $this->interrupt = false;

        // 单协程从生产者获取任务
        Coroutine::create(function ()use($maxJobTry){
            $trys = 0;
            while (!$this->isFinish){
                if($this->queueJob->isFull()){
                    Coroutine::sleep(0.001);
                    continue;
                }
                try {
                    $job = call_user_func($this->taskProducer);
                    if($job !== null){
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
                if($this->interrupt){
                    break;
                }
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
                    if($this->queueJob->errCode === 0){
                        try {
                            call_user_func($this->taskHandler, $task);
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
        $this->interrupt = true;
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