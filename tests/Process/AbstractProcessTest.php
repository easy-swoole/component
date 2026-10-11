<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests\Process;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Config;
use EasySwoole\Component\Process\Manager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Swoole\Atomic;
use Swoole\Coroutine;
use Swoole\Process;

final class AbstractProcessTest extends TestCase
{
    public static function pipeTypes(): array
    {
        return [
            'stream' => [Config::PIPE_TYPE_SOCK_STREAM],
            'datagram' => [Config::PIPE_TYPE_SOCK_DGRAM],
        ];
    }

    public static function coroutineModes(): array
    {
        return [
            'coroutine disabled' => [false],
            'coroutine enabled' => [true],
        ];
    }

    #[DataProvider('pipeTypes')]
    public function testParentAndChildCanCommunicateInBothDirections(int $pipeType): void
    {
        $config = $this->config($pipeType);
        $process = (new EchoProcess($config))->getProcess();
        $pid = $this->start($process);

        try {
            $request = 'ping-' . bin2hex(random_bytes(4));
            self::assertNotFalse($process->write($request));
            self::assertSame('ack:' . $request, $this->readWithTimeout($process));
        } finally {
            $this->terminate($pid);
        }
    }

    public function testPipeTypeNoneStartsAndStopsCleanly(): void
    {
        $config = $this->config(Config::PIPE_TYPE_NONE);
        $process = (new IdleProcess($config))->getProcess();
        $pid = $this->start($process);

        usleep(50_000);
        self::assertTrue(Process::kill($pid, 0));

        $result = $this->terminate($pid);
        self::assertSame(0, $result['code']);
        self::assertSame(0, $result['signal']);
    }

    public function testProcessTableIsSharedAndCleanedOnShutdown(): void
    {
        $config = $this->config(Config::PIPE_TYPE_SOCK_DGRAM);
        $config->setProcessGroup('phpunit');
        $process = (new IdleProcess($config))->getProcess();
        $pid = $this->start($process);

        try {
            $info = $this->waitUntil(function () use ($pid) {
                return Manager::getInstance()->info($pid)[$pid] ?? null;
            });
            self::assertSame('phpunit-process', $info['name']);
            self::assertSame('phpunit', $info['group']);
        } finally {
            $this->terminate($pid);
        }

        $removed = $this->waitUntil(
            fn () => Manager::getInstance()->info($pid) === []
        );
        self::assertTrue($removed);
    }

    public function testShutdownHookRunsOnce(): void
    {
        $counter = new Atomic(0);
        $config = $this->config(Config::PIPE_TYPE_NONE);
        $config->setArg($counter);
        $process = (new ShutdownCountingProcess($config))->getProcess();
        $pid = $this->start($process);

        // 等待子进程完成信号处理器注册，避免启动后立即发送 SIGTERM 造成竞态。
        usleep(50_000);
        $this->terminate($pid);

        self::assertSame(1, $counter->get());
    }

    #[DataProvider('coroutineModes')]
    public function testShutdownHookCanCallCoroutineApi(bool $enableCoroutine): void
    {
        $counter = new Atomic(0);
        $config = $this->config(Config::PIPE_TYPE_NONE);
        $config->setEnableCoroutine($enableCoroutine);
        $config->setArg($counter);
        $process = (new CoroutineShutdownProcess($config))->getProcess();
        $pid = $this->start($process);

        // 等待子进程完成信号处理器注册，确保测试覆盖正常关闭路径。
        usleep(50_000);
        $result = $this->terminate($pid);

        self::assertSame(0, $result['code']);
        self::assertSame(0, $result['signal']);
        self::assertSame(1, $counter->get());
    }

    public function testLifecycleEntryPointCannotBeOverridden(): void
    {
        self::assertTrue((new ReflectionMethod(AbstractProcess::class, '__start'))->isFinal());
    }

    public function testClearTickReturnsBoolean(): void
    {
        $process = new IdleProcess($this->config(Config::PIPE_TYPE_NONE));
        \Swoole\Coroutine\run(function () use ($process): void {
            $timerId = $process->addTick(10_000, static function (): void {});
            self::assertTrue($process->clearTick($timerId));
            self::assertFalse($process->clearTick($timerId));
        });
    }

    private function config(int $pipeType): Config
    {
        $config = new Config();
        $config->setProcessName('phpunit-process');
        $config->setPipeType($pipeType);
        $config->setEnableCoroutine(false);
        return $config;
    }

    private function start(Process $process): int
    {
        $pid = $process->start();
        self::assertIsInt($pid);
        self::assertGreaterThan(0, $pid);
        return $pid;
    }

    private function readWithTimeout(Process $process, float $timeout = 2.0): string
    {
        $process->setBlocking(false);
        $deadline = microtime(true) + $timeout;
        do {
            $data = $process->read();
            if (is_string($data) && $data !== '') {
                return $data;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('等待子进程响应超时');
    }

    private function terminate(int $pid): array
    {
        if (Process::kill($pid, 0)) {
            Process::kill($pid, SIGTERM);
        }

        $result = Process::wait(true);
        self::assertIsArray($result);
        self::assertSame($pid, $result['pid']);
        return $result;
    }

    private function waitUntil(callable $condition, float $timeout = 2.0): mixed
    {
        $deadline = microtime(true) + $timeout;
        do {
            $result = $condition();
            if ($result) {
                return $result;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        return false;
    }
}

final class EchoProcess extends AbstractProcess
{
    protected function run($arg): void {}

    protected function onPipeReadable(Process $process): void
    {
        $process->write('ack:' . $process->read());
    }
}

class IdleProcess extends AbstractProcess
{
    protected function run($arg): void {}
}

final class ShutdownCountingProcess extends IdleProcess
{
    protected function onShutDown(): void
    {
        $this->getArg()->add(1);
    }
}

final class CoroutineShutdownProcess extends IdleProcess
{
    protected function onShutDown(): void
    {
        if (Coroutine::getCid() < 0) {
            throw new RuntimeException('关闭钩子未运行在协程上下文中');
        }

        Coroutine::sleep(0.01);
        $this->getArg()->add(1);
    }
}
