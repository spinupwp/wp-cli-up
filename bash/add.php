multipass exec wp-cli-up -- bash <<EOMULTIPASSCMD

cd ~/wp-cli-up/sites

mkdir -p <?php echo $domain; ?>/files/public <?php echo $domain; ?>/logs

cd /etc/nginx/ssl

sudo tee -a <?php echo $domain; ?>.ext > /dev/null <<EOT
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
subjectAltName = @alt_names

[alt_names]
DNS.1 = <?php echo $domain,"\n"; ?>
DNS.2 =
EOT

sudo openssl genrsa -out <?php echo $domain; ?>.key 2048 2> /dev/null

sudo openssl req -new -key <?php echo $domain; ?>.key -out <?php echo $domain; ?>.csr -subj '/C=US/ST=Denial/L=Springfield/O=Dis/CN=<?php echo $domain; ?>'

sudo openssl x509 -req -in <?php echo $domain; ?>.csr \
-CA ~/wp-cli-up/root-ca/root-ca.pem -CAkey ~/wp-cli-up/root-ca/root-ca.key -CAcreateserial \
-passin pass:wpcliup -out <?php echo $domain; ?>.crt -days 825 -sha256 -extfile <?php echo $domain; ?>.ext 2> /dev/null

cd /etc/nginx/sites-available

sudo tee -a <?php echo $domain; ?> > /dev/null <<EOT
server {
	listen 443 ssl http2;
	server_name <?php echo $domain; ?>;
	root /home/ubuntu/wp-cli-up/sites/<?php echo $domain; ?>/files/public;

	ssl_certificate /etc/nginx/ssl/<?php echo $domain; ?>.crt;
	ssl_certificate_key /etc/nginx/ssl/<?php echo $domain; ?>.key;

	index index.php;

	access_log /home/ubuntu/wp-cli-up/sites/<?php echo $domain; ?>/logs/access.log;
	error_log /home/ubuntu/wp-cli-up/sites/<?php echo $domain; ?>/logs/error.log;

	include global/server/defaults.conf;
	include global/server/ssl.conf;

	location / {
		try_files \\\$uri \\\$uri/ /index.php?\\\$args;
	}

	location ~ \.php$ {
		try_files \\\$uri =404;
		include global/fastcgi-params.conf;
		fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
	}
}

server {
	listen 80;
	server_name <?php echo $domain; ?>;

	return 301 https://<?php echo $domain; ?>;
}
EOT

sudo ln -s /etc/nginx/sites-available/<?php echo $domain; ?> /etc/nginx/sites-enabled/<?php echo $domain; ?>

sudo service nginx reload

mysql -u root -proot -e 'DROP USER IF EXISTS "<?php echo $dbuser; ?>"@"localhost";'

mysql -u root -proot -e 'CREATE USER "<?php echo $dbuser; ?>"@"localhost" IDENTIFIED BY "<?php echo $dbpass; ?>";'

mysql -u root -proot -e 'GRANT ALL PRIVILEGES ON <?php echo $dbname; ?>.* TO "<?php echo $dbuser; ?>"@"localhost"; FLUSH PRIVILEGES;'

cd ~/wp-cli-up/sites/<?php echo $domain; ?>/files/public

wp core download --quiet

wp core config --quiet --dbname='<?php echo $dbname; ?>' --dbuser='<?php echo $dbuser; ?>' --dbpass='<?php echo $dbpass; ?>' --dbprefix='<?php echo $dbprefix; ?>'

wp db create --quiet

wp core install --quiet --url='https://<?php echo $domain; ?>' --title='<?php echo $title; ?>' --admin_user='<?php echo $admin_user; ?>' --admin_email='<?php echo $admin_email; ?>' --admin_password='<?php echo $admin_password; ?>' --skip-email

EOMULTIPASSCMD
