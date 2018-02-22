$(function(){
    var nickname = null;
    $('#connect-socket').click(function(){
        nickname = $('#nickname').val();

        var wsurl = 'ws://127.0.0.1:3301';
        var websocket;
        if(window.WebSocket && nickname){
            //关闭用户信息输入框
            $('#login_block').attr('style','');
            //页面刷新提示
            $(window).bind('beforeunload',function(){return '您输入的内容尚未保存，确定离开此页面吗？';});

            websocket = new WebSocket(wsurl);
            //连接建立
            websocket.onopen = function(evevt){
                send('login',nickname);
            };
            //收到消息
            websocket.onmessage = function(event) {
                var msg = JSON.parse(event.data); //解析收到的json消息数据
                if(msg.type === 'login' || msg.type === 'logout'){
                    var html = '';
                    for(var id=0;id < msg.user_list.length ;id++)
                    {
                        html += '<div class="line">' +
                            '<span class="picture"><img src="assets/img/header.jpg" alt="test"></span>' +
                            '<span class="nickname">'+ msg.user_list[id] +'</span>' +
                            '<span class="conversation"></span>' +
                            '</div>';
                    }
                    $('.user-block').html(html);
                }
                if(msg.type === 'system'){
                    $('.chat-block').append('<p class="bg-warning message"><a name=""></a><i class="glyphicon glyphicon-info-sign"></i>'+msg.content+' online</p>');
                }
                if(msg.type === 'IM' && msg.user !== nickname){
                    $('.chat-block').append(
                        '<div class="chat-block-left">' +
                        '<p class="chat-block-nickname">'+ nickname +'</p>' +
                        '<span>'+ msg.content +'</span>' +
                        '</div>'
                    );
                }
            };

            //发生错误
            websocket.onerror = function(){
                console.log("Connected to WebSocket server error");
                $('.chat-block').append('<p class="bg-danger message"><a name=""></a><i class="glyphicon glyphicon-info-sign"></i>Connect to WebSocket server error.</p>');

            };

            //连接关闭
            websocket.onclose = function(){
                console.log('websocket Connection Closed. ');
                $('.chat-block').append('<p class="bg-warning message"><a name=""></a><i class="glyphicon glyphicon-info-sign"></i>websocket Connection Closed.</p>');
            };

            function send(type , user , content){
                var msg = {
                    type: type,
                    user: user,
                    content: content
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
                    var message = $('#input_message').val();
                    if(!message)
                    {
                        alert('请先键入内容');
                        return;
                    }
                    send('IM',nickname,message);
                    $('#input_message').val('');
                    $('.chat-block').append(
                        '<div class="chat-block-right">' +
                        '<span>'+ message +'</span>' +
                        '</div>'
                    );
                }
            });

            //点发送按钮发送消息
            $('#send').bind('click',function(){
                var message = $('#input_message').val();
                if(!message)
                {
                    alert('请先键入内容');
                    return;
                }
                send('IM',nickname,message);
                $('#input_message').val('');
                $('.chat-block').append(
                    '<div class="chat-block-right">' +
                    '<span>'+ message +'</span>' +
                    '</div>'
                );
            });

        }
        else{
            alert('该浏览器不支持web socket');
        }
    });
});