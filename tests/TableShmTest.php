<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests;

use EasySwoole\Component\TableShm;
use InvalidArgumentException;
use JsonException;
use LengthException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Swoole\Process;

final class TableShmTest extends TestCase
{
    public function testPhpSerializerSupportsCommonTypes(): void
    {
        $shm = new TableShm(16);
        $values = [
            'array' => ['id' => 1, 'enabled' => true],
            'string' => '中文内容',
            'integer' => 42,
            'false' => false,
            'null' => null,
        ];

        foreach ($values as $key => $value) {
            self::assertTrue($shm->set($key, $value));
            self::assertSame($value, $shm->get($key));
        }

        foreach (array_keys($values) as $key) {
            self::assertTrue($shm->exists($key));
        }
        self::assertNull($shm->get('missing'));
    }

    public function testJsonSerializer(): void
    {
        $shm = new TableShm(4, TableShm::SERIALIZER_JSON);
        self::assertTrue($shm->set('user', ['id' => 7, 'roles' => ['admin']]));
        self::assertSame(['id' => 7, 'roles' => ['admin']], $shm->get('user'));
    }

    public function testJsonSerializerReportsUnsupportedValues(): void
    {
        $shm = new TableShm(4, TableShm::SERIALIZER_JSON);

        $this->expectException(JsonException::class);
        $shm->set('resource', fopen('php://memory', 'rb'));
    }

    public function testDeleteAndClear(): void
    {
        $shm = new TableShm(4);
        $shm->set('first', 1);
        $shm->set('second', 2);

        self::assertTrue($shm->exists('first'));
        self::assertTrue($shm->delete('first'));
        self::assertFalse($shm->exists('first'));

        $shm->clear();
        self::assertFalse($shm->exists('second'));
        self::assertNull($shm->get('second'));
    }

    public function testCustomColumnSizeAndRejectsOversizedSerializedData(): void
    {
        $shm = new TableShm(4, TableShm::SERIALIZER_PHP, 16);

        // PHP 序列化后恰好为 16 字节，可以完整写入。
        self::assertTrue($shm->set('fit', '123456789'));
        self::assertSame('123456789', $shm->get('fit'));

        $this->expectException(LengthException::class);
        $shm->set('large', '1234567890');
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TableShm(0);
    }

    public function testRejectsInvalidColumnSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TableShm(4, TableShm::SERIALIZER_PHP, 0);
    }

    public function testRejectsUnsupportedSerializer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TableShm(4, 999);
    }

    public function testMsgpackRequiresExtensionWhenUnavailable(): void
    {
        if (function_exists('msgpack_pack') && function_exists('msgpack_unpack')) {
            $shm = new TableShm(4, TableShm::SERIALIZER_MSGPACK);
            self::assertTrue($shm->set('message', ['status' => 'ok']));
            self::assertSame(['status' => 'ok'], $shm->get('message'));

            return;
        }

        $this->expectException(RuntimeException::class);
        new TableShm(4, TableShm::SERIALIZER_MSGPACK);
    }

    public function testChildProcessCanWriteAndParentCanRead(): void
    {
        // 共享表必须在 fork 之前创建，这样父子进程才会映射同一块内存。
        $shm = new TableShm(4);
        $process = new Process(static function (Process $process) use ($shm): void {
            $shm->set('child-result', ['pid' => getmypid(), 'status' => 'done']);
            $process->exit(0);
        });

        $pid = $process->start();
        self::assertIsInt($pid);

        $result = Process::wait(true);
        self::assertIsArray($result);
        self::assertSame($pid, $result['pid']);
        self::assertSame(0, $result['code']);
        self::assertSame(
            ['pid' => $pid, 'status' => 'done'],
            $shm->get('child-result')
        );
    }
}
