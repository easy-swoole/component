<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/6/22
 * Time: 下午1:33
 */

namespace EasySwoole\Component\Pool;


abstract class AbstractObject
{
    protected abstract function gc();
    //使用后,free的时候会执行
    abstract function objectRestore();

    function __destruct()
    {
        // TODO: Implement __destruct() method.
        $this->gc();
    }
}