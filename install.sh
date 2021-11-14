#!/usr/bin/env bash

multipass launch 20.04 --name wp-cli-up --cloud-init - <<EOT
#cloud-config
packages:
  - software-properties-common
  - unzip
  - redis-server
runcmd:
  - echo "wp-cli-up" > /etc/hostname
  - hostname -F /etc/hostname
  - add-apt-repository --no-update universe
  - add-apt-repository --no-update -y ppa:ondrej/php
  - add-apt-repository --no-update -y ppa:ondrej/nginx
  - apt-get update -y
  - apt-get install -y nginx
  - mkdir -p /etc/nginx/ssl
  - curl -O https://downloads.spinupwp.com/nginx-config-kit-for-wordpress-1.3.zip
  - unzip nginx-config-kit-for-wordpress-1.3.zip
  - cp -Rf nginx-config-kit-for-wordpress-1.3/nginx.conf nginx-config-kit-for-wordpress-1.3/global/ /etc/nginx
  - rm -Rf nginx-config-kit-for-wordpress-1.3*
  - perl -p -i -e 's/^user .*/user ubuntu;/g' /etc/nginx/nginx.conf
  - service nginx reload
  - apt-get install -y php8.0-fpm php8.0-common php8.0-mysql php8.0-xml php8.0-xmlrpc php8.0-curl php8.0-gd php8.0-imagick php8.0-cli php8.0-dev php8.0-imap php8.0-mbstring php8.0-opcache php8.0-redis php8.0-soap php8.0-zip
  - perl -p -i -e 's/upload_max_filesize = .*/upload_max_filesize = 64M/g' /etc/php/8.0/fpm/php.ini
  - perl -p -i -e 's/post_max_size = .*/post_max_size = 64M/g' /etc/php/8.0/fpm/php.ini
  - perl -p -i -e 's/pm.max_children = .*/pm.max_children = 10/g' /etc/php/8.0/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.start_servers = .*/pm.start_servers = 4/g' /etc/php/8.0/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.min_spare_servers = .*/pm.min_spare_servers = 2/g' /etc/php/8.0/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.max_spare_servers = .*/pm.max_spare_servers = 6/g' /etc/php/8.0/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.max_spare_servers = .*/pm.max_spare_servers = 6/g' /etc/php/8.0/fpm/pool.d/www.conf
  - perl -p -i -e 's/user = .*/user = ubuntu/g' /etc/php/8.0/fpm/pool.d/www.conf
  - perl -p -i -e 's/group = .*/group = ubuntu/g' /etc/php/8.0/fpm/pool.d/www.conf
  - perl -p -i -e 's/listen.owner = .*/listen.owner = ubuntu/g' /etc/php/8.0/fpm/pool.d/www.conf
  - perl -p -i -e 's/listen.group = .*/listen.group = ubuntu/g' /etc/php/8.0/fpm/pool.d/www.conf
  - service php8.0-fpm restart
  - mkdir -p /home/ubuntu/.composer
  - echo "COMPOSER_HOME=/home/ubuntu/.composer" >> /etc/environment
  - curl -sS https://getcomposer.org/installer -o composer-setup.php
  - php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  - rm composer-setup.php
  - curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  - chmod +x wp-cli.phar
  - mv wp-cli.phar /usr/local/bin/wp
  - apt-get install -y mysql-server
  - service mysql stop
  - echo "" >> /etc/mysql/conf.d/mysql.cnf
  - echo "[mysqld]" >> /etc/mysql/conf.d/mysql.cnf
  - echo "default_authentication_plugin=mysql_native_password" >> /etc/mysql/conf.d/mysql.cnf
  - service mysql start
  - mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';"
  - mysql -e "FLUSH PRIVILEGES;"
EOT

mkdir -p ~/wp-cli-up

multipass mount ~/wp-cli-up wp-cli-up:/home/ubuntu/wp-cli-up

cd ~/wp-cli-up

mkdir -p sites root-ca

cd root-ca

openssl genrsa -des3 -out root-ca.key -passout pass:wpcliup 2048 2> /dev/null

openssl req -x509 -new -nodes -key root-ca.key -passin pass:wpcliup \
  -subj "/C=US/ST=Denial/L=Springfield/O=Dis/CN=www.wp-cli-up.org" -sha256 -days 1825 -out root-ca.pem

echo "Enter your macOS password to add the root certificate to your keychain..."

sudo security add-trusted-cert -d -r trustRoot -k "/Library/Keychains/System.keychain" root-ca.pem

wp package install --quiet --allow-root https://github.com/deliciousbrains/wp-cli-up.git
