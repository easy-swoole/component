<?php


namespace EasySwoole\Component\Process\Socket;


use EasySwoole\Component\Process\Config;

class UnixConfig extends Config
{
    protected $socketFile;
    protected $asyncCallBack = true;

    /**
     * @return mixed
     */
    public function getSocketFile()
    {
        return $this->socketFile;
    }

    /**
     * @param mixed $socketFile
     */
    public function setSocketFile($socketFile): void
    {
        $this->socketFile = $socketFile;
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