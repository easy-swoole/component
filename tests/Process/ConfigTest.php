<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests\Process;

use EasySwoole\Component\Process\Config;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

final class ConfigTest extends TestCase
{
    public function testDefaultsAndTypedAccessors(): void
    {
        $config = new Config();

        self::assertNull($config->getProcessName());
        self::assertNull($config->getProcessGroup());
        self::assertNull($config->getArg());
        self::assertFalse($config->isRedirectStdinStdout());
        self::assertSame(Config::PIPE_TYPE_SOCK_DGRAM, $config->getPipeType());
        self::assertTrue($config->isEnableCoroutine());

        $arg = new stdClass();
        $config->setProcessName('worker');
        $config->setProcessGroup('default');
        $config->setArg($arg);
        $config->setRedirectStdinStdout(true);
        $config->setEnableCoroutine(false);

        self::assertSame('worker', $config->getProcessName());
        self::assertSame('default', $config->getProcessGroup());
        self::assertSame($arg, $config->getArg());
        self::assertTrue($config->isRedirectStdinStdout());
        self::assertFalse($config->isEnableCoroutine());
    }

    public static function validPipeTypes(): array
    {
        return [
            'none' => [Config::PIPE_TYPE_NONE],
            'stream' => [Config::PIPE_TYPE_SOCK_STREAM],
            'datagram' => [Config::PIPE_TYPE_SOCK_DGRAM],
        ];
    }

    #[DataProvider('validPipeTypes')]
    public function testAcceptsSupportedPipeTypes(int $pipeType): void
    {
        $config = new Config();
        $config->setPipeType($pipeType);

        self::assertSame($pipeType, $config->getPipeType());
    }

    public function testRejectsUnsupportedPipeType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Config())->setPipeType(99);
    }

    public function testProcessGroupCanBeCleared(): void
    {
        $config = new Config();
        $config->setProcessGroup('workers');
        $config->setProcessGroup(null);

        self::assertNull($config->getProcessGroup());
    }

    public function testProcessNameRejectsNonStringValue(): void
    {
        $this->expectException(TypeError::class);
        (new Config())->setProcessName(123);
    }
}
