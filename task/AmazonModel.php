<?php

namespace module\task;

class AmazonModel extends TaskModel
{

    public function tableName()
    {
        return 'yibai_amazon_account';
    }

    public function getTaskList($params)
    {
        if ($this->isUsePool) {
            //冒号 : 占位符与参数绑定 bindValue() 执行
            //问号 ? 占位符与参数绑定 bindValue() 执行
            //绑定冒号前有空格（注意）
            $sql = "select * from {$this->tableName()} where id < :id limit :limit";
            $statement = $this->poolObject->prepare($sql);
            if (!$statement) {
                throw new \RuntimeException('Prepare failed');
            }
            $maxId = 5000;
            $statement->bindValue(':id', $maxId, \PDO::PARAM_INT);
            $statement->bindValue(':limit', $params['limit'], \PDO::PARAM_INT);
            $result = $statement->execute();
            if (!$result) {
                throw new \RuntimeException('Execute failed');
            }
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            // TODO: Implement getTaskList() method.
            //$result = $this->query->where('id', 5000, '<')->get($this->tableName(), $params['limit']);      //查所有
            $result = $this->query->where('id', 5000, '<')->page(1)->limit($params['limit'])->paginate($this->tableName());
        }
        return $result;
    }

    /**
     * 重新解压，编译支持https
     * phpize && ./configure --enable-openssl --enable-http2 && make && sudo make install
     * @param $id
     * @param $task
     * @return mixed
     * @throws \Exception
     */
    public function taskRun($id, $task)
    {
        // TODO: Implement taskRun() method.
        //todo 模拟业务耗时处理逻辑
        if ($this->isUsePool) {
            $sql = "update {$this->tableName()} set refresh_num = :refresh_num, update_time = :update_time where id = :id";
            $statement = $this->poolObject->prepare($sql);
            if (!$statement) {
                throw new \RuntimeException('Prepare failed');
            }
            $statement->bindValue(':refresh_num', mt_rand(1, 10), \PDO::PARAM_INT);
            $statement->bindValue(':update_time', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $result = $statement->execute();
            if ($statement->rowCount() > 0) {
                $res = $result;
            } else {
                throw new \RuntimeException('Update Execute failed：' . print_r($statement->errorInfo(), true));
            }
        } else {
            $data = ['refresh_num' => mt_rand(0, 10)];
            $res = $this->query->where('id', $task['id'])->update($this->tableName(), $data);
        }
        $id = $task['id'];
        $appId = $task['app_id'];
        $sellingPartnerId = $task['selling_partner_id'];
        $host = 'api.amazon.com';
        $path = '/auth/o2/token';
        $data = [];
        $data['grant_type'] = 'refresh_token';
        $data['client_id'] = '111';
        $data['client_secret'] = '222';
        $data['refresh_token'] = '333';
        $cli = new \Swoole\Coroutine\Http\Client($host, 443, true);
        $cli->set(['timeout' => 10]);
        $cli->setHeaders([
            'Host' => $host,
            'grant_type' => 'refresh_token',
            'client_id' => 'refresh_token',
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
        ]);
        $cli->post($path, http_build_query($data));
        $responseBody = $cli->body;
        return $responseBody;
    }

    public function taskDone($id, $data)
    {
        // TODO: Implement taskDone() method.
        if ($this->isUsePool) {
            $sql = "update {$this->tableName()} set refresh_msg=:refresh_msg, refresh_time=:refresh_time where id=:id";
            $statement = $this->poolObject->prepare($sql);
            if (!$statement) {
                throw new \RuntimeException('Prepare failed');
            }
            $statement->bindValue(':refresh_msg', json_encode($data, 256), \PDO::PARAM_STR);
            $statement->bindValue(':refresh_time', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $result = $statement->execute();
            if ($statement->rowCount() <= 0 || !$result) {
                throw new \RuntimeException('Update Execute failed');
            }
            $res = $result;
        } else {
            $data = ['refresh_msg' => json_encode($data, 256), 'refresh_time' => date('Y-m-d H:i:s')];
            $res = $this->query->where('id', $id)->update($this->tableName(), $data);
        }
    }


}