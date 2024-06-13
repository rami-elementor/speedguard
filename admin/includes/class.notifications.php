<?php

/**
 *
 *   Class responsible for email notifications
 */
class SpeedGuard_Notifications {

	function __construct() {
	}

	public static function test_results_email( $type ) {
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Check if there are any tests running at the moment, and if so -- reschedule it to 10 minutes later
		if ( get_transient( 'speedguard-tests-running' ) ) {
			wp_schedule_single_event( time() + 10 * 60, 'speedguard_email_test_results' );
			return;
		}

		$guarded_pages = get_transient('speedguard_tests_count');
		if ( (int)$guarded_pages > 0) { //if there are monitored pages
			$speedguard_options = SpeedGuard_Admin::get_this_plugin_option( 'speedguard_options' );
			$admin_email        = $speedguard_options['email_me_at'];
			$site_url           = wp_parse_url( get_home_url() );
			$site_url           = $site_url['host'];

			$speedguard_cwv_origin = SpeedGuard_Admin::get_this_plugin_option( 'sg_origin_results' );
			$overall_category_desktop = $speedguard_cwv_origin['desktop']['cwv']['overall_category'];
			$overall_category_mobile = $speedguard_cwv_origin['mobile']['cwv']['overall_category'];
			$status_mobile = ($overall_category_mobile === 'FAST') ? 'PASSES' : 'FAILS';
			$status_desktop = ($overall_category_desktop === 'FAST') ? 'PASSES' : 'FAILS';
			$color_mobile = ($overall_category_mobile === 'FAST') ? 'score-green' : 'score-red';
			$color_desktop = ($overall_category_desktop === 'FAST') ? 'score-green' : 'score-red';
			$status_mobile_display = '<span class="' . $color_mobile . '"><strong>' . $status_mobile . '</strong></span>';
			$status_desktop_display = '<span class="' . $color_desktop . '"><strong>' . $status_desktop . '</strong></span>';

			/* translators: %s: site URL */
			$subject            = sprintf( __( 'Performance update for %s', 'speedguard' ), $site_url );

			$message = '';
			$message .= '<!DOCTYPE html>';
			$message .= '<html>';
			$message .= '<head>';
			$message .= '<title>';
			/* translators: Title of the email report */
			$message .= esc_html__( 'SpeedGuard Report', 'speedguard' );
			$message .= '</title>';
			$message .= '<style>';
			$message .= 'table {border-collapse: collapse;width: 560px; margin-top: 2em;}';
			$message .= 'th, td {text-align: left; padding: 8px;}';
			$message .= 'tr:nth-child(even) {background-color: #f2f2f2;}';
			$message .= '.score-green {color: #4CAF50;}';
			$message .= '.score-red {color: #f44336;}';
			$message .= '</style>';
			$message .= '</head>';
			$message .= '<body style="padding-top: 50px; padding-bottom: 50px; background:#fff; color:#000;">';
			$message .= '<table align="center">';
			$message .= '<tr>';
			$message .= '<td style="padding: 10px; bgcolor="#f7f7f7">';
			$message .= '<p style="text-align:center; font-size: 1.2em; font-weight: bold;">';
			/* translators: Core Web Vitals report */
			$message .= esc_html__( 'Core Web Vitals report', 'speedguard' );
			$message .= '</p>';
			$message .= '<p>';
			/* translators: %1$s - Website URL, %2$s - Mobile status, %3$s - Desktop status */
			$message .= sprintf( esc_html__( 'Currently the website %1$s %2$s Core Web Vitals assessment by Google for Mobile and %3$s for Desktop. This result is for Origin, meaning for the website in general.', 'speedguard' ), $site_url, $status_mobile_display, $status_desktop_display );
			$message .= '</p>';
			$message .= '<p>';
			/* translators: Individual URLs might be passing or not. */
			$message .= esc_html__( 'Individual URLs might be passing or not.', 'speedguard' );
			$message .= '</p>';
			$message .= '<p>';
			/* translators: Number of monitored pages */
			$message .= sprintf( esc_html__( '%s pages are monitored now.', 'speedguard' ), $guarded_pages );
			$message .= '</p>';
			$message .= '<p>';
			/* translators: %1$s - Link start, %2$s - Link end */
			$message .= sprintf( esc_html__( 'You can see the detailed report and add more individual URLs to be monitored %1$shere%2$s.', 'speedguard' ), '<a href="' . esc_url( SpeedGuard_Admin::speedguard_page_url( "tests" ) ) . '" target="_blank">', '</a>' );
			$message .= '</p>';
			$message .= '</td>';
			$message .= '</tr>';
			$message .= '<tr>';
			$message .= '<td width="100%" style="padding: 0;">';
			$message .= '<div style="padding: 1em; color:#000;">';
			$message .= '<p style="font-size: 1.2em; font-weight: bold;">';
			/* translators: Important questions section */
			$message .= esc_html__( 'Important questions:', 'speedguard' );
			$message .= '</p>';
			$message .= SpeedGuard_Widgets::get_important_questions_widget_function();  // TODO: Address the replacement issue
			$message .= '</div>';
			$message .= '</td>';
			$message .= '</tr>';
			$message .= '<tr>';
			$message .= '<td style="padding: 10px;color:#5f5a5a; text-align:right; font-size: 0.9em;" bgcolor="#e6e1e1" align="right">';
			/* translators: Report requested by site administrator */
			$message .= sprintf( esc_html__( 'This report was requested by administrator of %s.', 'speedguard' ), $site_url );
			$message .= ' ';
			/* translators: Change SpeedGuard notification settings link */
			$message .= sprintf( esc_html__( 'You can change SpeedGuard notification settings %1$shere%2$s any time.', 'speedguard' ), '<a href="' . esc_url( SpeedGuard_Admin::speedguard_page_url( 'settings' ) ) . '" target="_blank">', '</a>' );
			$message .= '</td>';
			$message .= '</tr>';
			$message .= '</table>';
			$message .= '</body>';
			$message .= '</html>';
		
			echo $message;
			wp_mail( $admin_email, $subject, $message, $headers );

		}
	}
}

new SpeedGuard_Notifications();
