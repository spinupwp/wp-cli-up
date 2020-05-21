# wp-cli-up

A WP-CLI package to set up a [Multipass](https://multipass.run/) VM and manage WordPress sites on the VM.

## WIP

This is a work in progress. Not meant for public use just yet.

## Requirements

* macOS only
* [Multipass](https://multipass.run/)
* [WP-CLI](https://wp-cli.org/)

## Installation

    curl -o- https://raw.githubusercontent.com/bradt/wp-cli-up/master/install.sh | bash

MySQL root user password is 'root'.

## Add a Site

    sudo wp up add --allow-root <domain>

_The command needs to be run as root so your /etc/hosts file can be updated._

## Remove a Site

    sudo wp up remove --allow-root <domain>

_The command needs to be run as root so your /etc/hosts file can be updated._

## SSH to the VM

    multipass shell wp-cli-up

## Uninstall

    multipass delete wp-cli-up
    multipass purge
    wp package uninstall deliciousbrains/wp-cli-up

If you want to delete all your files as well:

    rm -Rf ~/wp-cli-up
