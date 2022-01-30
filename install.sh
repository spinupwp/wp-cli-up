#!/usr/bin/env bash

echo "Starting wp-cli-up..."

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
  - apt-get install -y php8.1-fpm php8.1-common php8.1-mysql php8.1-xml php8.1-xmlrpc php8.1-curl php8.1-gd php8.1-imagick php8.1-cli php8.1-dev php8.1-imap php8.1-mbstring php8.1-opcache php8.1-redis php8.1-soap php8.1-zip
  - perl -p -i -e 's/upload_max_filesize = .*/upload_max_filesize = 64M/g' /etc/php/8.1/fpm/php.ini
  - perl -p -i -e 's/post_max_size = .*/post_max_size = 64M/g' /etc/php/8.1/fpm/php.ini
  - perl -p -i -e 's/pm.max_children = .*/pm.max_children = 10/g' /etc/php/8.1/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.start_servers = .*/pm.start_servers = 4/g' /etc/php/8.1/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.min_spare_servers = .*/pm.min_spare_servers = 2/g' /etc/php/8.1/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.max_spare_servers = .*/pm.max_spare_servers = 6/g' /etc/php/8.1/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.max_spare_servers = .*/pm.max_spare_servers = 6/g' /etc/php/8.1/fpm/pool.d/www.conf
  - perl -p -i -e 's/user = .*/user = ubuntu/g' /etc/php/8.1/fpm/pool.d/www.conf
  - perl -p -i -e 's/group = .*/group = ubuntu/g' /etc/php/8.1/fpm/pool.d/www.conf
  - perl -p -i -e 's/listen.owner = .*/listen.owner = ubuntu/g' /etc/php/8.1/fpm/pool.d/www.conf
  - perl -p -i -e 's/listen.group = .*/listen.group = ubuntu/g' /etc/php/8.1/fpm/pool.d/www.conf
  - service php8.1-fpm restart
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
  - echo "" >> /etc/mysql/mysql.conf.d/mysqld.cnf
  - echo "default_authentication_plugin=mysql_native_password" >> /etc/mysql/mysql.conf.d/mysqld.cnf
  - service mysql start
  - mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';"
  - mysql -uroot -proot -e "FLUSH PRIVILEGES;"
EOT

echo "Enter your Ubuntu password to allow wp-cli-up to continue..."

sudo mkdir -p ~/wp-cli-up

multipass mount ~/wp-cli-up wp-cli-up:/home/ubuntu/wp-cli-up

cd ~/wp-cli-up

mkdir -p sites root-ca

cd root-ca

openssl genrsa -des3 -out root-ca.key -passout pass:wpcliup 2048 2> /dev/null

openssl req -x509 -new -nodes -key root-ca.key -passin pass:wpcliup \
  -subj "/C=US/ST=Denial/L=Springfield/O=Dis/CN=www.wp-cli-up.org" -sha256 -days 1825 -out root-ca.pem

sudo apt-get install -y ca-certificates

sudo cp ~/wp-cli-up/root-ca/root-ca.pem /usr/local/share/ca-certificates/root-ca.crt

sudo update-ca-certificates

sudo wp package install --quiet --allow-root https://github.com/deliciousbrains/wp-cli-up.git
