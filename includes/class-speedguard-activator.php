<?php

/**
 * Fired during plugin activation.
 *
 * @see       http://sabrinazeidan.com/
 * @since      1.0.0
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 *
 * @author     Sabrina Zeidan <sabrinazeidan@gmail.com>
 */
class Speedguard_Activator {

	public static function activate() {
		set_transient( 'speedguard-notice-activation', true, 5 );
		$add_homepage = SpeedGuard_Tests::try_add_speedguard_test( get_site_url() );


		//check that speedguard_tests_count transient is updated properly
		$guarded_pages = get_posts( [
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'post_type'      => SpeedGuard_Admin::$cpt_name,
			'post_status'    => 'publish',
			'fields'         => 'ids'
		] );
		set_transient( 'speedguard_tests_count', count( $guarded_pages ) );



	}
}
