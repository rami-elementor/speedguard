<?php
/**
 *
 *   Class responsible for adding metaboxes
 */


class SpeedGuard_Widgets {
	public function __construct() {
		$options = SpeedGuard_Admin::get_this_plugin_option( 'speedguard_options' );
		if ( ! empty( $options ) ) {
			if ( $options['show_dashboard_widget'] === 'on' ) {
				add_action( 'wp_' . ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? 'network_' : '' ) . 'dashboard_setup', [
					$this,
					'speedguard_dashboard_widget_function',
				] );
			}
		}
	}

	/**
	 * Define all metaboxes for plugin's admin pages (Tests and Settings)
	 */
	public static function add_meta_boxes() {
		$sg_test_type = SpeedGuard_Settings::global_test_type();
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );

		add_meta_box( 'settings-meta-box', __( 'SpeedGuard Settings', 'speedguard' ), [
			'SpeedGuard_Settings',
			'settings_meta_box',
		], '', 'normal', 'core' );

		if ( 'cwv' === $sg_test_type ) {
			$origin_widget_title = 'Core Web Vitals for Origin -- real users\' experience for the entire website';
		} elseif ( 'psi' === $sg_test_type ) {
			$origin_widget_title = 'PageSpeed Insights (lab tests)';
		}

		add_meta_box( 'speedguard-dashboard-widget', esc_html__( $origin_widget_title, 'speedguard' ), [
			'SpeedGuard_Widgets',
			'origin_results_widget_function',
		], '', 'main-content', 'core' );

		add_meta_box( 'speedguard-add-new-url-meta-box', esc_html__( 'Add new URL to monitoring', 'speedguard' ), [
			'SpeedGuard_Widgets',
			'add_new_widget_function',
		], '', 'main-content', 'core' );

		if ( 'cwv' === $sg_test_type ) {
			$test_type = 'Core Web Vitals';
		} elseif ( 'psi' === $sg_test_type ) {
			$test_type = 'PageSpeed Insights';
		}

		add_meta_box( 'tests-list-meta-box', sprintf( esc_html__( '%s per page -- Test results for specific URLs', 'speedguard' ), $test_type ), [
			'SpeedGuard_Tests',
			'tests_results_widget_function',
		], '', 'main-content', 'core' );

		add_meta_box( 'speedguard-legend-meta-box', esc_html__( 'How to understand the information above?', 'speedguard' ), [
			'SpeedGuard_Widgets',
			'explanation_widget_function',
		], '', 'main-content', 'core' );
		add_meta_box( 'speedguard-howto-meta-box', esc_html__( 'How to get the most use from this plugin?', 'speedguard' ), [
			'SpeedGuard_Widgets',
			'howto_widget_function',
		], '', 'side', 'core' );

		add_meta_box( 'speedguard-important-questions-meta-box', esc_html__( 'Important questions:', 'speedguard' ), [
			'SpeedGuard_Widgets',
			'important_questions_widget_function',
		], '', 'side', 'core' );

		add_meta_box( 'speedguard-about-meta-box', esc_html__( 'Do you like this plugin?', 'speedguard' ), [
			'SpeedGuard_Widgets',
			'about_widget_function',
		], '', 'side', 'core' );
	}

	/**
	 * Function responsible for displaying the Origin widget, both n Tests page and Dashboard
	 */
	public static function origin_results_widget_function( $post = '', $args = '' ) {
		// Retrieving data to display
		$speedguard_cwv_origin = SpeedGuard_Admin::get_this_plugin_option( 'sg_origin_results' );
        //if PSI lcp value is not available it might mean it's localhost or staging, set transient to show notice
        //if $overall_category_desktop = $speedguard_cwv_origin['desktop']['psi']['overall_category'];

        if ( isset($speedguard_cwv_origin['desktop']['psi']['lcp']['average']) && str_contains( $speedguard_cwv_origin['desktop']['psi']['lcp']['average'], "N") ) {
	        set_transient( 'speedguard_not_production_environment', true, 10 );
        }
        // Preparing data to display
		$sg_test_type = SpeedGuard_Settings::global_test_type();
		foreach ( SpeedGuard_Admin::SG_METRICS_ARRAY as $device => $test_types ) {
			foreach ( $test_types as $test_type => $metrics ) {
				if ( $test_type === $sg_test_type ) { //prepare metrics only for needed test type
					foreach ( $metrics as $metric ) {
						$current_metric  = $device . '_' . $metric;
						$$current_metric = SpeedGuard_Widgets::single_metric_display( $speedguard_cwv_origin, $device, $test_type, $metric );
						// Check if the dynamic variable is defined (For the cases when new metrics are added)
							$$current_metric = isset($$current_metric) ? $$current_metric : '';

                    }
				}
			}
		}
		// Ensure $mobile_inp and $desktop_inp are defined if they are not already
		if (!isset($mobile_inp)) {
			$mobile_inp = 'N/A';
		}
		if (!isset($desktop_inp)) {
			$desktop_inp = 'N/A';
		}

		// Generate the table row for INP if the test type is 'cwv'
		if ( 'cwv' === $sg_test_type ) {
			$inp_tr = '<tr><th>' . esc_html__( 'Interaction to Next Paint (INP)', 'speedguard' ) . '</th>
    <td>' . wp_kses_post( $mobile_inp ) . '</td>
    <td>' . wp_kses_post( $desktop_inp ) . '</td></tr>';
		} else {
			$inp_tr = '';
		}

		if ( 'cwv' === $sg_test_type && isset( $speedguard_cwv_origin['desktop']['cwv']['overall_category'] ) && isset( $speedguard_cwv_origin['mobile']['cwv']['overall_category'] ) ) {

			$overall_category_desktop = $speedguard_cwv_origin['desktop']['cwv']['overall_category'];
			$overall_category_mobile  = $speedguard_cwv_origin['mobile']['cwv']['overall_category'];
			//overall_category can be FAST, AVERAGE, SLOW. Assign color (red, yellow, green) accordingly
			$mobile_color  = ( $overall_category_mobile === 'FAST' ) ? 'score-green' : ( ( $overall_category_mobile === 'AVERAGE' ) ? 'score-yellow' : 'score-red' );
			$desktop_color = ( $overall_category_desktop === 'FAST' ) ? 'score-green' : ( ( $overall_category_desktop === 'AVERAGE' ) ? 'score-yellow' : 'score-red' );
		}

		$mobile_color  = isset( $mobile_color ) ? esc_attr( $mobile_color ) : '';
		$desktop_color = isset( $desktop_color ) ? esc_attr( $desktop_color ) : '';


		$content = "
<table class='widefat fixed striped toplevel_page_speedguard_tests_cwv_widget'>
<thead>
<tr class='bc-platforms'><td></td>
<th><i class='sg-device-column mobile speedguard-score " . esc_attr( $mobile_color ) . "' aria-hidden='true' title='Mobile'></i></th>
<th><i class='sg-device-column desktop speedguard-score " . esc_attr( $desktop_color ) . "' aria-hidden='true' title='Desktop'></i></th>
</tr>
</thead>
<tbody>
<tr>
<th>" . esc_html__( 'Largest Contentful Paint (LCP)', 'speedguard' ) . "</th>
<td>" . wp_kses_post( $mobile_lcp ) . "</td>
<td>" . wp_kses_post( $desktop_lcp ) . "</td>
</tr>                                                                   
<tr><th>" . esc_html__( 'Cumulative Layout Shift (CLS)', 'speedguard' ) . "</th>

<td>" . wp_kses_post( $mobile_cls ) . "</td>
<td>" . wp_kses_post( $desktop_cls ) . "</td>
</tr>
   " . wp_kses_post( $inp_tr ) . "
</tbody>
</table>
";

        // if CWV is not available but it's production website and PSI was calculated
		if ( 'cwv' === $sg_test_type && str_contains( $mobile_lcp, 'N' ) && !get_transient( 'speedguard_not_production_environment' ) ) {
			set_transient( 'speedguard_no_cwv_data', true, 10 );
            $info_text = '';
        } elseif ( 'psi' === $sg_test_type ) {
			$info_text = sprintf(
				             esc_html__( 'Mind, that Pagespeed Insights IS NOT real user data. These are just emulated laboratory tests. Core Web Vitals -- is where the real data is. If your website has enough traffic and already had Core Web Vitals assessment -- you should always work with that.
			You can switch in %sSettings%s.', 'speedguard' ),
				             '<a href="' . esc_url( admin_url( 'admin.php?page=speedguard_settings' ) ) . '">',
				             '</a>'
			             ) . '<div><br></div>';

		} else {
			$info_text = '';
		}

		echo wp_kses_post( $content . $info_text );

	}

	/**
	 * Function responsible for formatting CWV data for display
	 */
	public static function single_metric_display( $results_array, $device, $test_type, $metric ) {

		$display_value = '';
		$category      = '';
		$class         = '';

		if ( $results_array === 'waiting' ) {
			// Tests are currently running, PSI Origin results will be calculated after all tests are finished
			$class = 'waiting';
		} elseif ( is_array( $results_array ) ) {
			// Tests are not currently running
			// Check if metric data is available for this device and test type
			if ( isset( $results_array[ $device ][ $test_type ][ $metric ] ) && is_array( $results_array[ $device ][ $test_type ][ $metric ] ) ) {

				if ( $test_type === 'psi' ) {
					$display_value = isset( $results_array[ $device ][ $test_type ][ $metric ]['displayValue'] ) ? $results_array[ $device ][ $test_type ][ $metric ]['displayValue'] : 'N/A';
					$class         = 'score';
					$category      = isset( $results_array[ $device ][ $test_type ][ $metric ]['score'] ) ? $results_array[ $device ][ $test_type ][ $metric ]['score'] : '';
				} elseif ( $test_type === 'cwv' ) {
					$metrics_value = isset( $results_array[ $device ][ $test_type ][ $metric ]['percentile'] ) ? $results_array[ $device ][ $test_type ][ $metric ]['percentile'] : null;

					// Format metrics output for display
					if ( $metrics_value !== null ) {
						if ( $metric === 'lcp' ) {
							$display_value = round( $metrics_value / 1000, 2 ) . ' s';
						} elseif ( $metric === 'cls' ) {
							$display_value = $metrics_value / 100;
						} elseif ( $metric === 'inp' ) {
							$display_value = $metrics_value . ' ms';
						}
					} else {
						$display_value = 'N/A';
					}

					$class    = 'score';
					$category = isset( $results_array[ $device ][ $test_type ][ $metric ]['category'] ) ? $results_array[ $device ][ $test_type ][ $metric ]['category'] : '';
				}
			} elseif ( $test_type === 'psi' && get_transient( 'speedguard-tests-running' ) ) {
				$class = 'waiting';
			} else {
				// No data available for the metric
				$class         = 'na';
				$display_value = 'N/A';
			}
		} else {
			// results_array is not an array or 'waiting', which is unexpected
			$class         = 'na';
			$display_value = 'N/A';
		}

		$category             = !empty($category) ? 'data-score-category="' . esc_attr($category) . '"' : '';
		$class                = 'class="speedguard-' . esc_attr($class) . '"';
		$metric_display_value = '<span ' . $category . ' ' . $class . '>' . esc_html($display_value) . '</span>';

		return $metric_display_value;
	}



	public static function explanation_widget_function() {
		$cwv_link = 'https://web.dev/lcp/';
		?>
        <ul>
            <li>
                <h3><?php esc_html_e( 'What does N/A mean?', 'speedguard' ); ?></h3>
                <span>
                <?php
                echo wp_kses_post( sprintf( /* translators: 1: Google Search Console URL, 2: CrUX report URL */ __( 'If you see "N/A" for a metric in Core Web Vitals tests, it means that there is not enough real-user data to provide a score. This can happen if your website is new or has very low traffic. You will see the same in your %1$s, as they pull data from the same source -- (%2$s).', 'speedguard' ), '<a href="' . esc_url( 'https://search.google.com/search-console/' ) . '">' . esc_html__( 'Google Search Console (GSC)', 'speedguard' ) . '</a>', '<a href="' . esc_url( 'https://developer.chrome.com/docs/crux/' ) . '">' . esc_html__( 'CrUX report', 'speedguard' ) . '</a>' ) );
                ?>
            </span>
            </li>
            <li>
                <h3><?php esc_html_e( 'What is the difference between Core Web Vitals and PageSpeed Insights?', 'speedguard' ); ?></h3>
                <span>
                <?php
                echo wp_kses_post( __( 'The main difference between CWV and PSI is that CWV is based on real-user data, while PSI uses lab data collected in a controlled environment. Lab data can be useful for debugging performance issues, but it is not as representative of the real-world user experience as real-user data.', 'speedguard' ) );
                ?>
                <p><strong><?php echo esc_html__( 'If you have CWV data available, you should always refer to that data first, as it represents the real experience real users of your website are having.', 'speedguard' ); ?></strong></p>
                <?php
                echo wp_kses_post( __( 'If there is no CWV data available -- you CAN use PSI as a reference, but you need to remember these are LAB tests: on the devices, connection and location that are most certainly don\'t match the actual state of things.', 'speedguard' ) );
                ?>
            </span>
            </li>
            <li id="undersanding-metrics">
	            <?php
	            echo wp_kses_post( __( 'All three of these metrics are important for providing a good user experience. A fast LCP means that users will not have to wait long for the main content of a page to load. A low CLS means that users will not have to deal with content that moves around while they are trying to read it. And a low INP means that users will be able to interact with a web page quickly and easily.', 'speedguard' ) );
	            ?>
                </p>
                <h3><?php esc_html_e( 'Understanding metrics:', 'speedguard' ); ?></h3>
                <span>
                <p>
                       <img src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . 'assets/images/lcp.svg' ); ?>"
                            alt="<?php echo esc_attr( 'Largest Contentful Paint chart' ); ?>">
                 <?php
                 echo wp_kses_post( sprintf( __( '%1$s The time it takes for the largest content element on a page to load. This is typically an image or video.', 'speedguard' ), '<strong>' . esc_html__( 'Largest Contentful Paint (LCP):', 'speedguard' ) . '</strong>' ) );
                 ?>
                </p>
                <p>
                     <img src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . 'assets/images/cls.svg' ); ?>"
                          alt="<?php echo esc_attr( 'Cumulative Layout Shift chart' ); ?>">
                  <?php
                  echo wp_kses_post( sprintf( __( '%1$s The total amount of layout shift on a page while it is loading. This is a measure of how much the content on a page moves around while it is loading.', 'speedguard' ), '<strong>' . esc_html__( 'Cumulative Layout Shift (CLS):', 'speedguard' ) . '</strong>' ) );
                  ?>
                 </p>
                <p>
                         <img src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . 'assets/images/inp.svg' ); ?>"
                              alt="<?php echo esc_attr( 'Interaction to Next Paint chart' ); ?>">

                    <?php
                    echo wp_kses_post( sprintf( __( '%1$s The time it takes the website to respond to a user interaction, such as clicking a button or tapping on a link, throughout entire user experience with the page. This is a measure of how responsive a web page feels to users.', 'speedguard' ), '<strong>' . esc_html__( 'Interaction to Next Paint (INP):', 'speedguard' ) . '</strong>' ) );
                    ?>
               </p>
                <p>

            </span>
            </li>
        </ul>
		<?php
	}


	public static function add_new_widget_function() {
		$nonce_field = wp_nonce_field( 'sg_add_new_url', 'sg_add_new_nonce_field', true, false );
		$content     = '<form name="speedguard_add_url" id="speedguard_add_url" method="post" action="">';
		$content     .= $nonce_field;
		$content     .= '<input class="form-control" type="text" id="speedguard_new_url" name="speedguard_new_url" value="" placeholder="' . esc_attr__( 'Start typing the title of the post, page or custom post type...', 'speedguard' ) . '" autofocus="autofocus"/>';
		$content     .= '<input type="hidden" id="blog_id" name="blog_id" value="" />';
		$content     .= '<input type="hidden" id="speedguard_new_url_permalink" name="speedguard_new_url_permalink" value=""/>';
		$content     .= '<input type="hidden" id="speedguard_item_type" name="speedguard_item_type" value=""/>';
		$content     .= '<input type="hidden" id="speedguard_new_url_id" name="speedguard_new_url_id" value=""/>';
		$content     .= '<input type="hidden" name="speedguard" value="add_new_url" />';
		$content     .= '<input type="submit" name="Submit" class="button action" value="' . esc_attr__( 'Add', 'speedguard' ) . '" />';
		$content     .= '</form>';

		echo wp_kses( $content, array(
			'form'   => array(
				'name'   => array(),
				'id'     => array(),
				'method' => array(),
				'action' => array()
			),
			'input'  => array(
				'class'       => array(),
				'type'        => array(),
				'id'          => array(),
				'name'        => array(),
				'value'       => array(),
				'placeholder' => array(),
				'autofocus'   => array()
			),
			'submit' => array(
				'name'  => array(),
				'class' => array(),
				'value' => array()
			)
		) );
	}

	public static function important_questions_widget_function() {
		echo wp_kses_post( SpeedGuard_Widgets::get_important_questions_widget_function() );
	}

	public static function get_important_questions_widget_function() {
		//Convert this function to return instead of echo

		$links   = [
			sprintf( __( '%1$sWhy CWV fail after they were passing before?%2$s', 'speedguard' ), '<a href="https://www.youtube.com/watch?v=Q40B5cscObc" target="_blank">', '</a>' ),
			sprintf( __( '%1$sOne single reason why your CWV are not passing%2$s', 'speedguard' ), '<a href="https://youtu.be/-d7CPbjLXwg?si=VmZ_q-9myI4SBYSD" target="_blank">', '</a>' ),
			sprintf( __( '%1$s5 popular recommendations that don’t work%2$s', 'speedguard' ), '<a href="https://youtu.be/5j3OUaBDXKI?si=LSow4BWgtF9cSQKq" target="_blank">', '</a>' ),
		];
		$content = '<ul>';
		foreach ( $links as $link ) {
			$content .= '<li>' . $link . '</li>';
		}
		$content .= '</ul>';

		return $content;
	}

	public static function howto_widget_function() {
		$content = '<style>.youtube-container{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%;}.youtube-container iframe{position:absolute;top:0;left:0;width:100%;height:100%;}</style>';
		$content .= '<ul>';
		$content .= '<li>' . sprintf( __( 'Add the URL of the page you want to monitor. You can add as many URLs as you want. The plugin will check them every day.', 'speedguard' ) ) . '</li>';
		$content .= '<li>' . sprintf( __( 'Check the results in the table below. If you see a red or yellow score, it means that there is a problem with the page.', 'speedguard' ) ) . '</li>';
	    $content .= '<li>' . sprintf( __( 'If you have any other questions, feel free to contact me. I will be happy to help you.', 'speedguard' ) ) . '</li>';

		// Add YouTube video with responsive wrapper
		$content .= '<div class="youtube-container">';
		$content .= '<iframe src="https://www.youtube.com/embed/y_RvQEhdq9c" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
		$content .= '</div>';

		$content .= '</ul>';

		// Define allowed HTML tags
		$allowed_html = array(
			'style' => array(),
			'ul' => array(),
			'li' => array(),
			'div' => array(
				'class' => array(),
			),
			'iframe' => array(
				'src' => array(),
				'title' => array(),
				'frameborder' => array(),
				'allow' => array(),
				'allowfullscreen' => array(),
			),
		);

		echo wp_kses( $content, $allowed_html );
	}


	public static function about_widget_function() {
		$picture        = '<a href="https://sabrinazeidan.com/?utm_source=speedguard&utm_medium=sidebar&utm_campaign=avatar" target="_blank"><div id="szpic"></div></a>';

		/* translators: Hey! My name is Sabrina.
		   I speed up websites every day, and I built this plugin because I needed a simple tool to monitor site speed and notify me if something is not right.
		   Hope it will be helpful for you too.
		*/
		$hey            = sprintf( __( 'Hey!%1$s My name is %3$sSabrina%4$s. 
        %1$sI speed up websites every day, and I built this plugin because I needed a simple tool to monitor site speed and notify me if something is not right.%2$s
        %1$sHope it will be helpful for you too.%2$s
        %2$s', 'speedguard' ), '<p>', '</p>', '<a href="https://sabrinazeidan.com/?utm_source=speedguard&utm_medium=sidebar&utm_campaign=sabrina" target="_blank">', '</a>' );

		$rate_link      = 'https://wordpress.org/support/plugin/speedguard/reviews/?rate=5#new-post';

		/* translators: If you like it, I would greatly appreciate if you add your ★★★★★ to spread the love. */
		$rate_it        = sprintf( __( 'If you like it, I would greatly appreciate if you add your %1$s★★★★★%2$s to spread the love.', 'speedguard' ), '<a class="rate-link" href="' . $rate_link . '" target="_blank">', '</a>' );

		$translate_link = 'https://translate.wordpress.org/projects/wp-plugins/speedguard/';

		/* translators: You can also help translate it to your language so that more people will be able to use it ❤︎ */
		$translate_it   = sprintf( __( 'You can also help %1$stranslate it to your language%2$s so that more people will be able to use it ❤︎', 'speedguard' ), '<a href="' . $translate_link . '" target="_blank">', '</a>' );

		//add the line: If you'd like to to buy me a gelato -- here is where: https://buymeacoffee.com/sabrinazeidan
		$gelato_link    = 'https://buymeacoffee.com/sabrinazeidan';

		/* translators: If you'd like to thank me -- buy me a gelato here. */
		$gelato_it      = sprintf( __( 'If you\'d like to thank me -- %1$s', 'speedguard' ), '<a href="' . $gelato_link . '" target="_blank">' . esc_html__( 'buy me a gelato here.', 'speedguard' ) . '</a>' );

		/* translators: Cheers! */
		$cheers         = sprintf( __( 'Cheers!', 'speedguard' ) );

		$content        = $picture . $hey . '<p>' . $rate_it . '</p><p>' . $translate_it . '<p>' .$gelato_it.'</p><p>'. $cheers.'</p>';
		echo wp_kses_post( $content );
	}


	function speedguard_dashboard_widget_function() {
		wp_add_dashboard_widget( 'speedguard_dashboard_widget', __( 'Current Performance', 'speedguard' ), [
			$this,
			'origin_results_widget_function',
		], '', [ 'echo' => 'true' ] );
		// Widget position
		global $wp_meta_boxes;
		$normal_dashboard      = $wp_meta_boxes[ 'dashboard' . ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? '-network' : '' ) ]['normal']['core'];
		$example_widget_backup = [ 'speedguard_dashboard_widget' => $normal_dashboard['speedguard_dashboard_widget'] ];
		unset( $normal_dashboard['speedguard_dashboard_widget'] );
		$sorted_dashboard                             = array_merge( $example_widget_backup, $normal_dashboard );
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}


}

new SpeedGuard_Widgets();
