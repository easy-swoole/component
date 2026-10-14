<?php


namespace EasySwoole\Component\Process\Socket;


use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Exception;
use Swoole\Coroutine\Socket;
use Swoole\Coroutine;

abstract class AbstractTcpProcess extends AbstractProcess
{
    protected bool $isShutDown = false;

    protected Socket $socketServer;

    function __construct(TcpProcessConfig $config)
    {
        $config->setEnableCoroutine(true);
        if(empty($config->getListenPort())){
            throw new Exception("listen port empty at class ".static::class);
        }
        parent::__construct($config);
    }

    public function run($arg)
    {
        $socketServer = new Socket(AF_INET,SOCK_STREAM,0);
        $socketServer->setOption(SOL_SOCKET,SO_REUSEPORT,true);
        $socketServer->setOption(SOL_SOCKET,SO_REUSEADDR,true);
        $socketServer->setOption(SOL_SOCKET,SO_LINGER,$this->getConfig()->getLinger());
        $ret = $socketServer->bind($this->getConfig()->getListenAddress(),$this->getConfig()->getListenPort());
        if(!$ret){
            throw new Exception(static::class." bind {$this->getConfig()->getListenAddress()}:{$this->getConfig()->getListenPort()} fail case ".$socketServer->errMsg);
        }
        $ret = $socketServer->listen(2048);
        if(!$ret){
            throw new Exception(static::class." listen {$this->getConfig()->getListenAddress()}:{$this->getConfig()->getListenPort()} fail case ".$socketServer->errMsg);
        }
        $this->socketServer = $socketServer;
        while (!$this->isShutDown) {
            // 使用短超时轮询，使 SIGTERM 到来后可以及时检查关闭状态并退出。
            $client = $this->socketServer->accept(0.1);
            if(!$client){
                continue;
            }
            if($this->getConfig()->isAsyncCallback()){
                Coroutine::create(function ()use($client){
                    try{
                        $this->onAccept($client);
                    }catch (\Throwable $throwable){
                        $this->onException($throwable,$client);
                    }
                });
            }else{
                try{
                    $this->onAccept($client);
                }catch (\Throwable $throwable){
                    $this->onException($throwable,$client);
                }
            }
        }
    }

    abstract function onAccept(Socket $socket);

    protected function onShutDown(): void
    {
        $this->isShutDown = true;
        // SIGTERM 可能在监听 Socket 完成初始化前到达。
        if (isset($this->socketServer)) {
            $this->socketServer->shutdown();
        }
    }
}
