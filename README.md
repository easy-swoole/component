# PoolInterface

```
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Component\Pool\TraitObjectInvoker;
use EasySwoole\Utility\Random;
use EasySwoole\Component\Pool\AbstractPoolObject;
use EasySwoole\Component\Pool\PoolObjectInterface;
use EasySwoole\Component\Pool\AbstractPool;
class test
{
    public $id;

    function __construct()
    {
        $this->id = Random::character(8);
    }

    function fuck(){
        var_dump('this is fuck at class:'.static::class.'@id:'.$this->id);
    }
}

class test2 extends test implements PoolObjectInterface
{
    function objectRestore()
    {
        var_dump('this is objectRestore at class:'.static::class.'@id:'.$this->id);
    }

    function gc()
    {
        // TODO: Implement gc() method.
    }

    function beforeUse(): bool
    {
        // TODO: Implement beforeUse() method.
        return true;
    }
}

class testPool extends AbstractPool
{

    protected function createObject()
    {
        // TODO: Implement createObject() method.
        return new test();
    }
}

class testPool2 extends AbstractPool
{

    protected function createObject()
    {
        // TODO: Implement createObject() method.
        return new test2();
    }
}



class test3 extends test
{
    use TraitObjectInvoker;
}

class test4 extends AbstractPoolObject
{
    function finalFuck()
    {
        var_dump('final fuck');
    }

    function objectRestore()
    {
        var_dump('final objectRestore');
    }
}

//cli下关闭pool的自动定时检查
PoolManager::getInstance()->getDefaultConfig()->setIntervalCheckTime(0);

go(function (){
    go(function (){
        $object = PoolManager::getInstance()->getPool(test::class)->getObj();
        $object->fuck();
        PoolManager::getInstance()->getPool(test::class)->recycleObj($object);
    });

    go(function (){
        testPool::invoke(function (test $test){
            $test->fuck();
        });
    });

    go(function (){
        testPool2::invoke(function (test2 $test){
            $test->fuck();
        });
    });

    go(function (){
        test3::invoke(function (test3 $test3){
            $test3->fuck();
        });
    });

    go(function (){
        $object = PoolManager::getInstance()->getPool(test4::class)->getObj();
        $object->finalFuck();
        PoolManager::getInstance()->getPool(test4::class)->recycleObj($object);
    });
});

```