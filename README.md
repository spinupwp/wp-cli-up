# wp-cli-up

A WP-CLI package to set up a [Multipass](https://multipass.run/) virtual machine (VM) and manage WordPress sites on the VM remotely from the Ubuntu terminal.

## Requirements

* [Multipass](https://multipass.run/)
* [WP-CLI](https://wp-cli.org/)

## Installation

    curl -o- https://raw.githubusercontent.com/deliciousbrains/wp-cli-up/master/install.sh | bash

Once installation is complete, you should have a new Multipass VM called 'wp-cli-up' with the following software is installed:

* PHP 8.1
* Nginx
* MySQL (root user password is 'root')
* Composer
* WP-CLI
* Redis

In addition, a root SSL certificate is generated and stored on your Ubuntu certificate store to allow the generation trusted SSL certificates for each site. You will find the root certificate in your \~/wp-cli-up folder.

Also in your \~/wp-cli-up folder, you will find a sites folder, this is where your site files will be found.

Let's add our first site...

## Add a Site

    sudo wp up add --allow-root <domain>

_The command needs to be run as root so the macOS /etc/hosts file can be updated._

Adding a site will generate a new SSL certificate, create a new Nginx configuration, create a new database and database user, and download and install WordPress.

## Remove a Site

    sudo wp up remove --allow-root <domain>

_The command needs to be run as root so the macOS /etc/hosts file can be updated._

Removing a site will remove all the SSL certificate files, Nginx confiugration files, database, database user, and site files.

## SSH to the VM

    multipass shell wp-cli-up

## Uninstall

    multipass delete wp-cli-up
    multipass purge
    sudo rm /usr/local/share/ca-certificates/root-ca.crt
    sudo update-ca-certificates
    wp package uninstall deliciousbrains/wp-cli-up

If you want to delete all your files as well:

    rm -rf ~/wp-cli-up
