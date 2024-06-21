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
		// Create a helper function for easy SDK access.
		function speedguard_fs() {
			global $speedguard_fs;

			if ( ! isset( $speedguard_fs ) ) {
				// Include Freemius SDK.
				require_once dirname(__FILE__) . '/freemius/start.php';

				$speedguard_fs = fs_dynamic_init( array(
					'id'                  => '15835',
					'slug'                => 'speedguard',
					'premium_slug'        => 'speedguard-pro',
					'type'                => 'plugin',
					'public_key'          => 'pk_4f087343623f01d0a96151c22d6f9',
					'is_premium'          => true,
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
						'slug'           => 'speedguard_tests',
					),
				) );
			}

			return $speedguard_fs;
		}

		// Init Freemius.
		speedguard_fs();
		// Signal that SDK was initiated.
		do_action( 'speedguard_fs_loaded' );

	}

	// ... Your plugin's main file logic ...




	/**
	 * The code that runs during plugin activation.
	 * This action is documented in includes/class-speedguard-activator.php
	 */
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
}









