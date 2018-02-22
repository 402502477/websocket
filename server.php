<?php
set_time_limit(0);
date_default_timezone_set('Asia/shanghai');

class Socket{
    protected $host = null;
    protected $port = 80;
    protected $socket = null;
    protected $sockets = [];

    /**
     * Socket constructor.
     * @param string $host
     * @param int $port
     */
    function __construct($host,$port)
    {
        $this->host = $host;
        $this->port = $port;
        $this->create();
        $this->process();
    }

    /**
     *析构函数 关闭套接字
     */
    function __destruct()
    {
        socket_close($this->socket);
    }

    /**
     * 创建tep socket 套接字
     */
    public function create()
    {
        //创建并返回一个套接字!
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)or $this->error('Socket create failed:','socket');

        // 设置IP和端口重用,在重启服务器后能重新使用此端口;
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1)or $this->error('Socket set option failed:','socket');

        //给套接字绑定名字
        socket_bind($this->socket, $this->host, $this->port)or $this->error('Socket set option failed:','socket');

        //监听套接字上的连接
        socket_listen($this->socket)or $this->error('Socket set option failed:','socket');

        //将创建完毕的套接字放进数组
        $this->sockets[0] = ['resource' => $this->socket];
    }

    /**
     *进行套接字的轮询
     */
    public function process()
    {
        //定义当前连接用户列表
        $user_list = [];

        //创建循环,保持连接状态
        while(true)
        {
            //将当前的套接字绑定进入独立的频道
            $chanel = array_column($this->sockets, 'resource');
            //select作为监视函数,参数分别是(监视可读,可写,异常,超时时间),返回可操作数目,出错时返回false;
            $select_res = socket_select($chanel,$write,$except,0,10);
            if($select_res === false)
            {
                $this->error('Socket select failed : ','socket');
            }


            //判断新加入的套接字执行的逻辑
            if(in_array($this->socket,$chanel))
            {
                //接受套接字的连接
                $new_socket = socket_accept($this->socket);

                //通过socket获取数据执行handshake
                $header = socket_read($new_socket,1024);
                $this->perform_handshaking($header,$new_socket,$this->host,$this->port);
                //获得套接字的ip地址
                $res = socket_getpeername($new_socket, $ip);
                if($res)
                {
                    $this->runtime($ip,'online');
                    //为新加入的socket绑定基本信息
                    $this->sockets[(int)$new_socket]['resource'] = $new_socket;
                    $this->sockets[(int)$new_socket]['ip'] = $ip;
                    $this->sockets[(int)$new_socket]['login_time'] = date('Y-m-d H:i:s');
                }

                $response = [
                    'type' => 'system',
                    'content' => $ip,
                ];

                $res = $this->send_message($response);
                if($res !== true)
                {
                    $this->error('Send message failed : ','socket');
                }else{
                    $this->runtime('Push message succeed','Send message');
                }
                $found_socket = array_search($this->socket, $chanel);
                unset($chanel[$found_socket]);
            }

            foreach($chanel as $socket)
            {
                $res = @socket_recv($socket,$buf,1024,0);
                while($res >= 1)
                {
                    $response = null;

                    //解码发送过来的数据
                    $request = json_decode($this->unmask($buf),true);
                    if($request)
                    {
                        switch ($request['type']){
                            case 'login':
                                //写入变量$user_list
                                $user_list[] = $request['user'];
                                sort($user_list);
                                //登陆请求
                                $this->sockets[(int)$socket]['name'] = $request['user'];
                                $response['type'] = $request['type'];
                                $response['user'] = $request['user'];
                                $response['user_list'] = $user_list;
                                break;
                            case 'IM':
                                //即时通讯请求
                                $response['type'] = $request['type'];
                                $response['user'] = $request['user'];
                                $response['content'] = $request['content'];
                                break;
                        }

                        $res = $this->send_message($response);
                        if($res !== true)
                        {
                            $this->error('Send message failed : ','socket');
                        }else{
                            $this->runtime('Send Message','Send message');
                        }
                    }

                    break 2;
                }

                //检查offline的client
                $buf = @socket_read($socket, 1024, PHP_NORMAL_READ);
                if ($buf === false) {

                    $ip = $this->sockets[(int)$socket]['ip'];
                    unset($user_list[array_search($this->sockets[(int)$socket]['name'],$user_list)]);
                    unset($this->sockets[(int)$socket]);

                    //重组user list列表
                    sort($user_list);
                    $response = [
                        'type' => 'logout',
                        'user_list' => $user_list
                    ];
                    $this->send_message($response);

                    $this->runtime($ip.' disconnected','offline');

                    $response = [
                        'type'=>'system',
                        'content'=>$ip.' disconnected'
                    ];
                    $this->send_message($response);
                }
            }
        }
    }

    /**
     * 发送消息的方法
     * @param $msg
     * @return bool
     */
    function send_message($msg)
    {
        $msg = $this->mask(json_encode($msg));
        $chanel = array_column($this->sockets,'resource');
        foreach($chanel as $socket)
        {
            @socket_write($socket,$msg,strlen($msg));
        }
        return true;
    }

    /**
     * 解码数据
     * @param $text
     * @return string
     */
    protected function unmask($text) {
        $length = ord($text[1]) & 127;
        if($length == 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        }
        elseif($length == 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        }
        else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        $text = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i%4];
        }
        return $text;
    }

    /**
     * 编码数据
     * @param $text
     * @return string
     */
    protected function mask($text)
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);

        if($length <= 125)
            $header = pack('CC', $b1, $length);
        elseif($length > 125 && $length < 65536)
            $header = pack('CCn', $b1, 126, $length);
        elseif($length >= 65536)
            $header = pack('CCNN', $b1, 127, $length);
        return $header.$text;
    }

    /**
     * 握手的逻辑
     * @param $receved_header
     * @param $client_conn
     * @param $host
     * @param $port
     */
    protected function perform_handshaking($receved_header, $client_conn, $host, $port)
    {
        $headers = array();
        $lines = preg_split("/\r\n/", $receved_header);
        foreach($lines as $line)
        {
            $line = chop($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
            {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $host\r\n" .
            "WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
            "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        socket_write($client_conn,$upgrade,strlen($upgrade));
    }

    /**
     * @param string $msg
     * @param string $type
     */
    protected function error($msg,$type = null)
    {
        if($type == 'socket')
        {
            $code = socket_last_error();
            if($msg)
            {
                $msg .= socket_strerror($code);
            }else{
                $msg = socket_strerror($code);
            }
        }
        if(!is_dir(__DIR__.'/log'))
        {
            mkdir(__DIR__.'/log');
        }
        $error_text = '['.date('Y-m-d H:i:s').']'.$msg."\n";
        file_put_contents(__DIR__.'/log/error.log',$error_text,FILE_APPEND);

    }
    protected function runtime($msg,$type)
    {
        if(!is_dir(__DIR__.'/log'))
        {
            mkdir(__DIR__.'/log');
        }
        $test = '['.date('Y-m-d H:i:s').']'.$type.':'.$msg."\n";
        file_put_contents(__DIR__.'/log/runtime.log',$test,FILE_APPEND);
    }
}
$config = file_get_contents('config.json');
$host =json_decode($config,true);
$ws = new Socket($host['server_host'],$host['port']);