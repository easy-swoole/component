<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests\Process;

use EasySwoole\Component\Process\Socket\TcpProcessConfig;
use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use PHPUnit\Framework\TestCase;
use TypeError;

final class SocketProcessConfigTest extends TestCase
{
    public function testTcpConfigDefaultsAndTypedAccessors(): void
    {
        $config = new TcpProcessConfig();

        self::assertSame('0.0.0.0', $config->getListenAddress());
        self::assertNull($config->getListenPort());
        self::assertTrue($config->isAsyncCallback());
        self::assertSame(['l_linger' => 0, 'l_onoff' => 0], $config->getLinger());

        $config->setListenAddress('127.0.0.1');
        $config->setListenPort(9501);
        $config->setAsyncCallback(false);
        $config->setLinger(['l_linger' => 3, 'l_onoff' => 1]);

        self::assertSame('127.0.0.1', $config->getListenAddress());
        self::assertSame(9501, $config->getListenPort());
        self::assertFalse($config->isAsyncCallback());
        self::assertSame(['l_linger' => 3, 'l_onoff' => 1], $config->getLinger());
    }

    public function testUnixConfigDefaultsAndTypedAccessors(): void
    {
        $config = new UnixProcessConfig();

        self::assertNull($config->getSocketFile());
        self::assertTrue($config->isAsyncCallback());
        self::assertSame(['l_linger' => 0, 'l_onoff' => 0], $config->getLinger());

        $config->setSocketFile('/tmp/component.sock');
        $config->setAsyncCallback(false);
        $config->setLinger(['l_linger' => 5, 'l_onoff' => 1]);

        self::assertSame('/tmp/component.sock', $config->getSocketFile());
        self::assertFalse($config->isAsyncCallback());
        self::assertSame(['l_linger' => 5, 'l_onoff' => 1], $config->getLinger());
    }

    public function testTcpPortRejectsNonIntegerValue(): void
    {
        $this->expectException(TypeError::class);
        (new TcpProcessConfig())->setListenPort('9501');
    }

    public function testUnixSocketFileRejectsNonStringValue(): void
    {
        $this->expectException(TypeError::class);
        (new UnixProcessConfig())->setSocketFile(123);
    }
}
