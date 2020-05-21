multipass exec wp-cli-up -- bash <<EOMULTIPASSCMD

cd /etc/nginx/ssl

sudo rm -f <?php echo $domain; ?>.key <?php echo $domain; ?>.csr <?php echo $domain; ?>.crt \<?php echo $domain; ?>.ext

sudo rm -f /etc/nginx/sites-available/<?php echo $domain; ?>

sudo rm -f /etc/nginx/sites-enabled/<?php echo $domain; ?>

sudo service nginx reload

mysql -u root -proot -e 'DROP DATABASE <?php echo $dbname; ?>;'

mysql -u root -proot -e 'REVOKE ALL PRIVILEGES ON <?php echo $dbuser; ?>.* FROM "<?php echo $dbuser; ?>"@"localhost";'

mysql -u root -proot -e 'DROP USER IF EXISTS "<?php echo $dbuser; ?>"@"localhost"; FLUSH PRIVILEGES;'

sudo rm -Rf ~/wp-cli-up/sites/<?php echo $domain; ?>

EOMULTIPASSCMD
