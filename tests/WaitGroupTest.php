<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests;

use EasySwoole\Component\WaitGroup;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

final class WaitGroupTest extends TestCase
{
    public function testAddDoesNotExecuteCallbackUntilStart(): void
    {
        Coroutine\run(function (): void {
            $executed = false;
            $waitGroup = new WaitGroup();
            $waitGroup->add(function () use (&$executed): void {
                $executed = true;
            });

            self::assertFalse($executed);
            self::assertSame(1, $waitGroup->count());

            $waitGroup->start();
            self::assertTrue($waitGroup->wait(1));
            self::assertTrue($executed);
            self::assertSame(0, $waitGroup->count());
        });
    }

    public function testWaitAutomaticallyStartsCallbacks(): void
    {
        Coroutine\run(function (): void {
            $result = [];
            $waitGroup = new WaitGroup();
            $waitGroup->add(function () use (&$result): void {
                Coroutine::sleep(0.01);
                $result[] = 'first';
            });
            $waitGroup->add(function () use (&$result): void {
                Coroutine::sleep(0.02);
                $result[] = 'second';
            });

            self::assertSame([], $result);
            self::assertTrue($waitGroup->wait(1));
            self::assertCount(2, $result);
            self::assertSame(2, $waitGroup->successNum());
            self::assertSame(0, $waitGroup->count());
        });
    }

    public function testSupportsManualAddAndDoneAfterStart(): void
    {
        Coroutine\run(function (): void {
            $waitGroup = new WaitGroup();
            $waitGroup->add();
            $waitGroup->start();

            Coroutine::create(function () use ($waitGroup): void {
                Coroutine::sleep(0.01);
                $waitGroup->done();
            });

            self::assertTrue($waitGroup->wait(1));
            self::assertSame(1, $waitGroup->successNum());
        });
    }

    public function testStartCanOnlyBeCalledOnce(): void
    {
        Coroutine\run(function (): void {
            $waitGroup = new WaitGroup();
            $waitGroup->start();

            try {
                $waitGroup->start();
                self::fail('第二次调用 start() 应抛出异常');
            } catch (LogicException $exception) {
                self::assertStringContainsString('only be called once', $exception->getMessage());
            }
        });
    }

    public function testWaitCanOnlyBeCalledOnce(): void
    {
        Coroutine\run(function (): void {
            $waitGroup = new WaitGroup();
            self::assertTrue($waitGroup->wait(1));

            try {
                $waitGroup->wait(1);
                self::fail('第二次调用 wait() 应抛出异常');
            } catch (LogicException $exception) {
                self::assertStringContainsString('only be called once', $exception->getMessage());
            }
        });
    }

    public function testWaitAutoStartPreventsLaterStart(): void
    {
        Coroutine\run(function (): void {
            $waitGroup = new WaitGroup();
            self::assertTrue($waitGroup->wait(1));

            try {
                $waitGroup->start();
                self::fail('wait() 自动启动后不应再次 start()');
            } catch (LogicException $exception) {
                self::assertStringContainsString('only be called once', $exception->getMessage());
            }
        });
    }

    public function testCannotAddTaskAfterStart(): void
    {
        Coroutine\run(function (): void {
            $waitGroup = new WaitGroup();
            $waitGroup->start();

            try {
                $waitGroup->add(static function (): void {});
                self::fail('启动后不应继续添加任务');
            } catch (LogicException $exception) {
                self::assertStringContainsString('cannot be added', $exception->getMessage());
            }
        });
    }

    public function testRejectsDoneBeforeStartAndExtraDone(): void
    {
        Coroutine\run(function (): void {
            $waitGroup = new WaitGroup();
            $waitGroup->add();

            try {
                $waitGroup->done();
                self::fail('启动前调用 done() 应抛出异常');
            } catch (LogicException $exception) {
                self::assertStringContainsString('has not started', $exception->getMessage());
            }

            $waitGroup->start();
            $waitGroup->done();

            try {
                $waitGroup->done();
                self::fail('多余的 done() 应抛出异常');
            } catch (LogicException $exception) {
                self::assertStringContainsString('exceed', $exception->getMessage());
            }

            self::assertTrue($waitGroup->wait(1));
        });
    }

    public function testTimeoutReturnsFalseButWaitStillCannotBeRepeated(): void
    {
        Coroutine\run(function (): void {
            $waitGroup = new WaitGroup();
            $waitGroup->add(function (): void {
                Coroutine::sleep(0.05);
            });

            self::assertFalse($waitGroup->wait(0.01));

            try {
                $waitGroup->wait(1);
                self::fail('超时后再次调用 wait() 仍应抛出异常');
            } catch (LogicException $exception) {
                self::assertStringContainsString('only be called once', $exception->getMessage());
            }

            // 等待任务自然结束，避免测试退出时留下未完成协程。
            Coroutine::sleep(0.06);
            self::assertSame(0, $waitGroup->count());
        });
    }

    public function testRejectsInvalidCapacity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WaitGroup(0);
    }

    public function testResetMethodIsNotExposed(): void
    {
        self::assertFalse(method_exists(WaitGroup::class, 'reset'));
    }
}
