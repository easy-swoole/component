<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests;

use EasySwoole\Component\CoroutineSingleTon;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use WeakReference;

final class CoroutineSingleTonTest extends TestCase
{
    public function testReturnsSameInstanceInSameCoroutine(): void
    {
        $instances = [];

        Coroutine\run(function () use (&$instances): void {
            $instances[] = CoroutineScopedObject::getInstance('first');
            $instances[] = CoroutineScopedObject::getInstance('second');
        });

        self::assertSame($instances[0], $instances[1]);
        self::assertSame('first', $instances[0]->name);
    }

    public function testReturnsDifferentInstancesInDifferentCoroutines(): void
    {
        $instances = [];

        Coroutine\run(function () use (&$instances): void {
            $result = new Channel(2);
            $release = new Channel(2);

            Coroutine::create(function () use ($result, $release): void {
                $result->push(CoroutineScopedObject::getInstance('first'));
                $release->pop();
            });
            Coroutine::create(function () use ($result, $release): void {
                $result->push(CoroutineScopedObject::getInstance('second'));
                $release->pop();
            });

            $instances[] = $result->pop();
            $instances[] = $result->pop();
            $release->push(true);
            $release->push(true);
        });

        self::assertNotSame($instances[0], $instances[1]);
        self::assertSame('first', $instances[0]->name);
        self::assertSame('second', $instances[1]->name);
    }

    public function testDestroyCreatesNewInstanceForCurrentCoroutine(): void
    {
        $instances = [];

        Coroutine\run(function () use (&$instances): void {
            $first = CoroutineScopedObject::getInstance('first');
            $first->destroy();

            $instances[] = $first;
            $instances[] = CoroutineScopedObject::getInstance('second');
        });

        self::assertNotSame($instances[0], $instances[1]);
        self::assertSame('second', $instances[1]->name);
    }

    public function testInstanceIsReleasedWhenCoroutineEnds(): void
    {
        $reference = null;

        Coroutine\run(function () use (&$reference): void {
            Coroutine::create(function () use (&$reference): void {
                $instance = CoroutineScopedObject::getInstance('temporary');
                $reference = WeakReference::create($instance);
            });
        });

        self::assertInstanceOf(WeakReference::class, $reference);
        self::assertNull($reference->get());
    }

    public function testNonCoroutineInstancePersistsUntilDestroyed(): void
    {
        $first = CoroutineScopedObject::getInstance('outside-first');
        $second = CoroutineScopedObject::getInstance('outside-second');

        self::assertSame($first, $second);
        self::assertSame('outside-first', $second->name);

        $first->destroy();
        $third = CoroutineScopedObject::getInstance('outside-third');

        self::assertNotSame($first, $third);
        self::assertSame('outside-third', $third->name);
        $third->destroy();
    }
}

final class CoroutineScopedObject
{
    use CoroutineSingleTon;

    public function __construct(public string $name)
    {
    }
}
