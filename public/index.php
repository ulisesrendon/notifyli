<?php require '../config/app.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifyli</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <style>
        body {
            background: linear-gradient(135deg, #1a0033 0%, #2d0052 25%, #1a0033 50%, #3d1066 75%, #1a0033 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        .glassmorphism {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        .message-box {
            scrollbar-width: thin;
            scrollbar-color: rgba(168, 85, 247, 0.5) transparent;
        }

        .message-box::-webkit-scrollbar {
            width: 6px;
        }

        .message-box::-webkit-scrollbar-track {
            background: transparent;
        }

        .message-box::-webkit-scrollbar-thumb {
            background: rgba(168, 85, 247, 0.5);
            border-radius: 10px;
        }

        .message-box::-webkit-scrollbar-thumb:hover {
            background: rgba(168, 85, 247, 0.7);
        }

        .gradient-text {
            background: linear-gradient(135deg, #a78bfa 0%, #e879f9 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="dark">
    <div class="min-h-screen flex items-center justify-center p-4" x-data="chatApp()">
        <div class="glassmorphism rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-900/40 to-pink-900/40 px-6 py-4 border-b border-purple-500/20">
                <h1 class="gradient-text text-2xl font-bold">Notifyli</h1>
                <p class="text-gray-400 text-sm mt-1" :style="connectionStatus === 'connected' ? {} : {}">
                    <span class="inline-block w-2 h-2 rounded-full mr-2"
                          :class="connectionStatus === 'connected' ? 'bg-green-400 animate-pulse' : 'bg-red-400'"></span>
                    <span x-text="connectionStatus === 'connected' ? 'Connected - ' + wsUri : 'Disconnected'"></span>
                </p>
            </div>

            <!-- Message Box -->
            <div class="p-6">
                <div class="message-box bg-slate-950/50 rounded-lg p-4 h-80 overflow-y-auto mb-4 border border-purple-500/20">
                    <template x-for="(msg, index) in messages" :key="index">
                        <div class="mb-3 animate-fade-in">
                            <!-- User Messages -->
                            <template x-if="msg.type === 'usermsg' || msg.type === 'msg' || !msg.type">
                                <div class="flex gap-2">
                                    <span class="text-purple-400 font-semibold text-sm" x-text="msg.name"></span>
                                    <span class="text-gray-300" x-text="msg.message"></span>
                                </div>
                            </template>

                            <!-- System Messages -->
                            <template x-if="msg.type === 'system'">
                                <div class="text-gray-500 text-sm italic text-center py-2" x-text="msg.message"></div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Input Panel -->
                <div class="space-y-3">
                    <!-- Name Input -->
                    <input
                        type="text"
                        x-model="userForm.name"
                        placeholder="Your Name"
                        maxlength="15"
                        class="w-full px-4 py-3 bg-slate-900/50 border border-purple-500/30 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                    />

                    <!-- Message Textarea -->
                    <textarea
                        x-model="userForm.message"
                        placeholder="Type your message here..."
                        @keydown.enter.ctrl="sendMessage()"
                        class="w-full px-4 py-3 bg-slate-900/50 border border-purple-500/30 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition resize-none h-20"
                    ></textarea>

                    <!-- Send Button -->
                    <button
                        @click="sendMessage()"
                        class="w-full px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-500 hover:to-pink-500 text-white font-semibold rounded-lg transition transform hover:scale-105 active:scale-95 shadow-lg"
                    >
                        Send Message
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.SERVER_PROTOCOL = "<?php echo $_ENV['APP_WS_SERVER_PROTOCOL'] ?>";
        window.SERVER_DOMAIN = "<?php echo $_ENV['APP_WS_SERVER_DOMAIN'] ?>";
        window.SERVER_PORT = "<?php echo $_ENV['APP_PORT'] ?>";
    </script>
    <script src="main.js" type="text/javascript"></script>
</body>

</html>
