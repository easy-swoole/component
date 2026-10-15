<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests;

use EasySwoole\Component\ChannelLock;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

final class ChannelLockTest extends TestCase
{
    public function testUnlockReturnsFalseWhenCurrentCoroutineDoesNotOwnLock(): void
    {
        Coroutine\run(function (): void {
            $lock = new ChannelLock();

            self::assertFalse($lock->unlock('resource'));
        });
    }

    public function testAnotherCoroutineCannotUnlockOwnedLock(): void
    {
        Coroutine\run(function (): void {
            $lock = new ChannelLock();
            $ownerReady = new Channel(1);
            $releaseOwner = new Channel(1);
            $result = new Channel(1);

            Coroutine::create(function () use ($lock, $ownerReady, $releaseOwner): void {
                self::assertTrue($lock->lock('resource'));
                $ownerReady->push(true);
                $releaseOwner->pop();
                self::assertTrue($lock->unlock('resource'));
            });

            Coroutine::create(function () use ($lock, $ownerReady, $result): void {
                $ownerReady->pop();
                $result->push($lock->unlock('resource'));
            });

            self::assertFalse($result->pop(1));
            // 越权解锁失败后，原持有者仍然拥有该锁。
            self::assertFalse($lock->lock('resource', 0.01));
            $releaseOwner->push(true);
        });
    }

    public function testOwnerCanUnlockAndRepeatedUnlockReturnsFalse(): void
    {
        Coroutine\run(function (): void {
            $lock = new ChannelLock();

            self::assertTrue($lock->lock('resource'));
            self::assertTrue($lock->unlock('resource'));
            self::assertFalse($lock->unlock('resource'));
        });
    }

    public function testRepeatedLockByOwnerDoesNotRequireRepeatedUnlock(): void
    {
        Coroutine\run(function (): void {
            $lock = new ChannelLock();

            self::assertTrue($lock->lock('resource'));
            self::assertTrue($lock->lock('resource'));
            self::assertTrue($lock->unlock('resource'));
            self::assertFalse($lock->unlock('resource'));
        });
    }
}
