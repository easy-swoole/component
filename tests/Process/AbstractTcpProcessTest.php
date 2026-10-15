<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests\Process;

use EasySwoole\Component\Process\Config;
use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Component\Process\Socket\TcpProcessConfig;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Socket;
use Swoole\Process;

final class AbstractTcpProcessTest extends TestCase
{
    public function testSigTermStopsListeningProcessWithoutExitError(): void
    {
        $port = $this->findAvailablePort();
        $config = new TcpProcessConfig();
        $config->setProcessName('phpunit-tcp-process');
        $config->setListenAddress('127.0.0.1');
        $config->setListenPort($port);
        $config->setPipeType(Config::PIPE_TYPE_NONE);

        $process = new TcpProcessForTest($config);
        $pid = $process->getProcess()->start();
        self::assertIsInt($pid);

        try {
            self::assertTrue($this->waitUntilListening($port));
            self::assertTrue(Process::kill($pid, SIGTERM));

            $result = Process::wait(true);
            self::assertIsArray($result);
            self::assertSame($pid, $result['pid']);
            self::assertSame(0, $result['code']);
            self::assertSame(0, $result['signal']);
        } finally {
            // 断言提前失败时也要回收测试子进程，避免影响后续用例。
            if (Process::kill($pid, 0)) {
                Process::kill($pid, SIGKILL);
                Process::wait(true);
            }
        }
    }

    private function findAvailablePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($server, $errorMessage);
        $address = stream_socket_get_name($server, false);
        fclose($server);

        self::assertIsString($address);
        return (int) substr(strrchr($address, ':'), 1);
    }

    private function waitUntilListening(int $port): bool
    {
        $deadline = microtime(true) + 2;
        do {
            $client = @stream_socket_client(
                "tcp://127.0.0.1:{$port}",
                $errorCode,
                $errorMessage,
                0.05
            );
            if (is_resource($client)) {
                fclose($client);
                return true;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        return false;
    }
}

final class TcpProcessForTest extends AbstractTcpProcess
{
    public function onAccept(Socket $socket): void
    {
        $socket->close();
    }
}
