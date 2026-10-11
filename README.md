# EasySwoole Component

EasySwoole 的通用组件集合，提供容器、依赖注入、事件、协程并发、共享内存、定时器和自定义进程等能力。

## 环境要求

- PHP `>= 8.1`
- Swoole 扩展
- `easyswoole/spl ^2.0`
- `Invoker` 额外依赖 `pcntl` 扩展和 Unix 信号支持

```bash
composer require easyswoole/component
```

除普通容器、事件和单例等基础组件外，使用 Channel、协程调度、协程锁等功能时，代码应运行在 Swoole 协程环境中：

```php
<?php
require __DIR__ . '/vendor/autoload.php';

Co\run(function () {
    // 在这里使用协程组件
});
```

## 基础组件

### Singleton

为类提供进程内单例。首次调用 `getInstance()` 时传入的参数用于构造实例，后续调用不会重新构造。

```php
use EasySwoole\Component\Singleton;

class ConfigCenter
{
    use Singleton;

    public function __construct(public array $config = []) {}
}

$config = ConfigCenter::getInstance(['debug' => true]);
```

单例只在当前 PHP 进程内有效，多个 Worker/子进程之间不共享实例。与进程绑定的资源（例如数据库连接和 Socket）应在进程创建完成后初始化。

### CoroutineSingleTon

按协程 ID 保存实例，同一协程内返回同一实例，不同协程互相隔离。协程结束时会自动清理。

```php
use EasySwoole\Component\CoroutineSingleTon;

class RequestScope
{
    use CoroutineSingleTon;
    public array $data = [];
}

Co\run(function () {
    RequestScope::getInstance()->data['requestId'] = 'req-1';
});
```

在非协程环境中，协程 ID 通常为 `-1`，此时实例不会被自动清理，可调用 `destroy()` 手动销毁。

### Container

简单的键值容器。

```php
use EasySwoole\Component\Container;

$container = new Container(['name', 'logger']); // null 表示不限制键名
$container->set('name', 'demo');
echo $container->get('name');

$all = $container->all();
$container->delete('name');
$container->clear();
```

当设置的键不在 `allowKeys` 中时，`set()` 返回 `false`；成功时返回容器实例。

### MultiContainer

一个键可以保存多个值。

```php
use EasySwoole\Component\MultiContainer;

$container = new MultiContainer();
$container->add('middleware', 'auth');
$container->add('middleware', 'trace');
$items = $container->get('middleware'); // ['auth', 'trace']
$container->set('middleware', 'cors');  // 覆盖为 ['cors']
```

### Di

轻量级依赖注入容器，支持保存值、对象、闭包、类名、别名和未命中回调。

```php
use EasySwoole\Component\Di;

$di = Di::getInstance();
$di->set('config', ['debug' => true]);
$di->set('service', AppService::class, 'constructor argument');
$di->alias('app.service', 'service');
$service = $di->get('app.service');

$di->setOnKeyMiss(fn (string $key) => null);
```

注意：

- 保存对象或 callable 时，`get()` 直接返回原值，不会执行 callable。
- 保存类名时，第一次 `get()` 才实例化，并缓存对象。
- 未显式提供构造参数时，会尝试按参数类名或参数名递归查找依赖。
- `clear()` 只清空实际条目，不清空别名；可用 `deleteAlias()` 删除别名。
- 复杂联合类型、内置类型或无默认值参数，建议显式传入构造参数。

### Event

一个事件只保存一个 callable。

```php
use EasySwoole\Component\Event;

$events = new Event();
$events->set('created', fn (int $id) => "created:{$id}");
$result = $events->hook('created', 1001);
```

传入非 callable 时 `set()` 返回 `false`；事件不存在时 `hook()` 返回 `null`。

### MultiEvent

一个事件可以注册多个 callable，并按注册顺序执行。

```php
use EasySwoole\Component\MultiEvent;

$events = new MultiEvent();
$events->add('saved', fn (int $id) => "log:{$id}");
$events->add('saved', fn (int $id) => "notify:{$id}");
$results = $events->hook('saved', 1001);
```

`set()` 会覆盖该事件已有的监听器，`add()` 会追加监听器。监听器抛出的异常会继续向上传递。

## 协程组件

### ContextManager

按协程保存上下文数据，适合保存请求 ID、用户信息等协程局部状态。当前协程结束时自动销毁上下文。

```php
use EasySwoole\Component\Context\ContextManager;

Co\run(function () {
    $context = ContextManager::getInstance();
    $context->set('requestId', 'req-1001');
    echo $context->get('requestId');
    $data = $context->getContextArray();
    $context->unset('requestId');
});
```

这些方法也能接收指定协程 ID。常规业务建议使用默认的当前协程，避免误操作其他协程的上下文。

### ChannelManger

命名 Channel 管理器。类名中的 `Manger` 是当前公开 API 的既有拼写，使用时请保持一致。

```php
use EasySwoole\Component\ChannelManger;

Co\run(function () {
    $manager = ChannelManger::getInstance();
    $manager->add('jobs', 128);
    $channel = $manager->get('jobs');
    $channel->push(['id' => 1]);
    $job = $channel->pop();
});
```

同名 Channel 重复 `add()` 不会覆盖原实例。Channel 只能在所属进程内使用，不能替代跨进程消息通道。

### ChannelLock

基于容量为 1 的 Channel 实现协程锁，并支持同一协程内重复加锁。

```php
use EasySwoole\Component\ChannelLock;

Co\run(function () {
    $lock = ChannelLock::getInstance();
    if ($lock->deferLock('order:1001', 1.0)) {
        // 临界区；协程退出时自动 unlock
    }
});
```

它是进程内协程锁，不是跨进程分布式锁。手动调用 `lock()` 后应在 `finally` 中 `unlock()`，或优先使用 `deferLock()`。

### WaitGroup

等待一组协程任务完成。

```php
use EasySwoole\Component\WaitGroup;

Co\run(function () {
    $waitGroup = new WaitGroup();
    $waitGroup->add(fn () => Co::sleep(0.1));
    $waitGroup->add(fn () => Co::sleep(0.2));

    // add() 只登记回调，不会立即执行。可以显式启动：
    $waitGroup->start();

    if (!$waitGroup->wait(2.0)) {
        throw new RuntimeException('Timed out waiting for coroutine tasks');
    }

    echo $waitGroup->successNum(); // 2
    echo $waitGroup->count();      // 0
});
```

如果没有显式调用 `start()`，首次 `wait()` 会自动启动所有已登记回调。也可无参数调用 `add()` 登记手动任务；这类任务需要先启动 WaitGroup，再由外部协程调用 `done()`。每次 `add()` 必须对应一次 `done()`。

- 一个 WaitGroup 只用于一批任务：`start()` 和 `wait()` 最多各调用一次。
- 可以先显式调用一次 `start()`，再调用一次 `wait()`；如果 `wait()` 自动完成启动，之后不能再调用 `start()`。
- 启动后不能继续 `add()`，即使 `wait()` 超时也不能再次等待。
- `wait()` 返回 `true` 表示全部完成，返回 `false` 表示超时；超时不会取消仍在运行的协程。
- `count()` 返回尚未完成的任务数，`successNum()` 返回最近一次 `wait()` 观察到的完成数。
- 该类不提供 `reset()`；内部 Channel 会在析构函数中自动关闭。

### Csp

并发执行多个有名称的 callable，并按名称收集结果。

```php
use EasySwoole\Component\Csp;

Co\run(function () {
    $csp = new Csp();
    $csp->add('user', fn () => ['id' => 1]);
    $csp->add('orders', fn () => [1001, 1002]);
    $result = $csp->exec(3.0);
    echo $csp->successNum();
});
```

- `exec()` 超时后只停止等待，不会主动取消仍在运行的协程。
- `add()` 不清空已添加的其他任务；同名任务会被覆盖。
- 任务异常不会被转换为结果，应在任务 callable 中自行捕获或在上层处理。

## 协程任务调度

### Runner 与 Task

`Runner` 用固定并发数消费任务队列；`Task` 保存任务 callable、结果和成功/失败回调。

```php
use EasySwoole\Component\CoroutineRunner\Runner;
use EasySwoole\Component\CoroutineRunner\Task;

Co\run(function () {
    $runner = new Runner(concurrency: 4, taskChannelSize: 128);
    $runner->setOnException(function (Throwable $throwable, Task $task) {
        error_log($throwable->getMessage());
    });

    for ($i = 1; $i <= 10; $i++) {
        $task = new Task(fn () => $i * 2);
        $task->setOnSuccess(function (Task $task) {
            echo $task->getResult() . PHP_EOL;
        });
        $task->setOnFail(function (Task $task) {
            echo 'task returned false' . PHP_EOL;
        });
        $runner->addTask($task);
    }

    $runner->start(30);
});
```

执行规则：

- callable 返回严格的 `false` 时触发 `onFail`，其他返回值触发 `onSuccess`。
- `start($waitTime)` 的时间单位是秒；小于等于 `0` 表示近似无限等待。
- 可用 `setOnLoop()` 在调度循环中动态添加任务，用 `status()` 查看状态。
- `Task::setTimeout()` 目前只保存配置，`Runner` 不会据此中断任务；任务超时需在业务代码中实现。
- `start()` 超时退出时，已启动的协程不会被强制取消。

### ParallelJob

用于“持续取任务 + 多协程消费”的场景。

```php
use EasySwoole\Component\CoroutineRunner\ParallelJob;

Co\run(function () {
    $jobs = new SplQueue();
    $jobs->enqueue(['id' => 1]);
    $jobs->enqueue(['id' => 2]);

    $parallel = new ParallelJob(128);
    $parallel->setAddTask(function () use ($jobs) {
        return $jobs->isEmpty() ? null : $jobs->dequeue();
    });
    $parallel->setOnTask(function (array $job) {
        echo "handle {$job['id']}" . PHP_EOL;
    });
    $parallel->setOnException(function (Throwable $throwable, $job = null) {
        error_log($throwable->getMessage());
    });

    $completed = $parallel->sync(
        coroutineNum: 8,
        maxJobTry: 3,
        timeOut: 10
    );
});
```

`setAddTask()` 连续返回空值达到 `maxJobTry` 后，组件认为没有更多任务，因此合法任务值应为 truthy。`exec()` 异步启动，`sync()` 等待完成。`interrupt()` 只停止调度，不保证正在执行的 callable 被立即取消；中断后可用 `flushJobQueue()` 清空剩余队列。

## 共享数据与调度

### AtomicManager

管理命名的 `Swoole\Atomic` 和 `Swoole\Atomic\Long`。

```php
use EasySwoole\Component\AtomicManager;

$manager = AtomicManager::getInstance();
$manager->add('request-count', 0);
$manager->get('request-count')?->add(1);

$manager->addLong('bytes', 0);
$manager->getLong('bytes')?->add(1024);
```

应在创建 Worker/子进程之前初始化，使底层共享内存能被派生进程继承。重复添加同名项不会覆盖原对象。

### TableManager

管理命名的 `Swoole\Table`。

```php
use EasySwoole\Component\TableManager;
use Swoole\Table;

$manager = TableManager::getInstance();
$manager->add('users', [
    'name' => ['type' => Table::TYPE_STRING, 'size' => 64],
    'score' => ['type' => Table::TYPE_INT, 'size' => 8],
], 1024);

$table = $manager->get('users');
$table?->set('1001', ['name' => 'Alice', 'score' => 10]);
$manager->del('users');
```

跨进程使用的 Table 应在创建子进程前完成 `add()`。`del()` 会销毁底层 Table，请确保其他代码不再持有或使用它。

### TableShm

`TableShm` 在 `Swoole\Table` 上增加序列化能力，可直接跨进程存取数组、标量等结构化数据。

```php
use EasySwoole\Component\TableShm;
use Swoole\Process;

// 必须在 fork 子进程或启动 Swoole Server 之前创建共享表。
$shm = new TableShm(
    maxRows: 1024,
    serializer: TableShm::SERIALIZER_PHP,
    columnSize: 65535
);

$process = new Process(function () use ($shm) {
    $shm->set('worker-status', [
        'pid' => getmypid(),
        'status' => 'ready',
    ]);
});

$process->start();
Process::wait();

$status = $shm->get('worker-status');
$exists = $shm->exists('worker-status');
$shm->delete('worker-status');
$shm->clear();
```

当前对外提供 `set()`、`get()`、`exists()`、`delete()` 和 `clear()`：`set()` 写入或覆盖一个键，`delete()` 删除指定键，`clear()` 清空全部键。底层 `Swoole\Table` 不对外暴露，行数统计等额外操作不属于该封装的公共 API。

构造参数说明：

- `$maxRows`：最大行数，默认 `64`，必须大于 `0`。
- `$serializer`：可选择 PHP、JSON 或 MsgPack，默认使用 PHP 序列化。
- `$columnSize`：每行 `data` 字段的字节容量，默认 `65535`。限制的是序列化后的数据长度，不是原始值长度；超过容量时 `set()` 会抛出 `LengthException`，避免被 Swoole 静默截断。

PHP 模式能保留更多 PHP 类型，但只应读取可信进程写入的数据；JSON 模式会把对象读取为关联数组，并会对资源等无法编码的值抛出 `JsonException`；MsgPack 模式要求安装 `ext-msgpack`。`maxRows` 与 `columnSize` 越大，占用的共享内存越多，应根据实际数据规模设置。

键不存在与存储值为 `null` 时，`get()` 都返回 `null`，需要区分时请先调用 `exists()`。`clear()` 会逐行删除当前数据，并不销毁底层共享内存表；并发读写期间调用时，不应把它视为原子操作。

使用 `getInstance()` 时，只有第一次调用传入的最大行数、序列化方式和列容量生效。`TableShm` 只能在同一台机器的相关进程间共享，不能替代 Redis 等跨机器存储。

### ReadyScheduler

基于 `Swoole\Table` 维护命名就绪状态，可等待全部或任意项目就绪。

```php
use EasySwoole\Component\ReadyScheduler;

Co\run(function () {
    $scheduler = ReadyScheduler::getInstance();
    $scheduler->addItem('database');
    $scheduler->addItem('cache');

    go(function () use ($scheduler) {
        $scheduler->ready('database', true);
        $scheduler->ready('cache', true);
    });

    $allReady = $scheduler->waitReady(['database', 'cache'], 3.0);
    $anyReady = $scheduler->waitAnyReady(['database', 'cache'], 3.0);
});
```

默认的 `ready()` / `unready()` 使用加减计数；传入 `$force = true` 才直接设为 `1` / `0`。等待方法需要协程环境，超时单位为秒。建议先 `addItem()` 再变更或等待状态。

### Timer

对 Swoole Timer 的简单封装，支持按 ID 或名称清理循环定时器。

```php
use EasySwoole\Component\Timer;

$timer = Timer::getInstance();
$id = $timer->loop(1000, function () {
    echo 'tick' . PHP_EOL;
}, 'heartbeat');

$timer->after(500, fn () => print('once' . PHP_EOL));
$timer->clear('heartbeat'); // 也可以传 $id
$activeTimers = $timer->list();
```

`loop()` 和 `after()` 的时间单位为毫秒。只有命名的循环定时器会写入名称映射；`clearAll()` 会清除当前进程中的全部 Swoole Timer，不只清除本组件创建的定时器。

### Invoker

使用 `SIGALRM` 为同步 callable 增加执行超时。

```php
use EasySwoole\Component\Invoker;

$result = Invoker::exec(function (string $name) {
    return "hello {$name}";
}, 500_000, 'EasySwoole');
```

超时参数单位为微秒，超时抛出 `RuntimeException('func timeout')`。它需要 `pcntl` 和支持信号的操作系统，不适用于 Windows；也不适合嵌套调用或与其他 `SIGALRM` 用法并存。协程超时建议使用 Swoole 的协程超时机制。

## 自定义进程

### Config

`EasySwoole\Component\Process\Config` 控制自定义进程参数：

| 配置 | 默认值 | 说明 |
| --- | --- | --- |
| `processName` | `null` | 进程名称；macOS、Windows/Cygwin 不会调用底层进程改名 |
| `processGroup` | `null` | 逻辑分组，供 `Manager::kill()` 和 `info()` 使用 |
| `arg` | `null` | 传给子进程 `run($arg)` 的参数 |
| `redirectStdinStdout` | `false` | 是否重定向标准输入输出 |
| `pipeType` | `PIPE_TYPE_SOCK_DGRAM` | 也可选 `PIPE_TYPE_NONE`、`PIPE_TYPE_SOCK_STREAM` |
| `enableCoroutine` | `true` | 是否在子进程回调中启用协程 |

### AbstractProcess

继承 `AbstractProcess` 并实现 `run()`；如需接收父进程管道消息，覆盖 `onPipeReadable()` 并调用 `read()`。

```php
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\Process\Config;
use Swoole\Process;

class WorkerProcess extends AbstractProcess
{
    protected function run($arg)
    {
        // 子进程启动后的业务初始化
    }

    protected function onPipeReadable(Process $process)
    {
        $message = $process->read();
        $process->write('ack:' . $message);
    }

    protected function onSigTerm()
    {
        // 收到 SIGTERM 时释放资源
    }

    protected function onShutDown()
    {
        // 子进程关闭回调
    }

    protected function onException(Throwable $throwable, ...$args)
    {
        error_log((string) $throwable);
    }
}

$config = new Config();
$config->setProcessName('demo-worker');
$config->setProcessGroup('demo');
$config->setArg(['workerId' => 1]);

$worker = new WorkerProcess($config);
$process = $worker->getProcess();
$pid = $process->start();

$process->write('ping');
echo $process->read(); // ack:ping

Process::kill($pid, SIGTERM);
Process::wait(true); // 回收子进程，避免僵尸进程
```

也兼容旧式位置参数：

```php
$worker = new WorkerProcess(
    'demo-worker',
    ['workerId' => 1],
    false,
    Config::PIPE_TYPE_SOCK_DGRAM,
    true
);
```

进程通信与生命周期注意事项：

- 管道用于创建出的父子进程通信，不是任意独立进程间的通用消息总线。
- 默认 `SOCK_DGRAM` 保留数据报边界，但消息仍受底层 Socket 缓冲区限制。
- 使用 `SOCK_STREAM` 时必须自行设计消息边界，例如长度头或分隔符，并处理半包、粘包和写入不完整。
- `onPipeReadable()` 必须调用 `read()` 消费数据，否则 LT 事件会持续触发。
- `PIPE_TYPE_NONE` 不会创建可读写管道，不应配合当前实现的管道监听逻辑使用。
- 父进程退出前应发送终止信号并调用 `Process::wait()` 回收子进程。
- `onSigTerm()` 和 `onShutDown()` 在退出路径中同步执行，应快速完成，不要放入无法控制时长的阻塞操作。
- 默认 `onException()` 会重新抛出异常；生产环境建议覆盖并记录上下文。
- `addTick()`、`delay()` 和 `clearTick()` 分别用于添加循环定时器、延迟任务和清理定时器。

#### 使用 WaitGroup 管理生产者和消费者

当 `AbstractProcess` 内存在生产者、消费者等常驻协程时，不能在收到 SIGTERM 后立即退出事件循环。应使用同一个 WaitGroup 管理所有工作协程，在 `onShutDown()` 中先通知任务停止、关闭业务 Channel，再等待工作协程全部退出。

```php
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Component\WaitGroup;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Process;

class QueueProcess extends AbstractProcess
{
    private ?Channel $channel = null;
    private ?WaitGroup $waitGroup = null;
    private bool $isShutdown = false;

    protected function run($arg)
    {
        $this->channel = new Channel(24);
        $this->waitGroup = new WaitGroup(2);

        // add() 只登记任务，此时协程尚未启动。
        $this->waitGroup->add(function () {
            while (!$this->isShutdown) {
                // Channel 满时会挂起生产者；关闭 Channel 后 push() 返回 false。
                if (!$this->channel->push(1, 0.1)) {
                    break;
                }
            }
        });

        $this->waitGroup->add(function () {
            while (!$this->isShutdown || !$this->channel->isEmpty()) {
                $data = $this->channel->pop(0.1);
                if (
                    $data === false
                    && $this->channel->errCode === SWOOLE_CHANNEL_CLOSED
                ) {
                    break;
                }

                // 模拟业务 I/O，同时主动让出调度，让事件循环能够处理 SIGTERM。
                Coroutine::sleep(0.01);
            }
        });

        // 所有任务登记完成后只启动一次。
        $this->waitGroup->start();
    }

    protected function onShutDown()
    {
        // 先设置停止标记，再关闭 Channel，唤醒可能阻塞在 push()/pop() 的协程。
        $this->isShutdown = true;
        $this->channel?->close();

        // 只等待一次，确保工作协程退出后 AbstractProcess 才结束事件循环。
        if ($this->waitGroup !== null && !$this->waitGroup->wait(3)) {
            error_log('Timed out waiting for process workers to stop');
        }
    }
}

$worker = new QueueProcess('queue-worker', null);
$pid = $worker->getProcess()->start();

// 父进程退出时发送 SIGTERM，并负责回收子进程。
Process::kill($pid, SIGTERM);
Process::wait(true);
```

这个场景需要特别注意：

- 生产者和消费者必须全部通过同一个 WaitGroup 登记，并在登记完成后调用一次 `start()`。
- `onShutDown()` 中只调用一次 `wait()`；不要在其他位置提前等待同一个 WaitGroup。
- 必须先设置关闭状态，再关闭业务 Channel，最后等待工作协程退出。
- 关闭 Channel 可以唤醒阻塞中的 `push()` / `pop()`，业务循环还应检查其返回值并及时结束。
- 如果 Channel 操作持续立即成功，协程可能长时间占用调度器；循环内应存在真实异步 I/O，或使用短暂的 `Coroutine::sleep()` 主动让出调度。
- `wait()` 超时只表示停止等待，不会强制终止仍在运行的协程。超时应记录日志，并避免继续释放这些协程仍在使用的业务资源。

### Manager

集中保存自定义进程，并可挂载到 `Swoole\Server`。

```php
use EasySwoole\Component\Process\Manager;
use Swoole\Http\Server;

$server = new Server('0.0.0.0', 9501);
$manager = Manager::getInstance();
$manager->addProcess($worker);
$manager->attachToServer($server);

$server->on('request', function ($request, $response) {
    $response->end('ok');
});
$server->start();
```

进程运行后可用 `info()` 查询全部进程，或按 PID/分组查询；`kill($pidOrGroupName)` 可终止指定 PID 或分组。`kill()` 默认发送 `SIGTERM`，调用前应确认 PID 或分组范围。

### Process\Exception

`EasySwoole\Component\Process\Exception` 是进程组件使用的异常类型，继承自 PHP `Exception`。TCP/Unix Socket 配置缺失或绑定、监听失败时会抛出该异常，可在进程入口统一捕获并记录。

## Socket 服务进程

### AbstractTcpProcess

创建基于协程 Socket 的 TCP 服务进程。

```php
use EasySwoole\Component\Process\Socket\AbstractTcpProcess;
use EasySwoole\Component\Process\Socket\TcpProcessConfig;
use Swoole\Coroutine\Socket;

class TcpEchoProcess extends AbstractTcpProcess
{
    public function onAccept(Socket $socket)
    {
        $data = $socket->recv();
        if ($data !== false && $data !== '') {
            $socket->sendAll('echo:' . $data);
        }
        $socket->close();
    }
}

$config = new TcpProcessConfig();
$config->setProcessName('tcp-echo');
$config->setListenAddress('127.0.0.1');
$config->setListenPort(9502);
$config->setAsyncCallback(true);

$tcpProcess = new TcpEchoProcess($config);
$pid = $tcpProcess->getProcess()->start();
```

`asyncCallback=true` 时每个连接进入独立协程；关闭时连接串行处理。`onAccept()` 应负责协议解析、超时控制及关闭客户端 Socket。监听地址默认是 `0.0.0.0`，对外暴露服务前应确认网络和鉴权策略。

### AbstractUnixProcess

创建 Unix Domain Socket 服务进程，仅适用于支持 AF_UNIX 的系统。

```php
use EasySwoole\Component\Process\Socket\AbstractUnixProcess;
use EasySwoole\Component\Process\Socket\UnixProcessConfig;
use Swoole\Coroutine\Socket;

class UnixEchoProcess extends AbstractUnixProcess
{
    public function onAccept(Socket $socket)
    {
        $data = $socket->recv();
        if ($data !== false && $data !== '') {
            $socket->sendAll('echo:' . $data);
        }
        $socket->close();
    }
}

$config = new UnixProcessConfig();
$config->setProcessName('unix-echo');
$config->setSocketFile('/tmp/easyswoole-component.sock');
$config->setAsyncCallback(true);

$unixProcess = new UnixEchoProcess($config);
$pid = $unixProcess->getProcess()->start();
```

启动时会删除 `socketFile` 指向的既有文件，务必使用专用且明确的路径，不要指向业务文件。还应确保目录存在、运行用户具有读写权限，并在退出后按需清理 Socket 文件。

### TCP/Unix 配置项

`TcpProcessConfig` 和 `UnixProcessConfig` 都继承 `Config`，并强制启用协程。

- `TcpProcessConfig`：`listenAddress`、`listenPort`、`asyncCallback`、`linger`。
- `UnixProcessConfig`：`socketFile`、`asyncCallback`、`linger`。
- `linger` 默认值为 `['l_linger' => 0, 'l_onoff' => 0]`，必须符合 Swoole Socket 的 `SO_LINGER` 格式。
- TCP 进程启用了 `SO_REUSEPORT` 和 `SO_REUSEADDR`；多实例监听同一端口时仍需结合操作系统行为评估流量分配。

## 通用注意事项

- `Singleton`、普通数组容器和命名 Channel 都是进程内状态；只有 `Swoole\Atomic`、`Swoole\Table` 等专门结构可用于派生进程间共享数据。
- Swoole 对象通常与创建它的进程和事件循环绑定，不要把数据库连接、客户端 Socket 等对象直接当作可跨进程共享资源。
- 超时单位并不完全相同：Timer 为毫秒，Runner/WaitGroup/Csp/ReadyScheduler 为秒，Invoker 为微秒。
- 本组件多数异常会直接向上传递。生产环境应在协程入口、任务回调和进程入口统一记录并处理异常。
- 创建大量协程或向 Channel 写入大量数据时，应合理设置并发数、队列容量与超时，避免背压导致调用长期阻塞。

## License

[Apache-2.0](LICENSE)
