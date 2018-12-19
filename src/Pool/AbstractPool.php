<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/6/22
 * Time: 下午1:21
 */

namespace EasySwoole\Component\Pool;


use EasySwoole\Component\Pool\Exception\PoolEmpty;
use EasySwoole\Component\Pool\Exception\PoolUnRegister;
use Swoole\Coroutine\Channel;

abstract class AbstractPool
{
    private $createdNum = 0;
    private $chan;
    private $objHash = [];
    private $conf;
    /*
     * 如果成功创建了,请返回对应的obj
     */
    abstract protected function createObject() ;

    public function __construct(PoolConf $conf)
    {
        $this->conf = $conf;
        $this->chan = new Channel($conf->getMaxObjectNum() + 1);
        if($conf->getIntervalCheckTime() > 0){
            swoole_timer_tick($conf->getIntervalCheckTime(),[$this,'intervalCheck']);
        }
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
                $this->chan->push($obj);
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getObj(float $timeout = null,int $tryTimes = 3)
    {
        if($timeout === null){
            $timeout = $this->conf->getGetObjectTimeout();
        }
        if($tryTimes <= 0){
            return null;
        }
        //懒惰创建模式
        $obj = null;
        if($this->chan->isEmpty()){
            //如果还没有达到最大连接数，则尝试进行创建
            if($this->createdNum < $this->conf->getMaxObjectNum()){
                $this->createdNum++;
                /*
                 * 创建对象的时候，请加try,尽量不要抛出异常
                 */
                $obj = $this->createObject();
                if(!is_object($obj)){
                    $this->createdNum--;
                    //创建失败，同样进入调度等待
                    $obj = $this->chan->pop($timeout);
                }
            }else{
                $obj = $this->chan->pop($timeout);
            }
        }else{
            $obj = $this->chan->pop($timeout);
        }
        //对对象进行标记处理
        if(is_object($obj)){
            $key = spl_object_hash($obj);
            //标记这个对象已经出队列了
            $this->objHash[$key] = true;
            $obj->last_use_time = time();
            if($obj instanceof PoolObjectInterface){
                $status = false;
                try{
                    $status = $obj->beforeUse();
                }catch (\Throwable $throwable){

                }
                if($status == false){
                    $this->unsetObj($obj);
                    //重新进入对象获取
                    return $this->getObj($timeout,$tryTimes - 1);
                }
            }
            return $obj;
        }else{
            return null;
        }
    }

    /*
     * 彻底释放一个对象
     */
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
            if(!$this->chan->isEmpty()){
                $obj = $this->chan->pop(0.001);
                if(is_object($obj)){
                    if(time() - $obj->last_recycle_time > $idleTime){
                        $this->unsetObj($obj);
                    }else{
                        array_push($list,$obj);
                    }
                }
            }else{
                break;
            }
        }
        foreach ($list as $item){
            $this->chan->push($item);
        }
    }

    protected function intervalCheck()
    {
        $this->gcObject($this->conf->getMaxIdleTime());
    }

    protected function getPoolConfig():PoolConf
    {
        return $this->conf;
    }

    public static function invoke(callable $call,float $timeout = null)
    {
        $pool = PoolManager::getInstance()->getPool(static::class);
        if($pool instanceof AbstractPool){
            $obj = $pool->getObj($timeout);
            if($obj){
                try{
                    $ret = call_user_func($call,$obj);
                    return $ret;
                }catch (\Throwable $throwable){
                    throw $throwable;
                }finally{
                    $pool->recycleObj($obj);
                }
            }else{
                throw new PoolEmpty(static::class." pool is empty");
            }
        }else{
            throw new PoolUnRegister(static::class." pool is unregister");
        }
    }

    /*
     * 用以解决冷启动问题
     */
    public function preLoad(int $num):int
    {
        if($this->conf->getMaxObjectNum() > $num){
            $success = 0;
            $t = time();
            for ($i= 0;$i < $num;$i++){
                $this->createdNum++;
                $ret = $this->createObject();
                if(is_object($ret)){
                    $ret->last_recycle_time = $t;
                    $ret->last_use_time = $t;
                    $this->chan->push($ret);
                    $success++;
                }else{
                    $this->createdNum--;
                }
            }
            return $success;
        }else{
            throw new \Exception("preLoad num:{$num} must small then max object num:{$this->conf->getMaxObjectNum()} for Pool class:{$this->conf->getClass()}");
        }
    }

}
