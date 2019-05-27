<?php


namespace EasySwoole\Component\Process\Socket;


use EasySwoole\Component\Process\Config;

class TcpConfig extends Config
{
    protected $listenAddress = '0.0.0.0';
    protected $port;
    protected $asyncCallBack = true;

    /**
     * @return string
     */
    public function getListenAddress(): string
    {
        return $this->listenAddress;
    }

    /**
     * @param string $listenAddress
     */
    public function setListenAddress(string $listenAddress): void
    {
        $this->listenAddress = $listenAddress;
    }

    /**
     * @return mixed
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param mixed $port
     */
    public function setPort($port): void
    {
        $this->port = $port;
    }

    /**
     * @return bool
     */
    public function isAsyncCallBack(): bool
    {
        return $this->asyncCallBack;
    }

    /**
     * @param bool $asyncCallBack
     */
    public function setAsyncCallBack(bool $asyncCallBack): void
    {
        $this->asyncCallBack = $asyncCallBack;
    }

}