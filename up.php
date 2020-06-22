<?php
/**
 * Script Name: WP CLI Up
 * Description: Setup local dev environment using Ubuntu Multipass
 * License: GPLv3
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'up', 'WP_CLI_Up', array( 'when' => 'before_wp_load' ) );
}

/**
 * Control a Multipass-powered dev environment
 *
 * Usages:
 *
 * wp up init
 * wp up add
 * wp up remove
 * wp up ssh
 *
 */
class WP_CLI_Up {

	/**
	 * Removes a site from the wp-cli-up multipass instance
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name of the site to remove
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove site.
	 *     $ wp up remove acmepublishing.com
	 *     Success: Added object 'my_key' in group 'my_value'.
	 */
	public function remove( $args, $assoc_args ) {

		if ( ! is_writable( '/etc/hosts' ) ) {
			WP_CLI::warning( "Not running as root. Your /etc/hosts file won't be updated." );
			WP_CLI::confirm( "Are you sure you want to proceed?" );
		}

		$domain = $args[0];

		WP_CLI::confirm( "Are you sure you want to remove the site '$domain', its database, and all its files?", $assoc_args );

		$defaults = [
			'path' => $_SERVER['HOME'] . "/wp-cli-up/sites/$domain/files/public"
		];
		$args = array_merge( $defaults, $assoc_args );

		$wp_path = $args['path'];

		if ( ! is_dir( $wp_path ) ) {
			WP_CLI::error( "Can't find folder $wp_path" );
		}

		$dbname = WP_CLI::runcommand( "config get DB_NAME --path=$wp_path", [ 'return' => true, 'exit_error' => false ] );
		$dbuser = WP_CLI::runcommand( "config get DB_USER --path=$wp_path", [ 'return' => true, 'exit_error' => false ] );

		if ( ! $dbname || ! $dbuser ) {
			WP_CLI::error( "Can't find a WordPress install in $wp_path" );
		}

		$bash_script = $this->get_bash_script( 'remove', compact(
			'domain', 'dbname', 'dbuser'
		) );

		system( $bash_script );

		$this->remove_from_hosts_file( $domain );

		WP_CLI::success( "$domain has been removed." );
	}

	/**
	 * Adds a site to the wp-cli-up multipass instance
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name to configure for the site
	 *
	 * [--dbname=<dbname>]
	 * : Set the database name. Defaults to the first part of the domain name.
	 *
	 * [--dbuser=<dbuser>]
	 * : Set the database user. Defaults to the first part of the domain name.
	 *
	 * [--dbpass=<dbpass>]
	 * : Set the database user password. Defaults to randomly generated string.
	 *
	 * [--dbprefix=<dbprefix>]
	 * : Set the database table prefix.
	 * ---
	 * default: wp_
	 * ---
	 *
	 * [--title=<site-title>]
	 * : The title of the new site. Defaults to the domain name.
	 *
	 * [--admin_user=<username>]
	 * : The name of the admin user.
	 * ---
	 * default: admin
	 * ---
	 *
	 * [--admin_password=<password>]
	 * : The password for the admin user. Defaults to randomly generated string.
	 *
	 * [--admin_email=<email>]
	 * : The email address for the admin user.
	 * ---
	 * default: nobody@nobody.com
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Add site.
	 *     $ wp up add acmepublishing.com
	 *     Success: Your site is now live at https://acmepublishing.com
	 *     You can login at https://acmepublishing.com/wp-login.php with the following credentials:
	 *     Username: admin
	 *     Password: 6gfaae6653c2b9
	 */
	public function add( $args, $assoc_args ) {

		if ( ! is_writable( '/etc/hosts' ) ) {
			WP_CLI::warning( "Not running as root. Your /etc/hosts file won't be updated." );
			WP_CLI::confirm( "Are you sure you want to proceed?" );
		}

		$domain = $args[0];

		if ( empty( $domain ) ) {
			WP_CLI::error( "You must provide a domain name to add a site." );
		}

		$username = $this->get_username_from_domain( $domain );

		$defaults = [
			'dbname' => $username,
			'dbuser' => $username,
			'dbpass' => base_convert( uniqid( 'pass', true ), 10, 20 ),
			'dbprefix' => 'wp_',
			'title'  => $domain,
			'admin_user' => 'admin',
			'admin_password' => base_convert( uniqid( 'pass', true ), 10, 20 ),
			'admin_email' => 'nobody@nobody.com'
		];
		$args = array_merge( $defaults, $assoc_args );

		$args['domain'] = $domain;

		WP_CLI::debug( 'Setting up site for ' . $domain . '...' );

		$bash_script = $this->get_bash_script( 'add', $args );

		system( $bash_script );

		$this->add_update_hosts_file( $domain );

    	WP_CLI::success( "Your site is now live at https://$domain\nYou can login at https://$domain/wp-login.php with the following credentials:\nUsername: {$args['admin_user']}\nPassword: {$args['admin_password']}" );
	}

	/**
	 * Removes a domain from the macOS /etc/hosts file
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name to remove from the hosts file
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove domain from hosts file.
	 *     $ sudo wp up hosts_remove acmepublishing.com
	 *     Success: Hosts file updated.
	 */
	public function hosts_remove( $args, $assoc_args ) {
		$domain = $args[0];

		if ( is_null( WP_CLI::get_runner()->config['allow-root'] ) || ! is_writable( '/etc/hosts' ) ) {
			WP_CLI::error( "This command must be run as root with --allow-root." );
		}

		if ( empty( $domain ) ) {
			WP_CLI::error( "You must provide a domain name to add to your hosts file." );
		}

		$current_hosts_entry = $this->get_current_hosts_entry( $domain );

		if ( ! $current_hosts_entry ) {
			WP_CLI::error( "Can't find this domain in the macOS /etc/hosts file." );
		}

		$this->remove_from_hosts_file( $domain );

		$current_hosts_entry = $this->get_current_hosts_entry( $domain );

		if ( ! $current_hosts_entry ) {
			WP_CLI::success( "Hosts file updated.");
		}
		else {
			WP_CLI::error( "Could not remove domain from hosts file." );
		}
	}

	private function remove_from_hosts_file( $domain ) {
		$current_hosts_entry = $this->get_current_hosts_entry( $domain );

 		WP_CLI::debug( 'Removing domain from hosts file...' );
 		$cmd = "perl -p -i -e 's/^" . preg_quote( $current_hosts_entry ) . ".*\n//g' /etc/hosts";
    	WP_CLI::launch( $cmd );
	}

	/**
	 * Adds a domain to the macOS /etc/hosts file with the IP of the wp-cli-up multipass instance
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name to add to the hosts file
	 *
	 * ## EXAMPLES
	 *
	 *     # Add domain to hosts file.
	 *     $ sudo wp up hosts_add acmepublishing.com
	 *     Success: Hosts file updated.
	 */
	public function hosts_add( $args, $assoc_args ) {
		$domain = $args[0];

		if ( is_null( WP_CLI::get_runner()->config['allow-root'] ) || ! is_writable( '/etc/hosts' ) ) {
			WP_CLI::error( "This command must be run as root with --allow-root." );
		}

		if ( empty( $domain ) ) {
			WP_CLI::error( "You must provide a domain name to add to your hosts file." );
		}

		$this->add_update_hosts_file( $domain );

		$current_hosts_entry = $this->get_current_hosts_entry( $domain );

		if ( $current_hosts_entry ) {
			WP_CLI::success( "Hosts file updated.");
		}
		else {
			WP_CLI::error( "Could add/update domain in hosts file." );
		}
	}

	private function get_bash_script( $script, $args ) {
		extract( $args );
		ob_start();
		include "bash/$script.php";
		$output = ob_get_clean();
		//file_put_contents( "$script.sh", $output );
		return $output;
	}

	private function add_update_hosts_file( $domain ) {
		if ( ! is_writable( '/etc/hosts' ) ) {
			return false;
		}

		$ip_address = $this->get_multipass_info( 'IPv4' );
		$new_hosts_entry = $ip_address . ' ' . $domain;
		$current_hosts_entry = $this->get_current_hosts_entry( $domain );

    	if ( $current_hosts_entry ) {
	 		WP_CLI::debug( 'Updating domain in hosts file...' );
	 		$cmd = "perl -p -i -e 's/$current_hosts_entry/$new_hosts_entry/g' /etc/hosts";
	    	WP_CLI::launch( $cmd, false );
    	}
    	else {
	 		WP_CLI::debug( 'Adding domain to hosts file...' );
	 		$cmd = 'echo %s >> /etc/hosts';
	    	WP_CLI::launch( \WP_CLI\Utils\esc_cmd( $cmd, $new_hosts_entry ), false );
    	}

    	return true;
	}

	private function get_current_hosts_entry( $domain ) {
 		$cmd = "perl -wln -e 'print if /^(?:\d{1,3}\.){3}\d{1,3}\s+%s\b/' /etc/hosts";
    	$result = WP_CLI::launch( \WP_CLI\Utils\esc_cmd( $cmd, $domain ), false, true );
    	return trim( $result->stdout );
	}

	private function get_multipass_info( $key ) {
		$info = WP_CLI::launch( 'multipass info wp-cli-up', true, true );
		$info = explode( PHP_EOL, $info );
		$new_info = [];
		foreach ( $info as $i => $line ) {
			if ( false === strpos( $line, ':' ) ) continue;

			$line = preg_replace( '/:\s+/', ':', $line );
			$line = explode( ':', $line );
			$new_info[$line[0]] = $line[1];
		}
		$info = $new_info;

		return isset( $info[$key] ) ? $info[$key] : false;
	}

	private function get_username_from_domain( $domain ) {
		if ( false !== strpos( $domain, '.' ) ) {
			$parts = explode( '.', $domain );
			if ( strlen( $parts[1] ) >= strlen( $parts[0] ) ) {
				return $parts[1];
			}
			else {
				return $parts[0];
			}
		}
		else {
			$username = $domain;
		}

		return $username;
	}

	public function ssh( $args, $assoc_args ) {
		system( "multipass shell wp-cli-up" );
	}

}
