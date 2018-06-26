<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/6/22
 * Time: 下午1:21
 */

namespace EasySwoole\Component\Pool;


use EasySwoole\Component\Singleton;
use Swoole\Coroutine as co;

abstract class AbstractPool
{
    use Singleton;

    protected $queue;
    protected $max = 10;
    protected $createdNum = 0;
    protected $waitList = [];

    /*
     * 如果成功创建了,请调用recycleObj 将创建成功的对象放置到队列中
     */
    abstract protected function createObject():bool ;

    public function __construct()
    {
        $this->queue = new \SplQueue();
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
            $cid = array_shift($this->waitList);
            if($cid){
                co::resume($cid);
            }
            return true;
        }else{
            return false;
        }
    }

    public function getObj($waitSchedule = true)
    {
        //懒惰创建模式
        if($this->queue->isEmpty()){
            if($this->createdNum < $this->max){
                $this->createdNum++;
                if($this->createObject()){
                    return $this->queue->pop();
                }else{
                    $this->createdNum--;
                }
            }
            //进入调度
            if($waitSchedule !== false){
                return null;
            }
            $cid = co::getUid();
            array_push($this->waitList,$cid);
            co::suspend();
            return $this->queue->pop();
        }else{
            return $this->queue->pop();
        }
    }
}