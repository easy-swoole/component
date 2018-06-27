<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/24
 * Time: 下午4:17
 */

namespace EasySwoole\Component;
use EasySwoole\Trigger\Trigger;


class Di
{
    use Singleton;
    private $container = array();

    public function set($key, $obj,...$arg):void
    {
        /*
         * 注入的时候不做任何的类型检测与转换
         * 由于编程人员为问题，该注入资源并不一定会被用到
         */
        $this->container[$key] = array(
            "obj"=>$obj,
            "params"=>$arg,
        );
    }

    function delete($key):void
    {
        unset( $this->container[$key]);
    }

    function clear():void
    {
        $this->container = array();
    }

    function get($key)
    {
        if(isset($this->container[$key])){
            $obj = $this->container[$key]['obj'];
            $params = $this->container[$key]['params'];
            if(is_object($obj) || is_callable($obj)){
                return $obj;
            }else if(is_string($obj) && class_exists($obj)){
                try{
                    $this->container[$key]['obj'] = new $obj(...$params);
                    return $this->container[$key]['obj'];
                }catch (\Throwable $throwable){
                    Trigger::throwable($throwable);
                    return null;
                }
            }else{
                return $obj;
            }
        }else{
            return null;
        }
    }
}