function chatApp() {
    return {
        messages: [],
        userForm: {
            name: '',
            message: ''
        },
        websocket: null,
        wsUri: '',
        connectionStatus: 'disconnected',
        room: window.room ?? 1,
        keepaliveInterval: null,

        init() {
            this.wsUri = `${window.SERVER_PROTOCOL}://${window.SERVER_DOMAIN}:${window.SERVER_PORT}`;
            this.connectWebSocket();
        },

        connectWebSocket() {
            try {
                this.websocket = new WebSocket(this.wsUri);

                this.websocket.onopen = (ev) => {
                    this.connectionStatus = 'connected';
                    this.addSystemMessage('Connected to server');
                    this.sendKeepalive();

                    this.keepaliveInterval = setInterval(() => {
                        this.sendKeepalive();
                    }, 30000);
                };

                this.websocket.onerror = (ev) => {
                    this.connectionStatus = 'error';
                    this.addSystemMessage('Connection error occurred');
                };

                this.websocket.onclose = (ev) => {
                    this.connectionStatus = 'disconnected';
                    this.addSystemMessage('Connection closed, reconnecting...');
                    if (this.keepaliveInterval) {
                        clearInterval(this.keepaliveInterval);
                    }
                    // Retry connection after 3 seconds
                    setTimeout(() => this.connectWebSocket(), 3000);
                };

                this.websocket.onmessage = (ev) => {
                    try {
                        const response = JSON.parse(ev.data);
                        const res_type = response.type ?? 'usermsg';

                        if (res_type === 'usermsg' || res_type === 'msg' || res_type === '') {
                            this.messages.push({
                                type: 'usermsg',
                                name: response.name,
                                message: response.message
                            });
                        } else if (res_type === 'system') {
                            this.addSystemMessage(response.message);
                        }

                        this.$nextTick(() => {
                            const msgBox = document.querySelector('.message-box');
                            if (msgBox) {
                                msgBox.scrollTop = msgBox.scrollHeight;
                            }
                        });
                    } catch (e) {
                        console.error('Error parsing message:', e);
                    }
                };
            } catch (e) {
                console.error('WebSocket error:', e);
                this.connectionStatus = 'error';
            }
        },

        sendKeepalive() {
            if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                this.websocket.send(JSON.stringify({
                    message: '...',
                    name: this.userForm.name || 'Anonymous',
                    room: this.room,
                    type: 'keepalive'
                }));
            }
        },

        sendMessage() {
            if (!this.userForm.message.trim()) {
                return;
            }
            if (!this.userForm.name.trim()) {
                alert('Please enter your name');
                return;
            }

            if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                this.websocket.send(JSON.stringify({
                    message: this.userForm.message,
                    name: this.userForm.name,
                    room: this.room,
                    type: 'usermsg'
                }));
                this.userForm.message = '';
            } else {
                alert('Not connected to server');
            }
        },

        addSystemMessage(text) {
            this.messages.push({
                type: 'system',
                message: text,
                name: 'System'
            });
            this.$nextTick(() => {
                const msgBox = document.querySelector('.message-box');
                if (msgBox) {
                    msgBox.scrollTop = msgBox.scrollHeight;
                }
            });
        }
    };
}
