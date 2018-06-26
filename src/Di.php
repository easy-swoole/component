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
            $result = $this->container[$key];
            if(is_object($result['obj'])){
                return $result['obj'];
            }else if(is_callable($result['obj'])){
                return $this->container[$key]['obj'];
            }else if(is_string($result['obj']) && class_exists($result['obj'])){
                try{
                    $params = $result['params'];
                    $class = $result['obj'];
                    $this->container[$key]['obj'] = new $class(...$params);
                    return $this->container[$key]['obj'];
                }catch (\Throwable $throwable){
                    Trigger::throwable($throwable);
                    return null;
                }
            }else{
                return $result['obj'];
            }
        }else{
            return null;
        }
    }
}