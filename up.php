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

	public function remove( $args, $assoc_args ) {
		$domain = $args[0];

		WP_CLI::confirm( "Are you sure you want to delete the site '$domain', its database, and all its files?", $assoc_args );

		$wp_path = $_SERVER['HOME'] . "/wp-cli-up/sites/$domain/files/public";

		if ( ! is_dir( $wp_path ) ) {
			fwrite( STDOUT, "Can't find folder $wp_path" );
			exit;
		}

		$dbname = WP_CLI::runcommand( "config get DB_NAME --path=$wp_path", [ 'return' => true, 'exit_error' => false ] );
		$dbuser = WP_CLI::runcommand( "config get DB_USER --path=$wp_path", [ 'return' => true, 'exit_error' => false ] );

		if ( ! $dbname || ! $dbuser ) {
			fwrite( STDOUT, "Can't find a WordPress install in $wp_path" );
			exit;
		}

		$bash_script = $this->get_bash_script( 'remove', compact(
			'domain', 'dbname', 'dbuser'
		) );

		system( $bash_script );

		$current_hosts_entry = $this->get_current_hosts_entry( $domain ) . "\n";

 		WP_CLI::debug( 'Removing domain from hosts file...' );
 		$cmd = "perl -p -i -e 's/$current_hosts_entry//g' /etc/hosts";
    	WP_CLI::launch( $cmd );
	}

	public function add( $args, $assoc_args ) {

		$domain = $args[0];
		$username = $this->get_username_from_domain( $domain );
		$passwd = base_convert(uniqid('pass', true), 10, 20);

		WP_CLI::log( 'Setting up site for ' . $domain . '...' );

		$bash_script = $this->get_bash_script( 'add', compact(
			'domain', 'username', 'passwd'
		) );

		system( $bash_script );

		$ip_address = $this->get_multipass_info( 'IPv4' );
		$new_hosts_entry = $ip_address . ' ' . $domain;

		$current_hosts_entry = $this->get_current_hosts_entry( $domain );

    	if ( $current_hosts_entry ) {
	 		WP_CLI::debug( 'Updating domain in hosts file...' );
	 		$cmd = "perl -p -i -e 's/$current_hosts_entry/$new_hosts_entry/g' /etc/hosts";
	    	WP_CLI::launch( $cmd );
    	}
    	else {
	 		WP_CLI::debug( 'Adding domain to hosts file...' );
	 		$cmd = 'echo %s >> /etc/hosts';
	    	WP_CLI::launch( \WP_CLI\Utils\esc_cmd( $cmd, $new_hosts_entry ) );
    	}

    	WP_CLI::log( "Your site is now live at https://$domain" );
    	WP_CLI::log( "You can login at https://$domain/wp-login.php with the following credentials:\nUsername: $username\nPassword: $passwd" );
	}

	private function get_bash_script( $script, $args ) {
		extract( $args );
		ob_start();
		include "bash/$script.php";
		$output = ob_get_clean();
		//file_put_contents( "$script.sh", $output );
		return $output;
	}

	private function get_current_hosts_entry( $domain ) {
 		$cmd = "perl -wln -e 'print if /\b%s\b/' /etc/hosts";
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
