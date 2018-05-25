<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/25
 * Time: 下午12:42
 */

namespace EasySwoole\Component;


use EasySwoole\Trigger\Trigger;

class Event extends Container
{
    function set($key, $item)
    {
        if(is_callable($item)){
            return parent::set($key, $item);
        }else{
            return false;
        }
    }

    public function hook($event,...$args)
    {
        $call = $this->get($event);
        if(is_callable($call)){
            try{
                return Invoker::callUserFunc($call,...$args);
            }catch (\Throwable $throwable){
                Trigger::throwable($throwable);
                return null;
            }
        }else{
            return null;
        }
    }
}