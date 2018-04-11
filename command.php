<?php

// TODO: Add WooCommerce check, then exclude.
// TODO: Only do auto --patch updates and ask about --minor updates.
// TODO: View change logs.
// TODO: Install previous version if older than X days and new update just came output.
// TODO: Check for security/vulnerability updates and apply.

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Update plugins depending on release date.
 *
 * Will update plugins given some variables, such as date of Release
 *
 * ---
 * ## EXAMPLES
 *
 * wp plug-up --days=3
 *
 * @package up-date
 */
class Custom_Update extends WP_CLI_Command {

	/**
	 * Returns days since the date passed
	 *
	 * Undocumented function long description
	 *
	 * @param mixed $datetime Date.
	 * @return int $days Number of days since $datetime
	 */
	private function days_passed( $datetime ) {
		$current_date = new DateTime();
		$update_date  = new DateTime( $datetime );
		$between_date = $current_date->diff( $update_date );
		$days         = $between_date->format( '%a' );
		return $days;
	}

	/**
	 * WP_CLI::runcommand() default options
	 *
	 * @var array
	 */
	private $options = array(
		'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
		'parse'      => 'json', // Parse captured STDOUT to JSON array.
		'launch'     => true,  // Reuse the current process.
		'exit_error' => true,   // Halt script execution on error.
	);

	/**
	 * Get list of all updateable plugins
	 *
	 * @return mixed $plugins_updateable List of all the plugins that have available updates
	 */
	private function get_plugins_updateable() {
		// Get all updateable plugins.
		$plugins_updateable = WP_CLI::runcommand( 'plugin update --all --dry-run --format=json', $this->options );
		foreach ( $plugins_updateable as &$plugin ) {
			// Array of plugins from a slug search, with last_updated dates.
			$plugin_search = WP_CLI::runcommand( 'plugin search ' . $plugin['name'] . ' --fields=slug,last_updated --format=json', $this->options );
			// Go through search results and match updateable $plugin['name'] with search $result['slug'].
			foreach ( $plugin_search as $result ) {
				if ( $result['slug'] === $plugin['name'] ) {
					// Get plugin's full, readable name.
					$plugin_name            = WP_CLI::runcommand( 'plugin get ' . $plugin['name'] . ' --field=title --format=json', $this->options );
					$plugin['last_updated'] = $result['last_updated'];
					$plugin['update_age']   = $this->days_passed( $plugin['last_updated'] );
					$plugin['title']        = $plugin_name;
					break;
				}
			}
		}
		return $plugins_updateable;
	}

	/**
	 * Return plugins with update age less than --days= or 3 days old.
	 *
	 * @param mixed $plugins_updateable All updateable plugins.
	 * @param mixed $args Possible command line arguments passed.
	 * @param mixed $assoc_args Possible command line arguments passed.
	 * @return mixed $plugins_updateable Updateable plugins X days old.
	 */
	private function get_plugins_by_date( $plugins_updateable, $args, $assoc_args = array() ) {
		$days = WP_CLI\Utils\get_flag_value( $assoc_args, 'days', '3' );
		foreach ( $plugins_updateable as $plugin => $value ) {
			if ( $value['update_age'] <= $days ) {
				unset( $plugins_updateable[ $plugin ] );
			}
		}
		return $plugins_updateable;
	}

	/**
	 * Update sorted plugins.
	 *
	 * @param mixed $plugins_by_date Plugins to update within date range.
	 * @param mixed $args Possible command line arguments passed.
	 * @param mixed $assoc_args Possible command line arguments passed.
	 */
	private function update_plugins( $plugins_by_date, $args, $assoc_args ) {
		foreach ( $plugins_by_date as $plugin => $value ) {
			$install_me .= $value['name'] . ' ';
		}
		if ( ! $install_me ) {
			WP_CLI::warning( 'There are no plugins to update' );
			return;
		}
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		WP_CLI::line( WP_CLI::colorize( "%USelected plugin updates:%n" ) );
		WP_CLI\Utils\format_items(
			$format,
			$plugins_by_date,
			array(
				'title',
				'version',
				'update_version',
				'last_updated',
				'update_age',
			)
		);
		WP_CLI::confirm( WP_CLI::colorize( PHP_EOL . "%mInstall These Plugins?%n" ) );
		WP_CLI::runcommand( 'plugin update ' . $install_me );
	}

	/**
	 * Update selected plugins
	 *
	 * Pulls all info from other functions to update plugins
	 *
	 * @param mixed $args Possible command line arguments passed.
	 * @param mixed $assoc_args Possible command line arguments passed.
	 */
	public function __invoke( $args, $assoc_args = array() ) {
		$plugins_updateable = $this->get_plugins_updateable();
		$plugins_by_date    = $this->get_plugins_by_date( $plugins_updateable, $args, $assoc_args );
		$this->update_plugins( $plugins_by_date, $args, $assoc_args );
	}
}

WP_CLI::add_command( 'plug-up', 'Custom_Update' );
