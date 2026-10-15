<?php

declare(strict_types=1);

namespace EasySwoole\Component\Tests\Process;

use EasySwoole\Component\Process\Config;
use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Socket;
use Swoole\Process;

final class AbstractUnixProcessTest extends TestCase
{
    public function testSigTermStopsListeningProcessWithoutDeadlockError(): void
    {
        $socketFile = sprintf(
            '%s/easyswoole-component-%d-%s.sock',
            sys_get_temp_dir(),
            getmypid(),
            bin2hex(random_bytes(4))
        );
        $config = new UnixProcessConfig();
        $config->setProcessName('phpunit-unix-process');
        $config->setSocketFile($socketFile);
        $config->setPipeType(Config::PIPE_TYPE_NONE);

        $process = new UnixProcessForTest($config);
        $pid = $process->getProcess()->start();
        self::assertIsInt($pid);

        try {
            self::assertTrue($this->waitUntilListening($socketFile));
            self::assertTrue(Process::kill($pid, SIGTERM));

            $result = Process::wait(true);
            self::assertIsArray($result);
            self::assertSame($pid, $result['pid']);
            self::assertSame(0, $result['code']);
            self::assertSame(0, $result['signal']);
        } finally {
            // 断言提前失败时也要回收子进程并删除临时 Socket 文件。
            if (Process::kill($pid, 0)) {
                Process::kill($pid, SIGKILL);
                Process::wait(true);
            }
            if (file_exists($socketFile)) {
                unlink($socketFile);
            }
        }
    }

    private function waitUntilListening(string $socketFile): bool
    {
        $deadline = microtime(true) + 2;
        do {
            $client = @stream_socket_client(
                'unix://' . $socketFile,
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

final class UnixProcessForTest extends AbstractUnixProcess
{
    public function onAccept(Socket $socket): void
    {
        $socket->close();
    }
}
