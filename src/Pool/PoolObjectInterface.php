<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/6/22
 * Time: 下午1:33
 */

namespace EasySwoole\Component\Pool;


interface PoolObjectInterface
{
     //unset 的时候执行
     function gc();
     //使用后,free的时候会执行
     function objectRestore();
}