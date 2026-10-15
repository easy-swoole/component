<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests;

use EasySwoole\Component\ReadyScheduler;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

final class ReadySchedulerTest extends TestCase
{
    public function testDropItemRemovesRegisteredItem(): void
    {
        $scheduler = new ReadyScheduler();

        self::assertTrue($scheduler->addItem('database'));
        self::assertTrue($scheduler->ready('database'));
        self::assertTrue($scheduler->dropItem('database'));
        self::assertNull($scheduler->status('database'));
        self::assertFalse($scheduler->ready('database'));
        self::assertFalse($scheduler->unready('database'));
        self::assertFalse($scheduler->restore('database'));
    }

    public function testDropItemReturnsFalseWhenItemDoesNotExist(): void
    {
        $scheduler = new ReadyScheduler();

        self::assertFalse($scheduler->dropItem('missing'));
        self::assertTrue($scheduler->addItem('database'));
        self::assertTrue($scheduler->dropItem('database'));
        self::assertFalse($scheduler->dropItem('database'));
    }

    public function testStateChangesDoNotCreateUnknownItems(): void
    {
        $scheduler = new ReadyScheduler();

        self::assertFalse($scheduler->ready('missing-ready'));
        self::assertFalse($scheduler->unready('missing-unready'));
        self::assertFalse($scheduler->restore('missing-restore'));
        self::assertNull($scheduler->status('missing-ready'));
        self::assertNull($scheduler->status('missing-unready'));
        self::assertNull($scheduler->status('missing-restore'));
    }

    public function testStateChangesSucceedForRegisteredItem(): void
    {
        $scheduler = new ReadyScheduler();

        self::assertTrue($scheduler->addItem('database'));
        self::assertTrue($scheduler->ready('database'));
        self::assertSame(ReadyScheduler::STATUS_READY, $scheduler->status('database'));
        self::assertTrue($scheduler->unready('database'));
        self::assertSame(ReadyScheduler::STATUS_UNREADY, $scheduler->status('database'));
        self::assertTrue($scheduler->ready('database'));
        self::assertTrue($scheduler->restore('database'));
        self::assertSame(ReadyScheduler::STATUS_UNREADY, $scheduler->status('database'));
    }

    public function testWaitReadyUsesActualTimeout(): void
    {
        Coroutine\run(function (): void {
            $scheduler = new ReadyScheduler();
            $scheduler->addItem('database');
            $startedAt = hrtime(true);

            self::assertFalse($scheduler->waitReady(['database'], 0.025));
            $elapsed = (hrtime(true) - $startedAt) / 1_000_000_000;

            self::assertGreaterThanOrEqual(0.02, $elapsed);
            self::assertLessThan(0.08, $elapsed);
        });
    }

    public function testWaitAnyReadyUsesActualTimeout(): void
    {
        Coroutine\run(function (): void {
            $scheduler = new ReadyScheduler();
            $scheduler->addItem('database');
            $startedAt = hrtime(true);

            self::assertFalse($scheduler->waitAnyReady(['database'], 0.025));
            $elapsed = (hrtime(true) - $startedAt) / 1_000_000_000;

            self::assertGreaterThanOrEqual(0.02, $elapsed);
            self::assertLessThan(0.08, $elapsed);
        });
    }

    public function testWaitReadyReturnsWhenItemBecomesReadyBeforeDeadline(): void
    {
        Coroutine\run(function (): void {
            $scheduler = new ReadyScheduler();
            $scheduler->addItem('database');

            Coroutine::create(function () use ($scheduler): void {
                Coroutine::sleep(0.02);
                $scheduler->ready('database');
            });

            self::assertTrue($scheduler->waitReady(['database'], 0.2));
        });
    }

    public function testWaitReadyRequiresAllItemsToBeReadyInSameCheck(): void
    {
        Coroutine\run(function (): void {
            $scheduler = new ReadyScheduler();
            $scheduler->addItem('database');
            $scheduler->addItem('cache');
            $scheduler->ready('database');

            Coroutine::create(function () use ($scheduler): void {
                Coroutine::sleep(0.02);
                // database 不再就绪后，cache 才进入就绪状态。
                $scheduler->unready('database');
                $scheduler->ready('cache');
            });

            self::assertFalse($scheduler->waitReady(['database', 'cache'], 0.06));
            self::assertSame(ReadyScheduler::STATUS_UNREADY, $scheduler->status('database'));
            self::assertSame(ReadyScheduler::STATUS_READY, $scheduler->status('cache'));
        });
    }

    public function testWaitReadyReturnsTrueWhenAllItemsBecomeReadyTogether(): void
    {
        Coroutine\run(function (): void {
            $scheduler = new ReadyScheduler();
            $scheduler->addItem('database');
            $scheduler->addItem('cache');

            Coroutine::create(function () use ($scheduler): void {
                Coroutine::sleep(0.02);
                $scheduler->ready('database');
                $scheduler->ready('cache');
            });

            self::assertTrue($scheduler->waitReady(['database', 'cache'], 0.2));
        });
    }

    public function testZeroTimeoutStillChecksCurrentStatusOnce(): void
    {
        Coroutine\run(function (): void {
            $scheduler = new ReadyScheduler();
            $scheduler->addItem('ready');
            $scheduler->addItem('unready');
            $scheduler->ready('ready');

            self::assertTrue($scheduler->waitReady(['ready'], 0));
            self::assertFalse($scheduler->waitReady(['unready'], 0));
            self::assertTrue($scheduler->waitAnyReady(['unready', 'ready'], 0));
            self::assertFalse($scheduler->waitAnyReady(['unready'], 0));
        });
    }
}
