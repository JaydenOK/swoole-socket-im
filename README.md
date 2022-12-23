## swoole-socket
swoole socket im 聊天项目模块，普通单聊，店铺客服，群聊，发公告信息

#### 功能逻辑
```text

```

#### 版本
- PHP 7.1
- Swoole 4.5.11


#### 测试结果

```shell script
1, 启动服务: 
php service.php start

2.1, http服务注册账号获取access_token: 
curl -X POST -d "username=a&password=123456" http://192.168.92.208:9501/user/register

2.2, 登录获取access_token
curl -X POST -d "username=a&password=123456" http://192.168.92.208:9501/user/login 

3, 连接websocket: 
ws://192.168.92.208:9501?access_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJpbV9zZXJ2ZXIiLCJpYXQiOjE2NzE2OTY4NjIsImV4cCI6ODY0MDAsInVpZCI6Miwic2NvcGVzIjpbXX0.yjjVXII1S_HXv2xpZUhT79onfb3q2ijR0lAWgeVVCBA

4, 发送socket消息
{
    "chat_type":1,
    "msg_type":"text",
    "msg_id":"",
    "chat_id":"",
    "to_uid":2,
    "msg":"hello"
}



```