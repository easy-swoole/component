<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests\Process;

use EasySwoole\Component\Process\Config;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
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
}
