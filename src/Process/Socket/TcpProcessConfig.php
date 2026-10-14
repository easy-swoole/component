<?php


namespace EasySwoole\Component\Process\Socket;


use EasySwoole\Component\Process\Config;

class TcpProcessConfig extends Config
{
    protected string $listenAddress = '0.0.0.0';

    protected ?int $listenPort = null;

    protected bool $asyncCallback = true;

    /** @var array{l_linger: int, l_onoff: int} */
    protected array $linger = ['l_linger' => 0, 'l_onoff' => 0];

    /**
     * @return array{l_linger: int, l_onoff: int}
     */
    public function getLinger(): array
    {
        return $this->linger;
    }

    /**
     * @param array{l_linger: int, l_onoff: int} $linger
     */
    public function setLinger(array $linger): void
    {
        $this->linger = $linger;
    }

    public function getListenAddress(): string
    {
        return $this->listenAddress;
    }

    public function setListenAddress(string $listenAddress): void
    {
        $this->listenAddress = $listenAddress;
    }

    public function getListenPort(): ?int
    {
        return $this->listenPort;
    }

    public function setListenPort(?int $listenPort): void
    {
        $this->listenPort = $listenPort;
    }
    public function isAsyncCallback(): bool
    {
        return $this->asyncCallback;
    }

    public function setAsyncCallback(bool $asyncCallback): void
    {
        $this->asyncCallback = $asyncCallback;
    }
}
