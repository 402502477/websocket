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
        $this->sockets[] = $this->socket;
    }

    /**
     *进行套接字的轮询
     */
    public function process()
    {
        //创建循环,保持连接状态
        while(true)
        {
            //将当前的套接字绑定进入独立的频道
            $chanel = $this->sockets;
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
                $this->sockets[] = $new_socket;

                //通过socket获取数据执行handshake
                $header = socket_read($new_socket,1024);
                $this->perform_handshaking($header,$new_socket,$this->host,$this->port);
                socket_getpeername($new_socket, $ip);
                $response = $this->mask(json_encode([
                    'type' => 'system',
                    'ip' => $ip,
                ]));

                $res = $this->send_message($response);
                if($res !== true)
                {
                    $this->error('Send message failed : ','socket');
                }
                $found_socket = array_search($this->socket, $chanel);
                unset($chanel[$found_socket]);
            }

            foreach($chanel as $socket)
            {
                while(socket_recv($socket,$buf,1024,0) >= 1)
                {
                    //解码发送过来的数据
                    $received_text = $this->unmask($buf);
                    $tst_msg = json_decode($received_text);
                    $user_name = $tst_msg->name;
                    $user_message = $tst_msg->message;

                    //把消息发送回所有连接的 client 上去
                    $response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message)));
                    send_message($response_text);
                    break 2;
                }

                //检查offline的client
                $buf = @socket_read($socket, 1024, PHP_NORMAL_READ);
                echo $buf;
                if ($buf === false) {
                    $found_socket = array_search($socket, $this->sockets);
                    socket_getpeername($socket, $ip);
                    unset($this->sockets[$found_socket]);
                    $response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));
                    send_message($response);
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
        foreach($this->sockets as $socket)
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
        $error_text = '['.date('Y-m-d H:i:s').']'.$msg;
        file_put_contents('error.log',$error_text,FILE_APPEND);

    }
}
$host = '127.0.0.1';
$port = 80;
$ws = new Socket($host,$port);