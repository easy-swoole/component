<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 01:41
 */

namespace EasySwoole\Component\Process;
use EasySwoole\Component\Timer;
use Swoole\Event;
use Swoole\Process;
use Swoole\Table;

abstract class AbstractProcess
{
    private Process $swooleProcess;
    private Config $config;
    private ?int $heartBeatTimerId = null;
    private bool $pipeEventRegistered = false;
    private bool $shutdownStarted = false;


    /**
     * name  args  false 2 true
     * AbstractProcess constructor.
     * @param string $processName
     * @param null $arg
     * @param bool $redirectStdinStdout
     * @param int $pipeType
     * @param bool $enableCoroutine
     */
    public function __construct(...$args)
    {
        $arg1 = array_shift($args);
        if($arg1 instanceof Config){
            $this->config = $arg1;
        }else{
            $this->config = new Config();
            $this->config->setProcessName($arg1);
            $arg = array_shift($args);
            $this->config->setArg($arg);
            $redirectStdinStdout = (bool)array_shift($args) ?: false;
            $this->config->setRedirectStdinStdout($redirectStdinStdout);
            $pipeType = array_shift($args);
            $pipeType = $pipeType === null ? Config::PIPE_TYPE_SOCK_DGRAM : $pipeType;
            $this->config->setPipeType($pipeType);
            $bool = array_shift($args);
            if($bool === null){
                $enableCoroutine = true;
            }else{
                $enableCoroutine = (bool)$bool;
            }
            $this->config->setEnableCoroutine($enableCoroutine);
        }
        // 必须在创建子进程前初始化共享进程表，否则子进程内新建的 Table 无法被父进程读取。
        Manager::getInstance();
        $this->swooleProcess = new Process(
            [$this, '__start'],
            $this->config->isRedirectStdinStdout(),
            $this->config->getPipeType(),
            $this->config->isEnableCoroutine()
        );
    }

    public function getProcess():Process
    {
        return $this->swooleProcess;
    }

    public function addTick(int $ms, callable $call): int
    {
        return Timer::getInstance()->loop(
            $ms,$call
        );
    }

    public function clearTick(int|string $timerIdOrName): bool
    {
        return Timer::getInstance()->clear($timerIdOrName);
    }

    public function delay(int $ms, callable $call): int
    {
        return Timer::getInstance()->after($ms,$call);
    }

    /*
     * 服务启动后才能获得到pid
     */
    public function getPid():?int
    {
        if(isset($this->swooleProcess->pid)){
            return $this->swooleProcess->pid;
        }else{
            return null;
        }
    }

    final public function __start(Process $process): void
    {
        $table = Manager::getInstance()->getProcessTable();
        $table->set($process->pid,[
            'pid'=>$process->pid,
            'name'=>$this->config->getProcessName(),
            'group'=>$this->config->getProcessGroup(),
            'startUpTime'=>time(),
            "hash"=>spl_object_hash($this->getProcess()),
            'customProcess'=>1
        ]);
        $this->heartBeatTimerId = \Swoole\Timer::tick(1000, function () use ($table, $process) {
            $table->set($process->pid,[
                'memoryUsage'=>memory_get_usage(),
                'memoryPeakUsage'=>memory_get_peak_usage(true),
                'lastHeartBeat'=>time()
            ]);
        });
        
        $banOS = ['Darwin','CYGWIN','WINNT'];
        $canSetProcessName = true;
        foreach ($banOS as $os){
            if (strpos(PHP_OS, $os) !== false) {
                $canSetProcessName = false;
                break;
            }
        }

        if($canSetProcessName && !empty($this->getProcessName())){
            $process->name($this->getProcessName());
        }
        // 无管道模式下 pipe 为无效描述符，不能注册到事件循环。
        if ($this->config->getPipeType() !== Config::PIPE_TYPE_NONE && $process->pipe >= 0) {
            Event::add($process->pipe, function () use ($process) {
                try {
                    $this->onPipeReadable($process);
                } catch (\Throwable $throwable) {
                    $this->onException($throwable, 'pipe_readable', $process);
                }
            });
            $this->pipeEventRegistered = true;
        }
        // SIGTERM 的清理顺序固定为：业务信号钩子、关闭钩子、停止事件循环。
        Process::signal(SIGTERM, function () use ($table, $process) {
            try{
                $this->onSigTerm();
            }catch (\Throwable $throwable){
                $this->onException($throwable, 'sigterm', $process);
            } finally {
                try {
                    $this->shutdown($table, $process);
                } finally {
                    $this->stopEventLoop($process);
                }
            }
        });
        register_shutdown_function(function () use ($table, $process) {
            $this->shutdown($table, $process);
        });
        try{
            $this->run($this->config->getArg());
        }catch (\Throwable $throwable){
            $this->onException($throwable, 'run', $process);
        }
        //加了 Event::wait() 在开启协程的时候，可能会crash，
        //swoole v5.1.3 新版本为了防止出现这个情况，所以加了判断
        //v4 v5的swoole 均有这个问题
        if(!$this->config->isEnableCoroutine()){
            Event::wait();
        }
    }

    /**
     * 只注销当前组件注册的事件和心跳，避免误清理业务自行创建的定时器。
     */
    private function stopEventLoop(Process $process): void
    {
        if ($this->pipeEventRegistered) {
            Event::del($process->pipe);
            $this->pipeEventRegistered = false;
        }
        if ($this->heartBeatTimerId !== null) {
            \Swoole\Timer::clear($this->heartBeatTimerId);
            $this->heartBeatTimerId = null;
        }
        Process::signal(SIGTERM, null);
        Event::exit();
    }

    /**
     * 执行幂等关闭：删除进程表记录，并保证业务关闭钩子最多执行一次。
     */
    private function shutdown(Table $table, Process $process): void
    {
        if ($this->shutdownStarted) {
            return;
        }
        $this->shutdownStarted = true;
        $table->del((string)$process->pid);
        try {
            $this->onShutDown();
        } catch (\Throwable $throwable) {
            $this->onException($throwable, 'shutdown', $process);
        }
    }

    public function getArg()
    {
        return $this->config->getArg();
    }

    public function getProcessName()
    {
        return $this->config->getProcessName();
    }

    public function getConfig():Config
    {
        return $this->config;
    }

    protected function onException(\Throwable $throwable,...$args){
        throw $throwable;
    }

    protected abstract function run($arg);

    protected function onShutDown()
    {

    }

    protected function onSigTerm()
    {

    }

    protected function onPipeReadable(Process $process)
    {
        /*
         * 由于Swoole底层使用了epoll的LT模式，因此swoole_event_add添加的事件监听，
         * 在事件发生后回调函数中必须调用read方法读取socket中的数据，否则底层会持续触发事件回调。
         */
        $process->read();
    }
}
