<?php


namespace EasySwoole\Component;


use Swoole\Coroutine;
use Swoole\Table;

class ReadyScheduler
{
    use Singleton;

    const STATUS_UNREADY = 0;
    const STATUS_READY = 1;

    private Table $table;

    function __construct(int $maxItemCount = 2048)
    {
        $this->table = new Table($maxItemCount);
        $this->table->column('status',Table::TYPE_INT,1);
        $this->table->create();
    }

    function addItem(string $key):bool
    {
        return $this->table->set($key,[
            'status'=>self::STATUS_UNREADY
        ]);
    }

    function dropItem(string $key):bool
    {
        return $this->table->del($key);
    }

    function status(string $key):?int
    {
        $ret = $this->table->get($key);
        if($ret){
            return $ret['status'];
        }else{
            return null;
        }
    }

    function ready(string $key): bool
    {
        if(!$this->table->exists($key)){
            return false;
        }
        return $this->table->set($key,[
            'status'=>self::STATUS_READY
        ]);
    }

    function unready(string $key):bool
    {
        if(!$this->table->exists($key)){
            return false;
        }
        return $this->table->set($key,[
            'status'=>self::STATUS_UNREADY
        ]);
    }

    function restore(string $key):bool
    {
        if(!$this->table->exists($key)){
            return false;
        }
        return $this->table->set($key,[
            'status'=>self::STATUS_UNREADY
        ]);
    }

    function waitReady(array $keys,float $time = 3.0):bool
    {
        if(empty($keys)){
            return true;
        }
        $deadline = $this->deadline($time);
        while (1){
            $allReady = true;
            foreach ($keys as $item){
                if($this->status($item) < self::STATUS_READY){
                    $allReady = false;
                    break;
                }
            }
            if($allReady){
                return true;
            }
            if(!$this->sleepUntilNextCheck($deadline)){
                return false;
            }
        }
    }

    function waitAnyReady(array $keys,float $timeout = 3.0):bool
    {
        if(empty($keys)){
            return true;
        }
        $deadline = $this->deadline($timeout);
        while (1){
            foreach ($keys as $key){
                if($this->status($key) >= self::STATUS_READY){
                    return true;
                }
            }
            if(!$this->sleepUntilNextCheck($deadline)){
                return false;
            }
        }
    }

    /**
     * 使用单调时钟计算截止时间，避免系统时间变化影响超时判断。
     */
    private function deadline(float $timeout): int
    {
        return hrtime(true) + (int) max(0, $timeout * 1_000_000_000);
    }

    /**
     * 休眠到下一次轮询；到达截止时间后返回 false。
     */
    private function sleepUntilNextCheck(int $deadline): bool
    {
        $remainingNanoseconds = $deadline - hrtime(true);
        if($remainingNanoseconds <= 0){
            return false;
        }

        $sleepSeconds = min(0.01, $remainingNanoseconds / 1_000_000_000);
        // Swoole sleep 的最小有效值为 1ms，最后一轮最多额外等待不足 1ms。
        Coroutine::sleep(max(0.001, $sleepSeconds));
        return true;
    }

}
