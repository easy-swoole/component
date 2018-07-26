<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/6/22
 * Time: 下午1:21
 */

namespace EasySwoole\Component\Pool;


use Swoole\Coroutine\Channel;

abstract class AbstractPool
{
    private $queue;
    protected $max;
    private $createdNum = 0;
    private $chan;

    /*
     * 如果成功创建了,请返回对应的obj
     */
    abstract protected function createObject() ;

    public function __construct($maxNum = 10)
    {
        $this->queue = new \SplQueue();
        $this->chan = new Channel();
        $this->max = $maxNum;
    }

    /*
     * 回收一个连接
     */
    public function recycleObj($obj):bool
    {
        if(is_object($obj)){
            if($obj instanceof AbstractObject){
                $obj->objectRestore();
            }
            $this->queue->enqueue($obj);
            $this->chan->push(1);
            return true;
        }else{
            return false;
        }
    }

    public function getObj(float $timeout = 0.1)
    {
        //懒惰创建模式
        if($this->queue->isEmpty()){
            if($this->createdNum < $this->max){
                $this->createdNum++;
                $obj = $this->createObject();
                if(is_object($obj)){
                    return $obj;
                }else{
                    $this->createdNum--;
                }
            }
            while (true){
                /*
                 * 如果上一个任务超时，没有pop成功，而归还任务的时候，会导致chan数据不为空，但队列无数据。
                 * 仅仅利用channel用于通知超时调度
                 */
                $res = $this->chan->pop($timeout);
                if($res > 0){
                    if(!$this->queue->isEmpty()){
                        return $this->queue->dequeue();
                    }
                }else{
                    return null;
                }
            }
        }else{
            //队列不为空，说明有出现recycle，recycle的时候，有push，请务必pop清除
            $this->chan->pop($timeout);
            return $this->queue->dequeue();
        }
    }

    public function unsetObj($obj):bool
    {
        if(is_object($obj)){
            if($obj instanceof AbstractObject){
                $obj->objectRestore();
            }
            unset($obj);
            return true;
        }else{
            return false;
        }
    }
}