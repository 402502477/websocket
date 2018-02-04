$(function(){
    var wsurl = 'ws://127.0.0.1:3301';
    var websocket;
    var i = 0;
    if(window.WebSocket){
        websocket = new WebSocket(wsurl);
        //连接建立
        websocket.onopen = function(evevt){
            //console.log("Connected to WebSocket server.");
            $('.show-area').append('<p class="bg-info message"><i class="glyphicon glyphicon-info-sign"></i>Connected to WebSocket server!</p>');
        };
        //收到消息
        websocket.onmessage = function(event) {
            var msg = JSON.parse(event.data); //解析收到的json消息数据

            var type = msg.type; // 消息类型
            var umsg = msg.message; //消息文本
            var uname = msg.name; //发送人
            i++;
            if(type == 'usermsg'){
                $('.show-area').append('<p class="bg-success message"><i class="glyphicon glyphicon-user"></i><a name="'+i+'"></a><span class="label label-primary">'+uname+' say: </span>'+umsg+'</p>');
            }
            if(type == 'system'){
                $('.show-area').append('<p class="bg-warning message"><a name="'+i+'"></a><i class="glyphicon glyphicon-info-sign"></i>'+umsg+' online</p>');
            }

            $('#message').val('');
            window.location.hash = '#'+i;
        }

        //发生错误
        websocket.onerror = function(event){
            i++;
            console.log("Connected to WebSocket server error");
            $('.show-area').append('<p class="bg-danger message"><a name="'+i+'"></a><i class="glyphicon glyphicon-info-sign"></i>Connect to WebSocket server error.</p>');
            window.location.hash = '#'+i;
        }

        //连接关闭
        websocket.onclose = function(event){
            i++;
            console.log('websocket Connection Closed. ');
            $('.show-area').append('<p class="bg-warning message"><a name="'+i+'"></a><i class="glyphicon glyphicon-info-sign"></i>websocket Connection Closed.</p>');
            window.location.hash = '#'+i;
        }

        function send(){
            var name = $('#name').val();
            var message = $('#message').val();
            if(!name){
                alert('请输入用户名!');
                return false;
            }
            if(!message){
                alert('发送消息不能为空!');
                return false;
            }
            var msg = {
                message: message,
                name: name
            };
            try{
                websocket.send(JSON.stringify(msg));
            } catch(ex) {
                console.log(ex);
            }
        }

        //按下enter键发送消息
        $(window).keydown(function(event){
            if(event.keyCode == 13){
                console.log('user enter');
                send();
            }
        });

        //点发送按钮发送消息
        $('.send').bind('click',function(){
            send();
        });

    }
    else{
        alert('该浏览器不支持web socket');
    }

});