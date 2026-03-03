# Websocket communication app

## Local Running:

Run the socket process
```shell
php -q server.php
```

Run the reusable websocket bot (room 1 auto-reply on `hello`)
```shell
php -q bot.php
```

For docker:
```shell
docker compose exec php-fpm php -q /var/www/html/chat/server.php
```

Go http://chat.localhost for visualizing UI connecting to the server (public/index.php)


## Cloud deploy:

1. Prepare Domain and cloud with nginx and php
2. Configure port 7000 on your cloud infrastructure
3. Configure port in your linux firewall
```shell
sudo ufw allow 7000
```
4. Configure nginx block

```
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}
server {

    # Log files for Debugging
    access_log /var/log/nginx/chat.mydomain.com-access.log;
    error_log /var/log/nginx/chat.mydomain.com-error.log;

    root /var/www/chat/public;
    index index.php index.html index.htm;

    # Your Domain Name
    server_name chat.mydomain.com;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Configuration Nginx
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_read_timeout 500;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /websocket {
        proxy_pass http://chat.mydomain.com:7000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header Host $host;
    }



    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/chat.mydomain.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/chat.mydomain.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot

}

server {

    server_name chat.mydomain.com;
    listen 80;

    access_log /var/log/nginx/chat-access.log;
    error_log /var/log/nginx/chat-error.log;

    root /var/www/chat/public;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Configuration Nginx
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_read_timeout 500;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

}

```

5. Configure linux service to run constantly in background
```shell
sudo nano /etc/systemd/system/chat.service
```

```
[Unit]
Description=WS Chat PHP Server
 
[Service]
ExecStart=sudo php -q /var/www/chat/server.php &
 
[Install]
WantedBy=default.target
```

```shell
chmod 644 /etc/systemd/system/chat.service
systemctl enable chat.service
```

6. Add cronjob to restart service to avoid some problems
```shell
0 0 * * * sudo service chat restart
```

## Supervisor bot example

```ini
[program:notifyli-bot]
command=/usr/bin/php -q /var/www/chat/bot.php
directory=/var/www/chat
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/notifyli-bot.err.log
stdout_logfile=/var/log/supervisor/notifyli-bot.out.log
```
