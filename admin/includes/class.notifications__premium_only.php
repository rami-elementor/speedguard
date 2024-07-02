<?php

/**
 *
 *   Class responsible for email notifications
 */
class SpeedGuard_Notifications {

	function __construct() {
		// Consider initializing any properties or dependencies if needed
	}

	public static function email_tests_results() {
		// There are guarded pages and tests have finished, now send the email
		$speedguard_options = SpeedGuard_Admin::get_this_plugin_option('speedguard_options');
		$email_me_case = $speedguard_options['email_me_case'];
		$sg_test_type = $speedguard_options['test_type'];
		$speedguard_cwv_origin = SpeedGuard_Admin::get_this_plugin_option('sg_origin_results');
		if ($speedguard_cwv_origin === 'waiting') return; // if the data is not ready, don't send the email (this
		// might happen if tests were started just now)
		//AND rescedule the email
		// This is needed for all cases:
//		var_dump($speedguard_cwv_origin);

		$category_desktop = $speedguard_cwv_origin['desktop'][$sg_test_type]['category'];
		$category_mobile = $speedguard_cwv_origin['mobile'][$sg_test_type]['category'];
		$status_mobile = ($category_mobile === 'FAST') ? 'PASSES' : 'FAILS';
		$status_desktop = ($category_desktop === 'FAST') ? 'PASSES' : 'FAILS';
		$color_mobile = ($category_mobile === 'FAST') ? 'score-green' : 'score-red';
		$color_desktop = ($category_desktop === 'FAST') ? 'score-green' : 'score-red';
		$status_mobile_display = '<span class="' . $color_mobile . '"><strong>' . $status_mobile . '</strong></span>';
		$status_desktop_display = '<span class="' . $color_desktop . '"><strong>' . $status_desktop . '</strong></span>';

		// Assign color classes based on category values
		$mobile_color = ($category_mobile === 'FAST') ? 'score-green' : (($category_mobile === 'AVERAGE') ? 'score-yellow' : 'score-red');
		$desktop_color = ($category_desktop === 'FAST') ? 'score-green' : (($category_desktop === 'AVERAGE') ? 'score-yellow' : 'score-red');

		// if any url fails for this test type
		if ($email_me_case === 'any_URL_fails') { //use proper test type
			// get all guarded pages TODO: later replace with transient
			$guarded_pages = get_posts([
				'post_type' => SpeedGuard_Admin::$cpt_name,
				'post_status' => 'publish',
				'posts_per_page' => 100, //TODO: improve this limit with ajax chunks
				'fields' => 'ids',
				'no_found_rows' => true,
			]);
			// If there are any guarded pages:
			if ($guarded_pages) {
				foreach ($guarded_pages as $guarded_page_id) {
					$sg_test_result = get_post_meta($guarded_page_id, 'sg_test_result', true);
					foreach (SpeedGuard_Admin::SG_METRICS_ARRAY as $device => $test_types) {
						foreach ($test_types as $test_type => $metrics) {
							if ($test_type === $sg_test_type) { // Check only Test Type that is set in the settings
								foreach ($metrics as $metric) {
									$core_value = SpeedGuard_Widgets::single_metric_display($sg_test_result, $device, $test_type, $metric);
									// If the metric value doesn't include score-green string, means that the URL is not passing on this metric
									if (strpos($core_value, 'score-green') === false) {
										echo 'Send email "at least 1 url is not passing"';
										// send email + 1 url fails
										$decision = true;
										$trigger = 'any_URL_fails';
										break 2; // after 1st fail, no need to check other URLs, break out of both foreach loops
									}
								}
							}
						}
					}
				}
			}
		}

		// if Origin fails for this test type or Every time (same data needed, just trigger is different)
		elseif (($email_me_case === 'origin_fails') && ($category_mobile !== 'FAST' || $category_desktop !== 'FAST')) {
			echo 'Send email "origin fails"';
			$decision = true;
			$trigger = 'origin_fails';
		} elseif ($email_me_case === 'every_time') {
			echo 'Send email "every time"';
			$decision = true;
			$trigger = 'every_time';
		}

		if (!isset($decision) || $decision !== true) return; // finish execution if no email should be sent

		// Otherwise -- Continue and Send the email!

		$guarded_pages = get_transient('speedguard_tests_count');
		$admin_email = $speedguard_options['email_me_at'];
		$site_url = wp_parse_url(get_home_url());
		$site_url = $site_url['host'];

		/* translators: %s: site URL */
		$headers = ['Content-Type: text/html; charset=UTF-8'];
		$subject = sprintf(__('Performance update for %s [SpeedGuard]', 'speedguard'), $site_url);

		$message = '';
		$message .= '<!DOCTYPE html>';
		$message .= '<html>';
		$message .= '<head>';
		//$message .= '<title>';
		/* translators: Title of the email report */
		//$message .= esc_html__('SpeedGuard Report', 'speedguard');
		//$message .= '</title>';
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
		$message .= '<p style="font-weight: bold;">';
		/* translators: Core Web Vitals report */
		$message .= esc_html__('SpeedGuard report', 'speedguard');
		/* translators: %1$s - Website URL, %2$s - Mobile status, %3$s - Desktop status */
		if ($sg_test_type === 'cwv') {
			$message .= sprintf(esc_html__('Currently the website %1$s %2$s Core Web Vitals assessment by Google for Mobile and %3$s for Desktop.', 'speedguard'), $site_url, $status_mobile_display, $status_desktop_display);
			$message .= '<p>';
			$message .= esc_html__('This result is for Origin, meaning for the website in general.', 'speedguard');
			$message .= '</p>';
		} elseif ($sg_test_type === 'psi') {
			$message .= sprintf(esc_html__('Currently the website %1$s average PageSpeed Insights %2$s for Mobile and %3$s for Desktop.', 'speedguard'), $site_url, $status_mobile_display, $status_desktop_display);
			$message .= '<p>';
			$message .= esc_html__('This result is calculated average based on the URLs that you have added to be monitored.', 'speedguard');
			$message .= '</p>';
			$message .= '<p>';
			$message .= esc_html__('Please, remember that PSI is not a real users data, it is lab test only. If your website has CWV data available you should use that.', 'speedguard');
			$message .= '</p>';
		}
		$message .= '</p>';
		$message .= '<p>';
		/* translators: Individual URLs might be passing or not. */
		if ($trigger === 'any_URL_fails') {
			$message .= esc_html__('At least one of the individual URLs that you have added to be monitored is not passing the test.', 'speedguard');
		} else {
			$message .= esc_html__('Individual URLs might be passing or not.', 'speedguard');
		}
		$message .= '</p>';
		$message .= '<p>';
		/* translators: Number of monitored pages */
		$message .= sprintf(esc_html__('%s pages are monitored now.', 'speedguard'), $guarded_pages);
		$message .= '</p>';
		$message .= '<p>';
		/* translators: %1$s - Link start, %2$s - Link end */
		$message .= sprintf(esc_html__('You can see the detailed report and add more individual URLs to be monitored %1$shere%2$s.', 'speedguard'), '<a href="' . esc_url(SpeedGuard_Admin::speedguard_page_url("tests")) . '" target="_blank">', '</a>');
		$message .= '</p>';
		$message .= '</td>';
		$message .= '</tr>';
		$message .= '<tr>';
		$message .= '<td width="100%" style="padding: 0;">';
		$message .= '<div style="padding: 1em; color:#000;">';
		$message .= '<p style="font-size: 1.2em; font-weight: bold;">';
		/* translators: Important questions section */
		$message .= esc_html__('Understand performance:', 'speedguard');
		$message .= '</p>';
		$message .= SpeedGuard_Widgets::get_important_questions_widget_function(); // TODO: Address the replacement issue
		$message .= '</div>';
		$message .= '</td>';
		$message .= '</tr>';
		$message .= '<tr>';
		$message .= '<td style="padding: 10px;color:#5f5a5a; text-align:right; font-size: 0.9em;" bgcolor="#e6e1e1" align="right">';
		/* translators: Report requested by site administrator */
		$message .= sprintf(esc_html__('This report was requested by administrator of %s.', 'speedguard'), $site_url);
		$message .= ' ';
		/* translators: Change SpeedGuard notification settings link */
		$message .= sprintf(esc_html__('You can change SpeedGuard notification settings %1$shere%2$s any time.', 'speedguard'), '<a href="' . esc_url(SpeedGuard_Admin::speedguard_page_url('settings')) . '" target="_blank">', '</a>');
		$message .= '</td>';
		$message .= '</tr>';
		$message .= '</table>';
		$message .= '</body>';
		$message .= '</html>';

		echo esc_html($message);
		wp_mail($admin_email, $subject, $message, $headers);
	}
}

new SpeedGuard_Notifications();