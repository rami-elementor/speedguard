<?php
/**
 *
 * @link              https://sabrinazeidan.com/
 * @since             1.0.0
 * @package           SpeedGuard
 * @wordpress-plugin
 * Plugin Name:       SpeedGuard
 * Plugin URI:        https://sabrinazeidan.com/speedguard/
 * Description:       Tracks Core Web Vitals for you and sends an email if there is a problem; every single day for free.
 * Version:           2.0
 * Author:            Sabrina Zeidan
 * Author URI:        https://sabrinazeidan.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       speedguard
 */

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-speedguard-activator.php
 */
const SPEEDGUARD_VERSION = '2.0';

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// SpeedGuard main file logic ...

function activate_speedguard( $network_wide ) {
	// Network-wide  activation is a PRO feature. If tries to activate Network wide, stop:
	if ( is_multisite() && $network_wide && ( ! defined( 'SPEEDGUARD_PRO' ) ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'Network activation is not available at the moment. But feel free to activate this plugin on per-site basis!', 'speedguard' ) );
	}

	// Activate in all other cases
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-speedguard-activator.php';
	Speedguard_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-speedguard-deactivator.php
 */
function deactivate_speedguard() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-speedguard-deactivator.php';
	Speedguard_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_speedguard' );
register_deactivation_hook( __FILE__, 'deactivate_speedguard' );


register_uninstall_hook( __FILE__, 'uninstall_speedguard' );

function uninstall_speedguard() {
	// Uninstall logic here
	require_once plugin_dir_path( __FILE__ ) . '/admin/class-speedguard-admin.php';
	require_once plugin_dir_path( __FILE__ ) . '/admin/includes/class.tests-table.php';

// Delete all data
	function speedguard_delete_data() {

		// Delete CPTs
		$guarded_pages = get_posts( [
			'post_type'      => [ 'guarded-page', SpeedGuard_Admin::$cpt_name ], // Backwards compatibility
			'post_status'    => 'any',
			'posts_per_page' => - 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		foreach ( $guarded_pages as $guarded_page_id ) {
			SpeedGuard_Tests::delete_test_fn( $guarded_page_id );
		}

		// Delete posts meta
		$guarded_posts = get_posts( [
			'post_type'     => 'any',
			'post_status'   => 'any',
			'fields'        => 'ids',
			'meta_query'    => [
				'relation' => 'AND',
				[
					'key'     => 'speedguard_on',
					'compare' => 'EXISTS',
				],
			],
			'no_found_rows' => true,
		] );

		foreach ( $guarded_posts as $guarded_post_id ) {
			delete_post_meta( $guarded_post_id, 'speedguard_on' );
		}

		// Delete terms meta
		$the_terms = get_terms( [
			'fields'     => 'ids',
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'     => 'speedguard_on',
					'compare' => 'EXISTS',
				],
			],
		] );

		foreach ( $the_terms as $term_id ) {
			delete_term_meta( $term_id, 'speedguard_on' );
		}

		// Delete options
		$speedguard_options = [
			'speedguard_options',
			'sg_origin_results'
		];
		foreach ( $speedguard_options as $option_name ) {
			delete_option( $option_name );
			if ( is_multisite() ) {
				delete_site_option( $option_name );
			}
		}

		// Delete non-expiring transients (auto-expiring will be deleted automatically)
		$speedguard_transients = [
			'speedguard_tests_in_queue',
			'speedguard_test_in_progress',
			'speedguard_sending_request_now',
			'speedguard_tests_count'
		];
		foreach ( $speedguard_transients as $speedguard_transient ) {
			delete_transient( $speedguard_transient );
		}

		// Delete CRON jobs
		wp_clear_scheduled_hook( 'speedguard_update_results' );
		wp_clear_scheduled_hook( 'speedguard_email_test_results' );
	}

// Search all blogs if Multisite
	if ( is_multisite() ) {
		$sites = get_sites();
		foreach ( $sites as $site ) {
			$blog_id = $site->blog_id;
			switch_to_blog( $blog_id );
			speedguard_delete_data();
			restore_current_blog();
		}
	} else {
		speedguard_delete_data();
	}

}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-speedguard.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */

function run_speedguard() {

	$plugin = new Speedguard();
	$plugin->run();
}

run_speedguard();
// End of SpeedGuard main file logic ...









