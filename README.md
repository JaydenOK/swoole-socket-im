## swoole-coroutine-mysql-pool-task
Coroutine协程并发实例，适用于内部系统要处理大量耗时的任务  
常驻监听进程启动，Http Server + 协程 + 协程化mysql客户端 + channel 实现并发处理，可控制并发数量，分批次执行任务  


#### 功能逻辑
```text
- 启动http服务器，监听http端口（不同任务类型，启动不同端口）
- 请求回调，查询当前需要处理的总任务数；
- 将任务保存到任务channel，初始化限制并发数channel；
- 启动生产者协程投递任务，阻塞获取任务，并启动独立协程，并发处理任务；
- 任务完成，数据投递到数据channel,供消费者处理数据结果，并channel阻塞，继续投递任务到最大并发；

```

#### 版本
- PHP 7.1
- Swoole 4.5.11


#### 测试结果

```shell script

[root@ac_web ]# php service.php start Amazon 9901 
[root@ac_web ]# php service.php start Amazon 9901  -d  (守护进程启动)
[root@ac_web ]# php service.php start Amazon 9901  -d -pool  (守护进程启动，使用连接池方式)
 
[root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=5&total=200"
{"taskCount":200,"concurrency":5,"useTime":"56s"}
 
[root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=10&total=200"
{"taskCount":200,"concurrency":10,"useTime":"28s"}
 
[root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=20&total=200"
{"taskCount":200,"concurrency":20,"useTime":"10s"}
 
[root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=50&total=200"
{"taskCount":200,"concurrency":50,"useTime":"6s"}
 
[root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=200&total=500"
{"taskCount":500,"concurrency":200,"useTime":"3s"}

[root@ac_web ]# php service.php stop Amazon 

```