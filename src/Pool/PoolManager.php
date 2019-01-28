<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/26
 * Time: 上午12:54
 */

namespace EasySwoole\Component\Pool;


use EasySwoole\Component\Singleton;

class PoolManager
{
    use Singleton;

    private $pool = [];
    private $defaultConfig;
    private $classMap = [];

    function __construct()
    {
        $this->defaultConfig = new PoolConf();
    }

    function getDefaultConfig()
    {
        return $this->defaultConfig;
    }

    function register(string $className, $maxNum = 20):?PoolConf
    {
        try{
            $ref = new \ReflectionClass($className);
            if($ref->isSubclassOf(AbstractPool::class)){
                $conf = clone $this->defaultConfig;
                $conf->setClass($className);
                $conf->setMaxObjectNum($maxNum);
                $this->pool[$this->generateKey($className)] = $conf;
                return $conf;
            }else{
                return null;
            }
        }catch (\Throwable $throwable){
            return null;
        }
    }

    /*
     * 请在进程克隆后，也就是worker start后，每个进程中独立使用
     */
    function getPool(string $className,?callable $createCall = null):?AbstractPool
    {
        //检查是否存在动态map
        if(isset($this->classMap[$className])){
            $key = $this->classMap[$className];
        }else{
            $key = $this->generateKey($className);
        }
        if(isset($this->pool[$key])){
            $item = $this->pool[$key];
            if($item instanceof AbstractPool){
                return $item;
            }else if($item instanceof PoolConf){
                $className = $item->getClass();
                /** @var AbstractPool $obj */
                $obj = new $className($item);
                $this->pool[$key] = $obj;
                return $obj;
            }
        }else{
            //先尝试动态注册
            if(!$this->register($className)){
                $config = clone $this->defaultConfig;
                $config->setClass($className);
                $temp = new class($config,$createCall) extends AbstractPool{
                    protected $createCall;
                    public function __construct(PoolConf $conf,$createCall)
                    {
                        $this->createCall = $createCall;
                        parent::__construct($conf);
                    }

                    protected function createObject()
                    {
                        // TODO: Implement createObject() method.
                        if(is_callable($this->createCall)){
                            return call_user_func($this->createCall);
                        }else{
                            $class = $this->getPoolConfig()->getClass();
                            return new $class;
                        }
                    }
                };
                $this->classMap[get_class($temp)] = $key;
                $this->pool[$key] = $temp;
            }
            return $this->getPool($className);
        }
        return null;
    }

    private function generateKey(string $class):string
    {
        return substr(md5($class), 8, 16);
    }
}