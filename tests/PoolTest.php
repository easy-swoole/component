<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-06
 * Time: 22:47
 */

namespace EasySwoole\Component\Tests;


use EasySwoole\Component\Pool\PoolManager;
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    function testNormalClass()
    {
        $pool = PoolManager::getInstance()->getPool(PoolObject::class);
        /**
         * @var $obj PoolObject
         */
        $obj = $pool->getObj();
        $this->assertEquals(PoolObject::class,$obj->fuck());
    }
}