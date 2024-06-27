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

if ( function_exists( 'speedguard_fs' ) ) {
	speedguard_fs()->set_basename( true, __FILE__ );
} else {
	// DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
	if ( ! function_exists( 'speedguard_fs' ) ) {

		// ... Freemius integration snippet ...
		if ( ! function_exists( 'speedguard_fs' ) ) {
			// Create a helper function for easy SDK access.
			function speedguard_fs() {
				global $speedguard_fs;

				if ( ! isset( $speedguard_fs ) ) {
					// Include Freemius SDK.
					require_once dirname( __FILE__ ) . '/freemius/start.php';

					$speedguard_fs = fs_dynamic_init( array(
						'id'                  => '15835',
						'slug'                => 'speedguard',
						'premium_slug'        => 'speedguard-pro',
						'type'                => 'plugin',
						'public_key'          => 'pk_4f087343623f01d0a96151c22d6f9',
						'is_premium'          => false,
						'premium_suffix'      => 'PRO',
						// If your plugin is a serviceware, set this option to false.
						'has_premium_version' => true,
						'has_addons'          => false,
						'has_paid_plans'      => true,
						'trial'               => array(
							'days'               => 7,
							'is_require_payment' => false,
						),
						'menu'                => array(
							'slug'       => 'speedguard_tests',
							'first-path' => 'admin.php?page=speedguard_tests',
						),
					) );
				}

				return $speedguard_fs;
			}

			// Init Freemius.
			speedguard_fs();
			// Signal that SDK was initiated.
			do_action( 'speedguard_fs_loaded' );

			//Set EURO as default currency
			function speedguard_default_currency( $currency ) {
				return 'eur';
			}

			speedguard_fs()->add_filter( 'default_currency', 'speedguard_default_currency' );


			//Customise strings
			// For customers on pro plan
			if ( speedguard_fs()->is_plan( 'pro' ) ) {
				speedguard_fs()->override_i18n( array(
					'upgrade' => __( 'Track CWV on more websites.', 'speedguard' ),
				) );
			} // For customers in trial
			elseif ( speedguard_fs()->is_trial() ) {
				speedguard_fs()->override_i18n( array(
					'upgrade' => __( 'Keep email notifications going', 'speedguard' ),
				) );
			} // For customers on free plan
			else {
				speedguard_fs()->override_i18n( array(
					'upgrade' => __( 'Get email notifications', 'speedguard' ),
				) );
			}

			// Custom connect message for New free/paid users
			speedguard_fs()->add_filter( 'connect_message', 'speedguard_fs_custom_connect_message_on_update', 10, 6 );
			// Custom connect message for Existing free/paid users, who update from older version
			speedguard_fs()->add_filter( 'connect_message_on_update', 'speedguard_fs_custom_connect_message_on_update', 10, 6 );
			function speedguard_fs_custom_connect_message_on_update(
				$message, $user_first_name, $plugin_title, $user_login, $site_link, $freemius_link
			) {
				$sz_image_url = plugin_dir_url(__FILE__) . 'admin/assets/images/sabrina.jpg';
				$picture = '<a href="https://sabrinazeidan.com/?utm_source=speedguard&utm_medium=sidebar&utm_campaign=avatar" target="_blank"
><div id="szpic" style="background-image: url('.$sz_image_url.'); background-size: cover; height: 75px; width: 75px; border-radius: 50%; overflow: hidden; float: right; margin: 1em;"></div></a>';

				/* translators:
                1: User's first name
                2: Plugin title
                3: User's login
                4: Site link
                5: Freemius link
                */

				return $picture . sprintf( '<p>' . __( 'Hi there!' ) . '</p>' . '<p>' . __( 'My name is Sabrina.' ) . '</p>' . '<p>' . __( 'Please help me improve %2$s!' ) . '<br>' . __( 'I would like to make this plugin more compatible with the sites like yours, and make it more useful.' ) . '<br>' . __( 'If you opt-in, some basic WordPress environment info will be shared.' ) . '<br>' . __( 'No guarantee, but I might also send you email for security & feature updates, educational content, and occasional offers.' ) . '</p>' . '<p>' . __( 'If you skip this, that\'s okay! %2$s will still work just fine.', 'speedguard' ) . '</p>', $user_first_name, '<b>' . $plugin_title . '</b>', '<b>' . $user_login . '</b>', $site_link, $freemius_link );
			}


			//Hide Contact me submenu for free users
			function speedguard_is_submenu_visible( $is_visible, $menu_id ) {
				if ( $menu_id !== 'contact' && $menu_id !== 'account' ) {
					return $is_visible;
				}

				return speedguard_fs()->can_use_premium_code();
			}

			speedguard_fs()->add_filter( 'is_submenu_visible', 'speedguard_is_submenu_visible', 10, 2 );


		}
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
		//	speedguard_delete_data();
	}

	register_activation_hook( __FILE__, 'activate_speedguard' );
	register_deactivation_hook( __FILE__, 'deactivate_speedguard' );


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
	// Add uninstall hook
	speedguard_fs()->add_action( 'after_uninstall', 'speedguard_fs_uninstall_cleanup' );

}


function speedguard_fs_uninstall_cleanup() {
	// Uninstall logic here
	require_once plugin_dir_path( __FILE__ ) . '/admin/class-speedguard-admin.php';
	require_once plugin_dir_path( __FILE__ ) . '/admin/includes/class.tests-table.php';
	speedguard_delete_data();
}

function speedguard_delete_data() {
	// Delete CPTs
	$guarded_pages = get_posts( [
		'post_type'      => [ 'guarded-page' ],
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
		'speedguard_tests_count',
		'speedguard_no_cwv_data'
	];

	foreach ( $speedguard_transients as $speedguard_transient ) {
		delete_transient( $speedguard_transient );
	}

	// Delete CRON jobs
	wp_clear_scheduled_hook( 'speedguard_update_results' );
	wp_clear_scheduled_hook( 'speedguard_email_test_results' );
	
}