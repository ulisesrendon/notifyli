function notifyliInit(msgBox){
    const wsUri = `${window.SERVER_PROTOCOL}://${window.SERVER_DOMAIN}:${window.SERVER_PORT}`;
    window.websocket = new WebSocket(wsUri);
    window.websocket.onopen = function (ev) {
        msgBox.innerHTML += `<div class="system_msg" style="color:#bbbbbb">Connected! - ${wsUri}</div>`;
        window.websocket.send(JSON.stringify({
            message: '...',
            name: document.querySelector('#name').value,
            room: window.room ?? 1,
            type: 'keepalive'
        }));

        setInterval(function () {
            window.websocket.send(JSON.stringify({
                message: '...',
                name: document.querySelector('#name').value,
                room: window.room ?? 1,
                type: 'keepalive'
            }));
        },30000);
    }
    window.websocket.onerror = function (ev) {
        msgBox.innerHTML += '<div class="system_error">Error Occurred - ' + ev.data + '</div>';
    };
    window.websocket.onclose = function (ev) {
        msgBox.innerHTML += '<div class="system_msg">Connection Closed</div>';
        notifyliInit(msgBox);
    };
    window.websocket.onmessage = function (ev) {
        const response = JSON.parse(ev.data);
        const res_type = response.type ?? 'usermsg'; //message type
        const user_message = response.message; //message text
        const user_name = response.name; //user name

        switch (res_type) {
            case '':
            case 'msg':
            case 'usermsg':
                msgBox.innerHTML += '<div><span class="user_name" style="color:purple">' + user_name + '</span> : <span class="user_message">' + user_message + '</span></div>';
                msgBox.scrollTop = msgBox.scrollHeight; //scroll message
                break;
            case 'system':
                msgBox.innerHTML += '<div style="color:#bbbbbb">' + user_message + '</div>';
                msgBox.scrollTop = msgBox.scrollHeight; //scroll message
                break;
        }
    };
}

window.addEventListener("load", function () {
    const msgBox = document.querySelector("#message-box");
    const message_input = document.querySelector('#message');
    const name_input = document.querySelector('#name');

    notifyliInit(msgBox);

    document.querySelector('#send-message').addEventListener('click', send_message);

    document.querySelector('#message').addEventListener("keydown", function (event) {
        if (event.which == 13) {
            send_message();
        }
    });

    function send_message() {
        if (message_input.value == "") {
            //alert("Enter Some message please!");
            return;
        }
        if (name_input.value == "") {
            alert("Enter your Name Please!");
            return;
        }

        window.websocket.send(JSON.stringify({
            message: message_input.value,
            name: name_input.value,
            room: window.room ?? 1,
            type: 'usermsg'
        }));
        message_input.value = ''; //reset message input
        message_input.focus();
    }
});
