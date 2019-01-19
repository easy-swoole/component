<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-06
 * Time: 23:27
 */

namespace EasySwoole\Component\Tests;


use EasySwoole\Component\Context\ContextHandlerInterface;
use EasySwoole\Utility\Random;

class ContextContextHandler implements ContextHandlerInterface
{

    function onContextCreate()
    {
        // TODO: Implement onContextCreate() method.
        $stdClass = new \stdClass();
        $stdClass->text = 'handler';
        return $stdClass;
    }

    function onDestroy($context)
    {
        // TODO: Implement onDestroy() method.
        $context->destroy = true;
    }
}