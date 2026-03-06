function chatApp() {
    return {
        messages: [],
        userForm: {
            userId: '',
            room: '1',
            name: '',
            message: ''
        },
        websocket: null,
        wsUri: '',
        connectionStatus: 'disconnected',
        keepaliveInterval: null,

        init() {
            this.wsUri = `${window.SERVER_PROTOCOL}://${window.SERVER_DOMAIN}:${window.SERVER_PORT}`;
            // Load userId from localStorage if available
            const savedUserId = localStorage.getItem('notifyli_user_id');
            if (savedUserId) {
                this.userForm.userId = savedUserId;
            }
            const savedRoom = localStorage.getItem('notifyli_room');
            if (savedRoom) {
                this.userForm.room = savedRoom;
            }
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
                        } else if (res_type === 'direct_message') {
                            // Direct message from Redis notification system
                            this.messages.push({
                                type: 'usermsg',
                                name: `${response.name} [notification]`,
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
                const payload = {
                    message: '...',
                    name: this.userForm.name || 'Anonymous',
                    room: this.userForm.room || '1',
                    type: 'keepalive'
                };

                if ((this.userForm.userId || '').trim() !== '') {
                    payload.user_id = this.userForm.userId.trim();
                }

                this.websocket.send(JSON.stringify(payload));
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
            if (!(this.userForm.userId || '').trim()) {
                alert('Please enter your User ID');
                return;
            }

            if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                // Save userId and room to localStorage
                localStorage.setItem('notifyli_user_id', this.userForm.userId);
                localStorage.setItem('notifyli_room', this.userForm.room);

                this.websocket.send(JSON.stringify({
                    message: this.userForm.message,
                    name: this.userForm.name,
                    user_id: this.userForm.userId.trim(),
                    room: this.userForm.room || '1',
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
