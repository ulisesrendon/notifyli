<?php require '../config/app.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="main.css">
    <title>Notifyly</title>
</head>
<body>

    <div class="chat-wrapper">
        <div id="message-box"></div>
        <div class="user-panel">
            <input type="text" name="name" id="name" placeholder="Your Name" maxlength="15" />
            <textarea name="message" id="message" placeholder="Type your message here..."></textarea>
            <button id="send-message">Send</button>
        </div>
    </div>

    <script>
        window.SERVER_PROTOCOL = "<?php echo $_ENV['APP_WS_SERVER_PROTOCOL'] ?>";
        window.SERVER_DOMAIN = "<?php echo $_ENV['APP_WS_SERVER_DOMAIN'] ?>";
        window.SERVER_PORT = "<?php echo $_ENV['APP_PORT'] ?>";
        // window.SERVER_PORT = 443;
    </script>
    <script src="main.js" type="text/javascript"></script>
</body>

</html>
