<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/30
 * Time: 下午2:37
 */

namespace EasySwoole\Component;


use EasySwoole\Trigger\Trigger;

class MultiEvent extends MultiContainer
{
    function set($key, $item)
    {
        if(is_callable($item)){
            return parent::set($key, $item);
        }else{
            return false;
        }
    }

    function add($key, $item)
    {
        if(is_callable($item)){
            return parent::add($key, $item);
        }else{
            return false;
        }
    }

    public function hook($event,...$args):array
    {
        $res = [];
        $calls = $this->get($event);
        foreach ($calls as $key => $call){
            try{
                $res[$key] =  call_user_func($call,...$args);
            }catch (\Throwable $throwable){
                Trigger::throwable($throwable);
                $res[$key] = null;
            }
        }
        return $res;
    }
}