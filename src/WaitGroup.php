<?php

declare(strict_types=1);

namespace EasySwoole\Component;

use LogicException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class WaitGroup
{
    private int $size;
    private ?Channel $channel = null;
    private array $tasks = [];
    private int $taskCount = 0;
    private int $doneCount = 0;
    private int $success = 0;
    private bool $started = false;
    private bool $waited = false;

    public function __construct(int $size = 128)
    {
        if ($size <= 0) {
            throw new \InvalidArgumentException('WaitGroup capacity must be greater than 0');
        }
        $this->size = $size;
    }

    /**
     * 登记一个任务，但不会立即执行。传入回调的任务会在 start() 或 wait() 时创建协程。
     */
    public function add(?callable $func = null): void
    {
        if ($this->started) {
            throw new LogicException('WaitGroup has already started; tasks cannot be added');
        }

        $this->taskCount++;
        if ($func !== null) {
            $this->tasks[] = $func;
        }
    }

    /**
     * 启动所有通过 add() 登记的回调。一个 WaitGroup 只能启动一次。
     */
    public function start(): void
    {
        if ($this->started) {
            throw new LogicException('WaitGroup::start() can only be called once');
        }

        $this->startTasks();
    }

    /**
     * 标记一个任务完成。done() 次数不能超过 add() 次数。
     */
    public function done(): void
    {
        if (!$this->started || $this->channel === null) {
            throw new LogicException('WaitGroup has not started; done() cannot be called');
        }
        if ($this->doneCount >= $this->taskCount) {
            throw new LogicException('WaitGroup::done() calls exceed add() calls');
        }

        $this->doneCount++;
        if (!$this->channel->push(true)) {
            $this->doneCount--;
            throw new LogicException('WaitGroup is closed; completion cannot be submitted');
        }
    }

    /**
     * 等待所有任务完成。未显式调用 start() 时，wait() 会自动启动一次。
     * 返回 true 表示全部完成，false 表示超时；一个 WaitGroup 只能等待一次。
     */
    public function wait(?float $timeout = 15): bool
    {
        if ($this->waited) {
            throw new LogicException('WaitGroup::wait() can only be called once');
        }
        $this->waited = true;

        if (!$this->started) {
            $this->startTasks();
        }

        $waitTimeout = $timeout === null || $timeout <= 0 ? PHP_INT_MAX : $timeout;
        $deadline = microtime(true) + $waitTimeout;
        $this->success = 0;

        while ($this->success < $this->taskCount) {
            $left = $deadline - microtime(true);
            if ($left <= 0 || $this->channel?->pop($left) !== true) {
                return false;
            }
            $this->success++;
        }

        return true;
    }

    /**
     * 返回最近一次 wait() 已确认完成的任务数。
     */
    public function successNum(): int
    {
        return $this->success;
    }

    /**
     * 返回尚未调用 done() 的任务数。
     */
    public function count(): int
    {
        return $this->taskCount - $this->doneCount;
    }

    private function startTasks(): void
    {
        $this->started = true;
        // 容量至少等于任务总数，确保显式 start() 后即使尚未 wait()，任务也不会阻塞在 done()。
        $this->channel = new Channel(max($this->size, $this->taskCount, 1));
        $tasks = $this->tasks;
        $this->tasks = [];

        foreach ($tasks as $task) {
            Coroutine::create(function () use ($task): void {
                try {
                    $task();
                } finally {
                    $this->done();
                }
            });
        }
    }

    public function __destruct()
    {
        if ($this->channel !== null) {
            $this->channel->close();
            $this->channel = null;
        }
        $this->tasks = [];
    }
}
