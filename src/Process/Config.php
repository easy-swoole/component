<?php


namespace EasySwoole\Component\Process;


use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    const PIPE_TYPE_NONE = 0;
    const PIPE_TYPE_SOCK_STREAM = 1;
    const PIPE_TYPE_SOCK_DGRAM = 2;

    protected ?string $processName = null;

    protected ?string $processGroup = null;

    protected mixed $arg = null;

    protected bool $redirectStdinStdout = false;

    protected int $pipeType = self::PIPE_TYPE_SOCK_DGRAM;

    protected bool $enableCoroutine = true;

    public function getProcessName(): ?string
    {
        return $this->processName;
    }

    public function setProcessName(?string $processName): void
    {
        $this->processName = $processName;
    }

    public function getArg(): mixed
    {
        return $this->arg;
    }

    public function setArg(mixed $arg): void
    {
        $this->arg = $arg;
    }

    public function isRedirectStdinStdout(): bool
    {
        return $this->redirectStdinStdout;
    }

    public function setRedirectStdinStdout(bool $redirectStdinStdout): void
    {
        $this->redirectStdinStdout = $redirectStdinStdout;
    }

    public function getPipeType(): int
    {
        return $this->pipeType;
    }

    public function setPipeType(int $pipeType): void
    {
        if (!in_array($pipeType, [
            self::PIPE_TYPE_NONE,
            self::PIPE_TYPE_SOCK_STREAM,
            self::PIPE_TYPE_SOCK_DGRAM,
        ], true)) {
            throw new \InvalidArgumentException("Invalid process pipe type: {$pipeType}");
        }
        $this->pipeType = $pipeType;
    }

    public function isEnableCoroutine(): bool
    {
        return $this->enableCoroutine;
    }

    public function setEnableCoroutine(bool $enableCoroutine): void
    {
        $this->enableCoroutine = $enableCoroutine;
    }

    public function getProcessGroup(): ?string
    {
        return $this->processGroup;
    }

    public function setProcessGroup(?string $processGroup): void
    {
        $this->processGroup = $processGroup;
    }
}
