## CoroutineRunner

```
use EasySwoole\Component\CoroutineRunner\Runner;
use Swoole\Coroutine\Scheduler;
use EasySwoole\Component\CoroutineRunner\Task;
$scheduler = new Scheduler;
$scheduler->add(function () {
    $runner = new Runner(4);
    $i = 10;
    while ($i){
        $runner->addTask(new Task(function ()use($runner,$i){
            var_dump("now is num.{$i} at time ".time());
            \co::sleep(1);

            if($i == 5){
                $runner->addTask(new Task(function (){
                    var_dump('this is task add in running');
                }));
            }

        }));
        $i--;
    }
    $runner->start();
    var_dump('task finish');
});
$scheduler->start();

```
