<?php


namespace EasySwoole\Component\CoroutineRunner;


class Task
{
    protected int $timeout = 3;
    /** @var callable */
    protected mixed $call;
    /** @var callable|null */
    protected mixed $onSuccess = null;
    /** @var callable|null */
    protected mixed $onFail = null;
    /** @var float|null */
    protected ?float $startTime = null;

    protected mixed $result = null;

    function __construct(callable $call)
    {
        $this->call = $call;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return callable
     */
    public function getCall(): callable
    {
        return $this->call;
    }

    /**
     * @param callable $call
     */
    public function setCall(callable $call): void
    {
        $this->call = $call;
    }

    /**
     * @return callable
     */
    public function getOnSuccess():? callable
    {
        return $this->onSuccess;
    }

    /**
     * @param callable $onSuccess
     */
    public function setOnSuccess(callable $onSuccess): void
    {
        $this->onSuccess = $onSuccess;
    }

    /**
     * @return callable
     */
    public function getOnFail():? callable
    {
        return $this->onFail;
    }

    /**
     * @param callable $onFail
     */
    public function setOnFail(callable $onFail): void
    {
        $this->onFail = $onFail;
    }

    /**
     * @return float|null
     */
    public function getStartTime(): ?float
    {
        return $this->startTime;
    }

    /**
     * @param float $startTime
     */
    public function setStartTime(float $startTime): void
    {
        $this->startTime = $startTime;
    }

    /**
     * @return mixed
     */
    public function getResult():mixed
    {
        return $this->result;
    }

    /**
     * @param mixed $result
     */
    public function setResult(mixed $result): void
    {
        $this->result = $result;
    }
}
