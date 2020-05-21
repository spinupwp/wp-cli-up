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
  - apt-key adv --fetch-keys 'https://mariadb.org/mariadb_release_signing_key.asc'
  - add-apt-repository --no-update -y 'deb [arch=amd64,arm64,ppc64el] http://mirrors.up.pt/pub/mariadb/repo/10.4/ubuntu focal main'
  - mkdir -p /home/ubuntu/.composer
  - echo "COMPOSER_HOME=/home/ubuntu/.composer" >> /etc/environment
  - curl -sS https://getcomposer.org/installer -o composer-setup.php
  - php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  - rm composer-setup.php
  - curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  - chmod +x wp-cli.phar
  - mv wp-cli.phar /usr/local/bin/wp
  - apt-get update -y
  - apt-get install -y nginx
  - mkdir -p /etc/nginx/ssl
  - curl -O https://downloads.spinupwp.com/nginx-config-kit-for-wordpress-1.3.zip
  - unzip nginx-config-kit-for-wordpress-1.3.zip
  - cp -Rf nginx-config-kit-for-wordpress-1.3/nginx.conf nginx-config-kit-for-wordpress-1.3/global/ /etc/nginx
  - rm -Rf nginx-config-kit-for-wordpress-1.3*
  - perl -p -i -e 's/^user .*/user ubuntu;/g' /etc/nginx/nginx.conf
  - service nginx reload
  - apt-get install -y php7.4-fpm php7.4-common php7.4-mysql php7.4-xml php7.4-xmlrpc php7.4-curl php7.4-gd php7.4-imagick php7.4-cli php7.4-dev php7.4-imap php7.4-mbstring php7.4-opcache php7.4-redis php7.4-soap php7.4-zip
  - perl -p -i -e 's/upload_max_filesize = .*/upload_max_filesize = 64M/g' /etc/php/7.4/fpm/php.ini
  - perl -p -i -e 's/post_max_size = .*/post_max_size = 64M/g' /etc/php/7.4/fpm/php.ini
  - perl -p -i -e 's/pm.max_children = .*/pm.max_children = 10/g' /etc/php/7.4/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.start_servers = .*/pm.start_servers = 4/g' /etc/php/7.4/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.min_spare_servers = .*/pm.min_spare_servers = 2/g' /etc/php/7.4/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.max_spare_servers = .*/pm.max_spare_servers = 6/g' /etc/php/7.4/fpm/pool.d/www.conf
  - perl -p -i -e 's/pm.max_spare_servers = .*/pm.max_spare_servers = 6/g' /etc/php/7.4/fpm/pool.d/www.conf
  - perl -p -i -e 's/user = .*/user = ubuntu/g' /etc/php/7.4/fpm/pool.d/www.conf
  - perl -p -i -e 's/group = .*/group = ubuntu/g' /etc/php/7.4/fpm/pool.d/www.conf
  - perl -p -i -e 's/listen.owner = .*/listen.owner = ubuntu/g' /etc/php/7.4/fpm/pool.d/www.conf
  - perl -p -i -e 's/listen.group = .*/listen.group = ubuntu/g' /etc/php/7.4/fpm/pool.d/www.conf
  - service php7.4-fpm restart
  - apt-get install -y mariadb-server
  - mysql -e "UPDATE mysql.global_priv SET priv=json_set(priv, '$.password_last_changed', UNIX_TIMESTAMP(), '$.plugin', 'mysql_native_password', '$.authentication_string', 'invalid', '$.auth_or', json_array(json_object(), json_object('plugin', 'unix_socket'))) WHERE User='root';"
  - mysql -e "UPDATE mysql.global_priv SET priv=json_set(priv, '$.plugin', 'mysql_native_password', '$.authentication_string', PASSWORD('root')) WHERE User='root';"
  - mysql -e "DELETE FROM mysql.global_priv WHERE User='';"
  - mysql -e "DELETE FROM mysql.global_priv WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
  - mysql -e "DROP DATABASE IF EXISTS test;"
  - mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'"
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

# wp package install https://github.com/bradt/wp-cli-up
