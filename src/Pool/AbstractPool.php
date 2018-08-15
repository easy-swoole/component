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
    private $objHash = [];

    /*
     * 如果成功创建了,请返回对应的obj
     */
    abstract protected function createObject() ;

    public function __construct($maxNum = 10)
    {
        $this->queue = new \SplQueue();
        $this->chan = new Channel(128);
        $this->max = $maxNum;
    }

    /*
     * 回收一个对象
     */
    public function recycleObj($obj):bool
    {
        if(is_object($obj)){
            //防止一个对象被重复入队列。
            $key = spl_object_hash($obj);
            if(isset($this->objHash[$key])){
                //标记这个对象已经入队列了
                unset($this->objHash[$key]);
                if($obj instanceof PoolObjectInterface){
                    $obj->objectRestore();
                }
                $obj->last_recycle_time = time();
                $this->queue->enqueue($obj);
                $this->chan->push(1);
                return true;
            }else{
                return false;
            }
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
                    $key = spl_object_hash($obj);
                    //标记这个对象已经出队列了
                    $this->objHash[$key] = true;
                    //清空channel
                    while (!$this->queue->isEmpty()){
                        $this->queue->pop(0.00001);
                    }
                    $obj->last_use_time = time();
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
                        $obj =  $this->queue->dequeue();
                        $key = spl_object_hash($obj);
                        //标记这个对象已经出队列了
                        $this->objHash[$key] = true;
                        $obj->last_use_time = time();
                        return $obj;
                    }
                }else{
                    return null;
                }
            }
        }else{
            //队列不为空，说明有出现recycle，recycle的时候，有push，请务必pop清除
            $this->chan->pop($timeout);
            $obj = $this->queue->dequeue();
            //标记这个对象已经出队列了
            $key = spl_object_hash($obj);
            $this->objHash[$key] = true;
            $obj->last_use_time = time();
            return $obj;
        }
    }

    public function unsetObj($obj):bool
    {
        if(is_object($obj)){
            $key = spl_object_hash($obj);
            if($obj instanceof PoolObjectInterface){
                $obj->objectRestore();
                $obj->gc();
            }
            if(isset($this->objHash[$key])){
                unset($this->objHash[$key]);
            }
            unset($obj);
            $this->createdNum--;
            return true;
        }else{
            return false;
        }
    }

    /*
     * 超过$idleTime未出队使用的，将会被回收。
     */
    public function gcObject(int $idleTime)
    {
        $list = [];
        while (true){
            if(!$this->queue->isEmpty()){
                $obj = $this->queue->pop();
                if($obj->last_recycle_time - $obj->last_use_time > $idleTime){
                    $this->unsetObj($obj);
                }else{
                    array_push($list,$obj);
                }
            }else{
                break;
            }
        }
        foreach ($list as $item){
            $this->queue->push($item);
        }
    }

}