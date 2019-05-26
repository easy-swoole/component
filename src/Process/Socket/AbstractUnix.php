<?php


namespace EasySwoole\Component\Process\Socket;


use EasySwoole\Component\Process\AbstractProcess;

abstract class AbstractUnix extends AbstractProcess
{
    abstract function onMessage(string $message);
}