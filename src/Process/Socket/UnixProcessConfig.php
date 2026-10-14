<?php


namespace EasySwoole\Component\Process\Socket;


use EasySwoole\Component\Process\Config;

class UnixProcessConfig extends Config
{
    protected ?string $socketFile = null;

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

    public function getSocketFile(): ?string
    {
        return $this->socketFile;
    }

    public function setSocketFile(?string $socketFile): void
    {
        $this->socketFile = $socketFile;
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
