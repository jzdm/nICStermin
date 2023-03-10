<?php
/**
 * Plugin Name: nICStermin
 * Plugin URI:  https://nicstermin.de
 * Description: ICS calendar subscription and display on WordPress websites
 * Version:     0.1.2
 * Author:      jzdm
 * Author URI:  https://jzdm.de
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0
 * Text Domain: nicstermin
 * Domain Path: /languages
 */

define( 'NICSTERMIN_PLUGIN_VERSION', '0.1.2');
define( 'NICSTERMIN_PLUGIN_URL',  plugin_dir_url(  __FILE__ ) );
define( 'NICSTERMIN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

define( 'NICSTERMIN_CALENDAR_CACHE', NICSTERMIN_PLUGIN_PATH .'cache/calendars' );
define( 'NICSTERMIN_TEMPLATES_DIR',  NICSTERMIN_PLUGIN_PATH .'public/templates' );
define( 'NICSTERMIN_CSS_DIR',        NICSTERMIN_PLUGIN_PATH .'public/css' );
define( 'NICSTERMIN_CSS_URL',        NICSTERMIN_PLUGIN_URL  .'public/css' );


require_once __DIR__.'/vendor/autoload.php'; // composer autoloader
require_once __DIR__.'/includes/calendars.php';
require_once __DIR__.'/includes/settings.php';
require_once __DIR__.'/includes/cron.php';



register_activation_hook(   __FILE__, 'nicstermin_activate'   );
register_deactivation_hook( __FILE__, 'nicstermin_deactivate' );
register_uninstall_hook(    __FILE__, 'nicstermin_uninstall'  );

function nicstermin_activate()
{
	add_option('nicstermin_opt_timezone',          'Europe/Berlin' );
	add_option('nicstermin_opt_caldata',           [] );
	add_option('nicstermin_opt_updatefrequency',   'hourly' );
	add_option('nicstermin_opt_filters',           [] );
	add_option('nicstermin_opt_calupdate_reports', [] );
	add_option('nicstermin_opt_pluginversion',     NICSTERMIN_PLUGIN_VERSION );
}

function nicstermin_deactivate()
{
	\nICStermin\Cron::deactivated();
}

function nicstermin_uninstall()
{
	delete_option('nicstermin_opt_timezone');
	delete_option('nicstermin_opt_caldata');
	delete_option('nicstermin_opt_updatefrequency');
	delete_option('nicstermin_opt_filters');
	delete_option('nicstermin_opt_calupdate_reports');
	delete_option('nicstermin_opt_pluginversion');
}
/**
 * Load translations
 */
add_action( 'init', 'nicstermin_load_textdomain' );

function nicstermin_load_textdomain() {
	load_plugin_textdomain( 'nicstermin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}

/**
 * Link to settings on WordPress Plugins page
 */

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'nicstermin_add_action_links' );
function nicstermin_add_action_links( $actions ) {
	// Build and escape the URL.
	$url = esc_url( add_query_arg(
		'page',
		'nicstermin-page-admin-settings',
		get_admin_url() . 'admin.php'
	));
	// Create the link.
	$settings_link = "<a href='{$url}'>" . __( 'Settings' ) . '</a>';
	// Adds the link to the end of the array.
	array_push(
		$actions,
		$settings_link
	);
	return $actions;
}

/**
 * Initialize modules
 */

\nICStermin\Settings::init();
\nICStermin\Calendars::init();
\nICStermin\Cron::init();
