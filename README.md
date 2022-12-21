## swoole-socket
swoole-socket 聊天项目

#### 功能逻辑
```text

```

#### 版本
- PHP 7.1
- Swoole 4.5.11


#### 测试结果

```shell script
1, 启动: 
php service.php start

2, http服务注册账号获取access_token: 
http://192.168.92.208:9501/user/register

3, 携带access_token连接websocket: 
ws://192.168.92.208:9501
```