<?php

declare(strict_types=1);

namespace EasySwoole\Component;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Swoole\Process;

class Invoker
{
    private static bool $executing = false;

    /**
     * 为同步 callable 设置微秒级软超时。
     *
     * @param int $timeout 超时时间，单位为微秒；默认 100000 微秒（100 毫秒）
     */
    public static function exec(
        callable $callable,
        int $timeout = 100_000,
        mixed ...$params
    ): mixed
    {
        if($timeout <= 0){
            throw new InvalidArgumentException('Timeout must be greater than zero.');
        }
        if(self::$executing){
            throw new LogicException('Nested Invoker calls are not supported.');
        }
        if(
            !defined('SIGALRM')
            || !function_exists('pcntl_async_signals')
            || !function_exists('pcntl_signal')
            || !function_exists('pcntl_signal_get_handler')
        ){
            throw new RuntimeException('Invoker requires the pcntl extension and SIGALRM support.');
        }

        $previousAsyncSignals = pcntl_async_signals();
        $previousSignalHandler = pcntl_signal_get_handler(SIGALRM);
        self::$executing = true;

        try{
            pcntl_async_signals(true);
            if(!pcntl_signal(SIGALRM, static function (): void {
                Process::alarm(-1);
                throw new RuntimeException('func timeout');
            })){
                throw new RuntimeException('Failed to register SIGALRM handler.');
            }
            if(!Process::alarm($timeout)){
                throw new RuntimeException('Failed to start timeout alarm.');
            }

            return $callable(...$params);
        }finally{
            try{
                Process::alarm(-1);
            }finally{
                // Invoker 使用进程级信号，调用结束后必须恢复调用方原有设置。
                pcntl_signal(SIGALRM, $previousSignalHandler);
                pcntl_async_signals($previousAsyncSignals);
                self::$executing = false;
            }
        }
    }
}
