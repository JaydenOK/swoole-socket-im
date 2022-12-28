## swoole-socket
swoole socket im 聊天项目，单聊，群聊，客服，发公告广播消息

#### 功能逻辑
```text
支持单聊，群聊，客服聊天，系统广播，聊天日志记录。  

客服聊天  
优先原则: 1，先选择上次联系过的且在线的客服。2，随机选择在线客服。  
```

#### 版本
- PHP 7.1
- Swoole 4.5.11


#### 单聊测试

```shell script
1, 启动服务: 
php service.php start 9501
php service.php start 9501 -d (守护进程启动)

2.1, http服务注册账号: 
curl -X POST -d "username=a&password=123456" http://192.168.92.208:9501/user/register

2.2, 登录获取access_token
curl -X POST -d "username=a&password=123456" http://192.168.92.208:9501/user/login 

3, 连接websocket: 
ws://192.168.92.208:9501?access_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJpbV9zZXJ2ZXIiLCJpYXQiOjE2NzE2OTY4NjIsImV4cCI6ODY0MDAsInVpZCI6Miwic2NvcGVzIjpbXX0.yjjVXII1S_HXv2xpZUhT79onfb3q2ijR0lAWgeVVCBA

4, 连接后, 发送socket消息
{
    "action":"single_chat",
    "chat_type":1,
    "msg_type":"text",
    "msg_id":"",
    "chat_id":"",
    "to_uid":2,
    "msg":"hello"
}

```

#### 群聊测试
```shell script

1, 创建群: 
curl -X POST 192.168.92.208:9501/room/create?access_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJpbV9zZXJ2ZXIiLCJpYXQiOjE2NzIxMjg4NDMsImV4cCI6ODY0MDAsInVpZCI6MSwic2NvcGVzIjpbXX0.uDjclECeghaXqlf_HRytmbVFjBlFitbJBgIrKi29qFM

返回:
{
    "code": 200,
    "message": "success",
    "data": {
        "chat_id": "2_1_221227173250"
    }
}

2,连接socket, 加入群聊: ws://192.168.92.208:9501?access_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJpbV9zZXJ2ZXIiLCJpYXQiOjE2NzE2OTY4NjIsImV4cCI6ODY0MDAsInVpZCI6Miwic2NvcGVzIjpbXX0.yjjVXII1S_HXv2xpZUhT79onfb3q2ijR0lAWgeVVCBA
{
    "action":"join_group",
    "chat_type":2,
    "chat_id":"2_1_221227173250"    
}

3,发送群聊信息: 
ws://192.168.92.208:9501?access_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJpbV9zZXJ2ZXIiLCJpYXQiOjE2NzE2OTY4NjIsImV4cCI6ODY0MDAsInVpZCI6Miwic2NvcGVzIjpbXX0.yjjVXII1S_HXv2xpZUhT79onfb3q2ijR0lAWgeVVCBA
{
    "action":"group_chat",
    "chat_type":2,
    "msg_type":"text",
    "chat_id":"2_1_221227173250",
    "msg":"hello,everyone."
}

```


#### 客服聊天测试
```shell script

1, 创建店铺yb_shop, 店铺客服yb_shop_service
2, 连接客服:
ws://192.168.92.208:9501?access_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9
{
    "action":"service_chat",
    "chat_type":3,
    "msg_type":"text",
    "chat_id":"3_1_221227173250",
    "shop_id":2,
    "msg":"hello, shop service."
}

```


#### 发送系统广播消息
```shell script
1, 发送系统广播消息 
curl -X POST -d "data=这是广播消息abc123" http://192.168.92.208:9501/message/sendPublicMessage?access_token=aaaaaa

```


