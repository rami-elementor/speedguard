<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://sabrinazeidan.com/
 * @since      1.0.0
 * @package    Speedguard
 * @subpackage Speedguard/admin
 * @author     Sabrina Zeidan <sabrinazeidan@gmail.com>
 */

class SpeedGuard_Admin {


	const SG_METRICS_ARRAY = [
		'mobile'  => [
			'psi' => [ 'lcp', 'cls' ],
			'cwv' => [ 'lcp', 'cls', 'inp' ],
		],
		'desktop' => [
			'psi' => [ 'lcp', 'cls' ],
			'cwv' => [ 'lcp', 'cls', 'inp' ],
		],
	];
	public static $cpt_name = 'guarded-page';
	public $main_page;
	public $tests_page_hook;
	public $settings_page_hook;
	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		// PRO (for debugging)
		define( 'SPEEDGUARD_PRO', false );
		// Multisite
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active_for_network( 'speedguard/speedguard.php' ) ) {
			define( 'SPEEDGUARD_MU_NETWORK', true );
		}
		if ( is_multisite() && ! ( is_plugin_active_for_network( 'speedguard/speedguard.php' ) ) ) {
			define( 'SPEEDGUARD_MU_PER_SITE', true );
		}
		// Menu items and Admin notices
		add_action( ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? 'network_' : '' ) . 'admin_menu', [
			$this,
			'speedguard_admin_menu',
		] );
		add_action( ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? 'network_' : '' ) . 'admin_notices', [
			$this,
			'show_admin_notices',
		] );
		// If Network activated don't load stuff on subsites. Load on the main site of the Multisite network or for regular WP install
		global $blog_id;
		if ( ! ( is_plugin_active_for_network( 'speedguard/speedguard.php' ) ) || ( is_plugin_active_for_network( 'speedguard/speedguard.php' ) ) && ( is_main_site( $blog_id ) ) ) {
			require_once plugin_dir_path( __FILE__ ) . '/includes/class.widgets.php';
			require_once plugin_dir_path( __FILE__ ) . '/includes/class.settings.php';
			require_once plugin_dir_path( __FILE__ ) . '/includes/class.tests-table.php';
			if ( speedguard_fs()->is__premium_only() ) {
				require_once plugin_dir_path( __FILE__ ) . '/includes/class.notifications.php';
			}
			add_action( 'admin_init', [ $this, 'speedguard_cpt' ] );
			add_action( 'current_screen', [ $this, 'sg_add_notices' ] );
			add_filter( 'admin_body_class', [ $this, 'body_classes_filter' ] );
			add_action( 'transition_post_status', [ $this, 'guarded_page_unpublished_hook' ], 10, 3 );
			add_action( 'before_delete_post', [ $this, 'before_delete_test_hook' ], 10, 1 );
			// MU Headers alredy sent fix
			add_action( 'init', [ $this, 'app_output_buffer' ] );
			// Add removable query args
			add_filter( 'removable_query_args', [ $this, 'removable_query_args' ] );
			add_filter( ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? 'network_admin_' : '' ) . 'plugin_action_links_speedguard/speedguard.php', [
				$this,
				'speedguard_actions_links',
			] );
		}
		// Fn to check the tests queue and initiate tests
		add_action( 'wp_ajax_check_tests_progress', [ $this, 'check_tests_progress_fn' ] );
		// Fn to mark individual test as done and save results to post_meta
		add_action( 'wp_ajax_mark_test_as_done', [ $this, 'mark_test_as_done_fn' ] );
		//Recount PSI Average for Origin when test is deleted
		add_action( 'deleted_post', [ $this, 'update_average_psi_on_deletion' ], 10, 1 );

	}

	//Add a few admin notices on Tests page
	public static function sg_add_notices() {
		if ( self::is_screen( 'tests' ) ) {

			$speedguard_cwv_origin = SpeedGuard_Admin::get_this_plugin_option( 'sg_origin_results' );
			if ( ! is_array( $speedguard_cwv_origin ) ) {
				return; // or handle the error appropriately
			}

			// Check if this is localhost or staging
			// If PSI lcp value is not available, it might mean it's localhost or staging, set transient to show notice
			if ( isset( $speedguard_cwv_origin['desktop']['psi']['lcp']['average'] ) && str_contains( $speedguard_cwv_origin['desktop']['psi']['lcp']['average'], "N" ) ) {
				set_transient( 'speedguard_not_production_environment', true, 10 );
			}
			// Check if tests are finished, but no CWV data available (and production) -- then suggest PSI
			// Tests are just done and it's production
			elseif ( get_transient( 'speedguard_last_test_is_done' ) && ! get_transient( 'speedguard_not_production_environment' ) ) {
				$sg_test_type          = SpeedGuard_Settings::global_test_type();
				$speedguard_cwv_origin = SpeedGuard_Admin::get_this_plugin_option( 'sg_origin_results' );

				// If CWV origin exists (tests finished) but it's N/A
				if ( isset( $speedguard_cwv_origin['mobile']['cwv']['lcp'] ) ) {
					$mobile_lcp = $speedguard_cwv_origin['mobile']['cwv']['lcp'];
					if ( 'cwv' === $sg_test_type && str_contains( $mobile_lcp, 'N' ) ) {
						set_transient( 'speedguard_no_cwv_data', true, 10 );
					}
				} //TODO take any test and compare it LCP, CLS and INP with origin


				else {
					// Handle the case where the keys are missing
					// Example: Log the error or set a different transient
					error_log( 'Expected keys missing in $speedguard_cwv_origin' );
				}
			}
		}
	}

	//Fired when post meta is deleted or updated

	public static function is_screen( $screens ) {
		// screens: dashboard,settings,tests,plugins, clients
		$screens = explode( ',', $screens );
		$screens = str_replace( [ 'tests', 'settings', 'clients' ], [
			'toplevel_page_speedguard_tests',
			'speedguard_page_speedguard_settings',
			'speedguard_page_speedguard_clients',
		], $screens );
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		// Multisite screens
		if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
			foreach ( $screens as $screen ) {
				$screens[] = $screen . '-network';
			}
		}
		$current_screen = get_current_screen();
		if ( $current_screen ) {
			$current_screen = $current_screen->id;
		}
		if ( in_array( ( $current_screen ), $screens ) ) {
			$return = true;
		} else {
			$return = false;
		}

		return $return;
	}

	public static function get_this_plugin_option( $option_name ) {
		if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
			return get_site_option( $option_name );
		} else {
			return get_option( $option_name );
		}
	}

	//MArk individual test as done and save results to post_meta

	public static function capability() {
		$capability = 'manage_options';

		return $capability;
	}


	//Post types that will be queriable in autocomeplete field
	public static function supported_post_types() {
		$args                 = [
			'publicly_queryable'  => true,
			'exclude_from_search' => false
		];
		$output               = 'names';
		$operator             = 'and';
		$supported_post_types = get_post_types( $args, $output, $operator );
		unset( $supported_post_types['attachment'] );
		$supported_post_types['page'] = 'page';

		return $supported_post_types;
	}

	public static function before_delete_test_hook( $postid ) {
		if ( get_post_type( $postid ) === self::$cpt_name ) {
			$guarded_item_id   = get_post_meta( $postid, 'guarded_post_id', true );
			$guarded_item_type = get_post_meta( $postid, 'speedguard_item_type', true );
			if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
				$blog_id = get_post_meta( $postid, 'guarded_post_blog_id', true );
				switch_to_blog( $blog_id );
			}
			if ( $guarded_item_type === 'single' ) {
				update_post_meta( $guarded_item_id, 'speedguard_on', 'false' );
			} elseif ( $guarded_item_type === 'archive' ) {
				update_term_meta( $guarded_item_id, 'speedguard_on', 'false' );
			}
			if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
				switch_to_blog( get_network()->site_id );
			}
		}
	}

	public static function guarded_page_unpublished_hook( $new_status, $old_status, $post ) {
		// Delete test data when original post got unpublished
		if ( ( $old_status === 'publish' ) && ( $new_status != 'publish' ) && ( get_post_type( $post->ID ) ) != self::$cpt_name ) {
			$speedguard_on = get_post_meta( $post->ID, 'speedguard_on', true );
			if ( $speedguard_on && $speedguard_on[0] === 'true' ) {
				$connected_guarded_pages = get_posts( [
					'post_type'      => self::$cpt_name,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [
						[
							'key'     => 'guarded_post_id',
							'value'   => $post->ID,
							'compare' => 'LIKE',
						],
					],
					'no_found_rows'  => true,
				] );
				if ( $connected_guarded_pages ) {
					foreach ( $connected_guarded_pages as $connected_guarded_page_id ) {
						SpeedGuard_Tests::delete_test_fn( $connected_guarded_page_id );
					}
					// uncheck speedguard_on
					update_post_meta( $post->ID, 'speedguard_on', 'false' );
				}
			}
		}
	}

	public static function delete_this_plugin_option( $option_name ) {
		if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
			return delete_site_option( $option_name );
		} else {
			return delete_option( $option_name );
		}
	}

	public static function speedguard_cpt() {
		$args = [
			'public'              => false,
			'exclude_from_search' => true,
			// 'publicly_queryable'      => true,
			'show_ui'             => true,
			'supports'            => [ 'title', 'custom-fields' ],
		];
		register_post_type( 'guarded-page', $args );
	}

	public static function show_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Initialize an empty array to collect notices
		$notices = [];

		$global_test_type = SpeedGuard_Settings::global_test_type();

		// All screens
		 if ( !self::is_screen( 'tests') && (int) get_transient( 'speedguard_tests_count' ) === 1 ) {
			$message   = sprintf( __( 'You only have the performance of 1 page monitored currently. Would you like to %1$sadd other pages%2$s to see the whole picture of the site speed?', 'speedguard' ), '<a href="' . self::speedguard_page_url( 'tests' ) . '">', '</a>' );
			$notices[] = self::set_notice( $message, 'warning' );
		}

		// On activation
		if ( get_transient( 'speedguard-notice-activation' ) ) {
			if ( self::is_screen( 'tests' ) ) {
				$message   = __( 'Homepage performance test has just started. Watch the video to make the most use of this plugin ->', 'speedguard' );
				$notices[] = self::set_notice( $message, 'success' );
			} else {
				$message   = sprintf( __( 'Homepage performance test has just started. Would you like to %1$stest some other pages%2$s as well?', 'speedguard' ), '<a href="' . self::speedguard_page_url( 'tests' ) . '">', '</a>' );
				$notices[] = self::set_notice( $message, 'success' );
			}

		}


		// Tests screen Notices
		if ( self::is_screen( 'tests' ) ) {
			//Notices about tests statuses
			//When trying to add test
			if ( ! empty( $_REQUEST['speedguard'] ) && isset( $_GET['sg_redirect_nonce'] ) && wp_verify_nonce( $_GET['sg_redirect_nonce'], 'sg_redirect_nonce_action' ) ) {

				if ( get_transient( 'speedguard_notice_add_new_url_error_empty' ) ) {
					$notices[] = self::set_notice( __( 'Please select the post you want to add.', 'speedguard' ), 'warning' );
				}
				if ( get_transient( 'speedguard_notice_add_new_url_error_not_url' ) ) {
					$notices[] = self::set_notice( __( 'Please enter valid URL or select the post you want to add.', 'speedguard' ), 'warning' );
				}
				if ( get_transient( 'speedguard_notice_create_test' ) ) {
					$notices[] = self::set_notice( __( 'New URL is successfully added!', 'speedguard' ), 'success' );
				}
			}
			if ( get_transient( 'speedguard_notice_add_new_url_error_not_current_domain' ) ) {
				$notices[] = self::set_notice( __( 'SpeedGuard only monitors pages from current website.', 'speedguard' ), 'warning' );
			}
			if ( get_transient( 'speedguard_notice_slow_down' ) ) {
				$notices[] = self::set_notice( __( 'You are moving too fast. Wait at least 3 minutes before updating the tests', 'speedguard' ), 'warning' );
			}
			if ( get_transient( 'speedguard_notice_already_in_queue' ) ) {
				$notices[] = self::set_notice( __( 'This URL is currently in the queue.', 'speedguard' ), 'success' );
			}
			if ( get_transient( 'speedguard_notice_delete_guarded_pages' ) ) {
				$notices[] = self::set_notice( __( 'Selected pages are not guarded anymore!', 'speedguard' ), 'success' );
			}


			// Notices about queue status when tests are running
			// Tests are being updated
			// Tests are being updated, but not the first run after activation
            if ( get_transient( 'speedguard_tests_in_queue' ) && !get_transient( 'speedguard-notice-activation' )) {
				$notices[] = self::set_notice( __( 'Tests are being updated. You can stay on this page, or you can leave it -- tests will be running.', 'speedguard' ), 'success' );
			} // When tests are finished
			elseif ( get_transient( 'speedguard_last_test_is_done' ) or ( isset( $_REQUEST['speedguard'] ) && $_REQUEST['speedguard'] === 'load_time_updated' ) ) {
				$notices[] = self::set_notice( __( 'Results have been updated', 'speedguard' ), 'success' );
			}


			// Notices about environment
			// There is no PSI data -- most likeley it's not a production environment
			if ( get_transient( 'speedguard_not_production_environment' ) ) {
				$notices[] = self::set_notice( __( 'Is this a live website? Tests can\'t be executed on staging or localhost. Install it on the live website.', 'speedguard' ), 'error' );
			} //there is PSI, but no CWV
			elseif ( get_transient( 'speedguard_no_cwv_data' ) && $global_test_type === 'cwv' ) {
				$notices[] = self::set_notice( sprintf( __( 'There is no Core Web Vitals data available for this website currently. Most likely your website has not got enough traffic for Google to make an evaluation. You can %sswitch%s to lab tests (PageSpeed Insights) though.', 'speedguard' ), '<a href="' . esc_url( admin_url( 'admin.php?page=speedguard_settings' ) ) . '">', '</a>' ), 'warning' );

			} // There is CWV data, but only for Origin
			elseif ( ( get_transient( 'speedguard_notice_cwv_mobile_match' ) ) && $global_test_type === 'cwv' ) {
				$settings_link = esc_url( admin_url( 'admin.php?page=speedguard_settings' ) );
				$message       = __( 'Your CWV tests results are the same for all pages -- it means that Google doesn\'t have data for specific URLs, and only has data for this website in general (usually, due to little traffic on the website).', 'speedguard' );
				$message       .= '<br/>';
				$message       .= sprintf( __( 'You can switch to PSI in  %sSettings%s or you can keep tracking CWV for origin only (add just 1 URL for now).', 'speedguard' ), '<a href="' . $settings_link . '">', '</a>' );
				$notices[]     = self::set_notice( $message, 'warning' );
			}


		} elseif ( self::is_screen( 'settings' ) ) {
			if ( ! empty( $_REQUEST['settings-updated'] ) && $_REQUEST['settings-updated'] === 'true' ) {
				$notices[] = self::set_notice( __( 'Settings have been updated!' ), 'success' );
			}
		}


		// Display collected notices
		if ( ! empty( $notices ) ) {
			foreach ( $notices as $notice ) {
				echo wp_kses_post( $notice );
			}
		}
	}

	public static function speedguard_page_url( $page ) {
		if ( $page === 'tests' ) {
			$admin_page_url = defined( 'SPEEDGUARD_MU_NETWORK' ) ? network_admin_url( 'admin.php?page=speedguard_tests' ) : admin_url( 'admin.php?page=speedguard_tests' );
		} elseif ( $page === 'settings' ) {
			$admin_page_url = defined( 'SPEEDGUARD_MU_NETWORK' ) ? network_admin_url( 'admin.php?page=speedguard_settings' ) : admin_url( 'admin.php?page=speedguard_settings' );
		}

		return $admin_page_url;
	}

	public static function set_notice( $message, $class ) {
		return "<div class='notice notice-$class is-dismissible'><p>$message</p></div>";
	}

	function update_average_psi_on_deletion( $post_id ) {
		//check if there are no tests in queue to avoid updating the same data multiple times
		$current_tests_array = get_transient( 'speedguard_tests_in_queue', true );
		if ( ! empty( $current_tests_array ) ) {
			return;
		}
		//check if its this post type was deleted
		if ( get_post_type( $post_id ) !== SpeedGuard_Admin::$cpt_name ) {
			return;
		}

		//get current CWV for origin value
		$current_cwv_origin_data = SpeedGuard_Admin::get_this_plugin_option( 'sg_origin_results' );


		$calculated_average_psi = SpeedGuard_Admin::count_average_psi(); // Returns PSI only
		// merge calculated average psi with current cwv origin data
		$both_devices_values_origin = [
			'mobile'  => [
				'cwv' => [
					'lcp'              => $current_cwv_origin_data['mobile']['cwv']['lcp'],
					//TODO check seems to be fine
					'cls'              => $current_cwv_origin_data['mobile']['cwv']['cls'],
					'inp'              => $current_cwv_origin_data['mobile']['cwv']['inp'],
					'overall_category' => $current_cwv_origin_data['mobile']['cwv']['overall_category']
				],
				'psi' => [
					'lcp' => $calculated_average_psi['mobile']['psi']['lcp'],
					'cls' => $calculated_average_psi['mobile']['psi']['cls']
				]

			],
			'desktop' => [
				'cwv' => [
					'lcp'              => $current_cwv_origin_data['desktop']['cwv']['lcp'],
					//array if ok, string if no data
					'cls'              => $current_cwv_origin_data['desktop']['cwv']['cls'],
					'inp'              => $current_cwv_origin_data['desktop']['cwv']['inp'],
					'overall_category' => $current_cwv_origin_data['desktop']['cwv']['overall_category']
				],
				'psi' => [
					'lcp' => $calculated_average_psi['desktop']['psi']['lcp'],
					'cls' => $calculated_average_psi['desktop']['psi']['cls']
				]
			]
		];


		// Update Average PSI (CWV is used from saved before) for Origin
		$update_cwv_origin_data = SpeedGuard_Admin::update_this_plugin_option( 'sg_origin_results', $both_devices_values_origin );

	}

	// Delete test data when original post got unpublished

	public static function count_average_psi() {
		// Prepare new values for PSI Averages
		$new_average_array = [];
		// Get all tests with valid results
		$guarded_pages = get_posts( [
			'posts_per_page' => 100, // 100 is enough to get the general picture and not overload the server
			'no_found_rows'  => true,
			'post_type'      => SpeedGuard_Admin::$cpt_name,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => 'sg_test_result',
					'value'   => 'waiting',
					'compare' => 'NOT LIKE',
				]
			]
		] );

		// If there are no tests with valid results, return an empty array
		if ( empty( $guarded_pages ) ) {
			return [];
		}

		// Initialize the average array
		$average = [];

		// Loop through the guarded pages
		foreach ( $guarded_pages as $guarded_page ) {
			// Get the guarded page load time
			$guarded_page_load_time = get_post_meta( $guarded_page, 'sg_test_result', true );

			// Loop through the device types
			foreach ( SpeedGuard_Admin::SG_METRICS_ARRAY as $device => $test_types ) {
				// Loop through the test types
				foreach ( $test_types as $test_type => $metrics ) {
					// If the test type is PSI, prepare the metrics
					if ( $test_type === 'psi' ) {
						foreach ( $metrics as $metric ) {
							// Check if numericValue exists and is a valid number
							$numericValue = $guarded_page_load_time[ $device ][ $test_type ][ $metric ]['numericValue'] ?? 'N/A';
							if ( ! is_numeric( $numericValue ) ) {
								$numericValue = 'N/A';
							}
							// Add the guarded page load time to the average array
							$average[ $device ][ $test_type ][ $metric ]['guarded_pages'][ $guarded_page ] = $numericValue;
						}
					}
				}
			}
		}

		// Loop through the average array
		foreach ( $average as $device => $test_types ) {
			// Loop through the test types
			foreach ( $test_types as $test_type => $metrics ) {
				// Loop through the metrics
				foreach ( $metrics as $metric => $values ) {
					// Filter out 'N/A' values
					$valid_values = array_filter( $values['guarded_pages'], 'is_numeric' );

					// Calculate the average if there are valid numeric values
					if ( count( $valid_values ) > 0 ) {
						$average_value = array_sum( $valid_values ) / count( $valid_values );
					} else {
						$average_value = 'N/A';
					}

					// Create a new metric array
					$new_metric_array = [
						'average' => $average_value,
					];

					// If the metric is LCP, calculate the display value and score
					if ( $metric === 'lcp' && $average_value !== 'N/A' ) {
						$average_value                    = round( $average_value / 1000, 2 );
						$new_metric_array['displayValue'] = $average_value . ' s';
						if ( $average_value < 2.5 ) {
							$new_metric_array['score'] = 'FAST';
						} elseif ( $average_value < 4.0 ) {
							$new_metric_array['score'] = 'AVERAGE';
						} else {
							$new_metric_array['score'] = 'SLOW';
						}
					}

					// If the metric is CLS, calculate the display value and score
					if ( $metric === 'cls' && $average_value !== 'N/A' ) {
						$average_value                    = round( $average_value, 3 );
						$new_metric_array['displayValue'] = $average_value;
						if ( $average_value < 0.1 ) {
							$new_metric_array['score'] = 'FAST';
						} elseif ( $average_value < 0.25 ) {
							$new_metric_array['score'] = 'AVERAGE';
						} else {
							$new_metric_array['score'] = 'SLOW';
						}
					}

					// Add the new metric array to the new average array
					$new_average_array[ $device ][ $test_type ][ $metric ] = $new_metric_array;
				}
			}
		}

		// Return the new average array
		return $new_average_array;
	}


	public static function update_this_plugin_option( $option_name, $option_value ) {
		if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
			return update_site_option( $option_name, $option_value );
		} else {
			return update_option( $option_name, $option_value );
		}
	}

	function check_tests_progress_fn() {

		//Only transient here
		check_ajax_referer( 'check_tests_progress', 'nonce' );
		//check current tests transient
		$current_tests_array  = get_transient( 'speedguard_tests_in_queue', true );
		$last_test_is_done_tr = get_transient( 'speedguard_last_test_is_done' );
		$test_in_progress     = get_transient( 'speedguard_test_in_progress' );
		//Possible responses:
		//There were tests in the queue but now all have just been completed
		$last_test_complete = [
			'status'  => 'last_test_complete',
			'message' => 'All tests are complete',
		];
		// There are no tests in the queue
		$do_nothing = [
			'status'  => 'no_tests',
			'message' => 'There are no tests in queue',
		];

		//There are still some tests in the queue
		if ( $current_tests_array ) {
			$current_tests_array = json_decode( $current_tests_array, true );
			// Run 1 test async in the separate function
			//If it's the first request and any test in not in progress now
			if ( ! get_transient( 'speedguard_test_in_progress' ) && ( ! empty( $current_tests_array ) ) ) {
				//$one_test_id = array_shift( array_values( $current_tests_array ) );
				$one_test_id = current( $current_tests_array );
				set_transient( 'speedguard_test_in_progress', $one_test_id );
				$upd_tr_value      = get_transient( 'speedguard_test_in_progress' );
				$tests_are_running = [
					'status'                          => 'queue',
					'message'                         => 'There are tests in queue, there was NO speedguard_test_in_progress transient, setting it now',
					'tr_value'                        => $upd_tr_value,
					'tests_in_queue'                  => $current_tests_array,
					'speedguard_test_in_progress_id'  => $one_test_id,
					'speedguard_test_in_progress_url' => get_post_meta( $one_test_id, 'speedguard_page_url', true ),
					'action_just_done'                => 'set_transient_speedguard_test_in_progress'
				];

			} else { //if there is a test in progress

				$one_test_id       = json_decode( get_transient( 'speedguard_test_in_progress' ), true );
				$tests_are_running = [
					'status'                          => 'queue',
					'message'                         => 'There are tests in queue, there WAS speedguard_test_in_progress transient, do not update',
					'tests_in_queue'                  => $current_tests_array,
					'speedguard_test_in_progress_id'  => $one_test_id,
					'speedguard_test_in_progress_url' => get_post_meta( $one_test_id, 'speedguard_page_url', true ),
					'action_just_done'                => 'nothing, test in progress was added before'
				];

			}
			$response = $tests_are_running;
		} else if ( ! $current_tests_array && ! $test_in_progress && ( $last_test_is_done_tr !== false ) ) { //if there are no tests in the queue, but last test has just completed
			delete_transient( 'speedguard_last_test_is_done' );
			$response = $last_test_complete;
		} else { // if there are no tests and not waiting for the last one to complete
			$response = $do_nothing;
		}
		wp_send_json( $response );
	}

	function mark_test_as_done_fn() {
		check_ajax_referer( 'mark_test_as_done', 'run_nonce' );
		if ( empty( $_POST['current_test_id'] ) ) {
			return;
		}
		//Data that we expect to have in the request: current_test_id, test_result_data, nonce

		$current_test               = $_POST['current_test_id'];
		$test_result_data_from_post = wp_unslash( $_POST['test_result_data'] ); // don't know where those slashes come from
		$test_result_data           = json_decode( $test_result_data_from_post, true );


		$mobile_data = isset($test_result_data[0]['mobile']) ? $test_result_data[0]['mobile'] : null;
		$desktop_data = isset($test_result_data[1]['desktop']) ? $test_result_data[1]['desktop'] : null;


		$both_devices_values = [
			'mobile'  => [
				'cwv' => [
					'lcp'              => $mobile_data['cwv']['lcp'], //TODO check seems to be fine
					'cls'              => $mobile_data['cwv']['cls'],
					'inp'              => $mobile_data['cwv']['inp'],
					'overall_category' => $mobile_data['cwv']['overall_category']
				],
				'psi' => [
					'lcp' => $mobile_data['psi']['lcp'],
					// title, description, score, scoreDisplayMode, displayValue, numericValue
					'cls' => $mobile_data['psi']['cls'],
				]
			],
			'desktop' => [
				'cwv' => [
					'lcp'              => $desktop_data['cwv']['lcp'], //array if ok, string if no data
					'cls'              => $desktop_data['cwv']['cls'],
					'inp'              => $desktop_data['cwv']['inp'],
					'overall_category' => $desktop_data['cwv']['overall_category']
				],
				'psi' => [
					'lcp' => $desktop_data['psi']['lcp'], //array
					// title, description, score, scoreDisplayMode, displayValue, numericValue
					'cls' => $desktop_data['psi']['cls'],
				]

			]
		];

		$update_url_values = update_post_meta( $current_test, 'sg_test_result', $both_devices_values );

		// update post date also
		$update_post_date = wp_update_post( array(
			'ID'            => $current_test,
			'post_date'     => current_time( 'mysql' ),
			'post_date_gmt' => current_time( 'mysql', 1 )
		) );

//wp_mail('sabrinazeidanspain@gmail.com', 'another attempt1205',    '$test_result_data:  '.print_r($test_result_data,true).'$mobile_data:  '.print_r($mobile_data,true).'$desktop_data:  '.print_r($desktop_data,true).'<br>$device_values ' .print_r($both_devices_values,true).'<br>$test_result_data[0]'.print_r($test_result_data[0], true), 'Content-Type: text/html; charset=UTF-8');


		//Mark test as done in the queue
		$current_tests_array = json_decode( get_transient( 'speedguard_tests_in_queue' ), true );

		if ( is_array( $current_tests_array ) && in_array( $current_test, $current_tests_array ) ) {
			$key = array_search( $current_test, $current_tests_array );
			unset( $current_tests_array[ $key ] );
		}

		delete_transient( 'speedguard_test_in_progress' );
		//if after removing this test there are no tests left to process, mark that this is the last test in queue and delete transient
		if ( ( ! is_array( $current_tests_array ) ) || ( count( $current_tests_array ) < 1 ) ) {
			delete_transient( 'speedguard_tests_in_queue' );
			set_transient( 'speedguard_last_test_is_done', true, 300 );
			$last_test_is_done = true;


			//Update CWV here, and count average psi
			$calculated_average_psi = SpeedGuard_Admin::count_average_psi();

			//Save CWV for origin

			$both_devices_values_origin = [
				'mobile'  => [
					'cwv' => [
						'lcp'              => $mobile_data['originCWV']['lcp'], //TODO check seems to be fine
						'cls'              => $mobile_data['originCWV']['cls'],
						'inp'              => $mobile_data['originCWV']['inp'],
						'overall_category' => $mobile_data['originCWV']['overall_category']
					],
					'psi' => [
						'lcp' => $calculated_average_psi['mobile']['psi']['lcp'],
						'cls' => $calculated_average_psi['mobile']['psi']['cls']
					]

				],
				'desktop' => [
					'cwv' => [
						'lcp'              => $desktop_data['originCWV']['lcp'], //array if ok, string if no data
						'cls'              => $desktop_data['originCWV']['cls'],
						'inp'              => $desktop_data['originCWV']['inp'],
						'overall_category' => $desktop_data['originCWV']['overall_category']
					],
					'psi' => [
						'lcp' => $calculated_average_psi['desktop']['psi']['lcp'],
						'cls' => $calculated_average_psi['desktop']['psi']['cls']
					]
				]
			];


			$update_cwv_origin_data = SpeedGuard_Admin::update_this_plugin_option( 'sg_origin_results', $both_devices_values_origin );


			// Compare CWV origin and test results CWV values for moibile. if htey are the same -- set transient to show the notice that there is no Google data available for separate URLs, only for Origin
			// Assuming $both_devices_values and $both_devices_values_origin are defined as in your question

			// Extract CWV mobile data from both arrays
			$mobile_cwv_values = [
				'lcp' => [
					'both' => [
						'mobile' => $both_devices_values['mobile']['cwv']['lcp']['percentile'],
						'origin' => $both_devices_values_origin['mobile']['cwv']['lcp']['percentile']
					]
				],
				'cls' => [
					'both' => [
						'mobile' => $both_devices_values['mobile']['cwv']['cls']['percentile'],
						'origin' => $both_devices_values_origin['mobile']['cwv']['cls']['percentile']
					]
				],
				'inp' => [
					'both' => [
						'mobile' => $both_devices_values['mobile']['cwv']['inp']['percentile'],
						'origin' => $both_devices_values_origin['mobile']['cwv']['inp']['percentile']
					]
				]
			];

			// Compare CWV mobile values
			foreach ( [ 'lcp', 'cls', 'inp' ] as $metric ) {
				$value_both   = $mobile_cwv_values[ $metric ]['both']['mobile'];
				$value_origin = $mobile_cwv_values[ $metric ]['both']['origin'];

				 // Perform comparison
                if ( (!str_contains( $value_both, "N")) && ($value_both === $value_origin) ) {
					set_transient( 'speedguard_notice_cwv_mobile_match', true );
				} else {
					delete_transient( 'speedguard_notice_cwv_mobile_match', true );
				}
			}


		} else {
			set_transient( 'speedguard_tests_in_queue', wp_json_encode( $current_tests_array ) );
		}
		$response = [
			'status'             => 'test marked as done',
			'test_id_passed'     => $current_test,
			'last_test_in_queue' => isset( $last_test_is_done ) ? $last_test_is_done : false
		];


		wp_send_json( $response );

	}

	public function speedguard_actions_links( array $actions ) {
		return array_merge( [
			'settings' => sprintf( __( '%1$sSettings%2$s', 'speedguard' ), '<a href="' . self::speedguard_page_url( 'settings' ) . '">', '</a>' ),
			'tests'    => sprintf( __( '%1$sTests%2$s', 'speedguard' ), '<a href="' . self::speedguard_page_url( 'tests' ) . '">', '</a>' ),
		], $actions );
	}

	// Plugin Styles
	public function removable_query_args( $query_args ) {
		if ( self::is_screen( 'settings,tests,clients' ) ) {
			$new_query_args = [ 'speedguard', 'new_url_id' ];
			$query_args     = array_merge( $query_args, $new_query_args );
		}

		return $query_args;
	}


	public function enqueue_styles() {
		if ( ( is_admin_bar_showing() ) && ( self::is_screen( 'dashboard,settings,tests' ) ) ) {
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/css/speedguard-admin.css', [], $this->version );
		}
		if ( is_admin_bar_showing() && self::is_screen( 'tests' ) ) {
			wp_enqueue_style( $this->plugin_name . '-awesompletecss', plugin_dir_url( __FILE__ ) . 'assets/awesomplete/awesomplete.css', [], $this->version );
		}
	}

	public function speedguard_tests_module_inline_fix( $tag, $handle ) {
		if ( strpos( $handle, 'speedguard_tests_module-js' ) === 0 ) {
			if ( current_theme_supports( 'html5', 'script' ) ) {
				return substr_replace( $tag, '<script type="module"', strpos( $tag, '<script' ), 7 );
			} else {
				return substr_replace( $tag, 'module', strpos( $tag, 'text/javascript' ), 15 );
			}
		}

		return $tag;
	}

	public function enqueue_scripts() {
		if ( is_admin_bar_showing() && ( self::is_screen( 'dashboard,settings,tests,plugins,clients' ) ) ) {
			//general JS
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/js/speedguard-admin.js', [], $this->version, false );

			//For making requests to API
			wp_enqueue_script( 'speedguard_tests_module', plugin_dir_url( __FILE__ ) . 'assets/js/execute_tests.js', [], filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/execute_tests.js' ), true );
			wp_add_inline_script( 'speedguard_tests_module', 'const SG_Tests_Data = "data here"' );


			wp_enqueue_script( 'speedguard_initiate_tests', plugin_dir_url( __FILE__ ) . 'assets/js/initiate_tests.js', [], filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/initiate_tests.js' ), true );

			// Localize the script with your data
			$data = [
				'sg_ajaxurl'                 => admin_url( 'admin-ajax.php' ),
				'sg_check_tests_queue_nonce' => wp_create_nonce( 'check_tests_progress' ),
				'sg_run_one_test_nonce'      => wp_create_nonce( 'mark_test_as_done' ),
				'reload'                     => self::is_screen( 'tests' ) ? 'true' : 'false',
			];

			$script = 'var initiate_tests_data = ' . wp_json_encode( $data ) . ';';

			wp_add_inline_script( 'speedguard_initiate_tests', $script, 'before' );


		}
		if ( is_admin_bar_showing() && self::is_screen( 'tests' ) ) {
			// search field with vanilla js
			wp_enqueue_script( $this->plugin_name . '-awesompletejs', plugin_dir_url( __FILE__ ) . 'assets/awesomplete/awesomplete.js', [], $this->version, true );
			wp_enqueue_script( 'speedguardsearch', plugin_dir_url( __FILE__ ) . 'assets/js/speedguard-search.js', [ $this->plugin_name . '-awesompletejs' ], $this->version, true );
			wp_localize_script( 'speedguardsearch', 'speedguardsearch', [
				'search_url' => home_url( '/wp-json/speedguard/search?term=' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
			] );
		}
	}


	function body_classes_filter( $classes ) {
		if ( self::is_screen( 'settings,tests,dashboard' ) ) {
			if ( get_transient( 'speedguard_tests_count' ) < 1 ) {
				$classes = $classes . ' no-guarded-pages';
			}
		}
		if ( self::is_screen( 'tests' ) ) {
			$sg_test_type = SpeedGuard_Settings::global_test_type();
			if ( 'cwv' === $sg_test_type ) {
				$class = 'test-type-cwv';
			} elseif ( 'psi' === $sg_test_type ) {
				$class = 'test-type-psi';
			}
			$classes = $classes . ' ' . $class;
		}
		if ( self::is_screen( 'plugins' ) ) {
			if ( get_transient( 'speedguard-notice-activation' ) ) {
				$classes = $classes . ' speedguard-just-activated';
			}
		}

		return $classes;
	}

	function speedguard_admin_menu() {
		$this->main_page          = add_menu_page( __( 'SpeedGuard', 'speedguard' ), __( 'SpeedGuard', 'speedguard' ), 'manage_options', 'speedguard_tests', [
			'SpeedGuard_Tests',
			'tests_page',
		], 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IURPQ1RZUEUgc3ZnIFs8IUVOVElUWSBuc19mbG93cyAiaHR0cDovL25zLmFkb2JlLmNvbS9GbG93cy8xLjAvIj5dPjxzdmcgdmVyc2lvbj0iMS4yIiBiYXNlUHJvZmlsZT0idGlueSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeG1sbnM6YT0iaHR0cDovL25zLmFkb2JlLmNvbS9BZG9iZVNWR1ZpZXdlckV4dGVuc2lvbnMvMy4wLyIgeD0iMHB4IiB5PSIwcHgiIHdpZHRoPSI5MXB4IiBoZWlnaHQ9IjkxcHgiIHZpZXdCb3g9Ii0wLjUgLTAuNSA5MSA5MSIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PGRlZnM+PC9kZWZzPjxwYXRoIGZpbGw9IiM4Mjg3OEMiIGQ9Ik04NS42NDYsNDAuNjQ1Yy0yLjQwNCwwLTQuMzU1LDEuOTUyLTQuMzU1LDQuMzU1YzAsMjAuMDEzLTE2LjI3NywzNi4yOS0zNi4yOSwzNi4yOUMyNC45ODgsODEuMjksOC43MDksNjUuMDEzLDguNzA5LDQ1QzguNzA5LDI0Ljk4OCwyNC45ODgsOC43MDksNDUsOC43MDljMi40MDQsMCw0LjM1NC0xLjk1MSw0LjM1NC00LjM1NFM0Ny40MDQsMCw0NSwwQzIwLjE4NywwLDAsMjAuMTg3LDAsNDVjMCwyNC44MTQsMjAuMTg3LDQ1LDQ1LDQ1YzI0LjgxNCwwLDQ1LTIwLjE4Niw0NS00NUM5MCw0Mi41OTcsODguMDQ5LDQwLjY0NSw4NS42NDYsNDAuNjQ1eiIvPjxwYXRoIGZpbGw9IiM4Mjg3OEMiIGQ9Ik00Ny4zMiwzMC42MjRjLTEuMjM2LDEuODA1LTEuOTIzLDMuODA5LTIuMzkzLDUuNjc1Yy00Ljc3NiwwLjA0MS04LjYzNywzLjkyLTguNjM3LDguNzAxYzAsNC44MDcsMy45MDIsOC43MSw4LjcwOSw4LjcxYzQuODA3LDAsOC43MS0zLjkwMyw4LjcxLTguNzFjMC0xLjE1OC0wLjIzOC0yLjI1OS0wLjY0OC0zLjI3MmMxLjU0My0xLjE0OSwzLjEyOC0yLjU1NSw0LjMyNC00LjM5NmMxLjI5MS0yLjA4MywxLjkyNS00LjgwOCwzLjA5NC03LjE3N2MxLjExOS0yLjM5OCwyLjI4NC00Ljc3MSwzLjIzNi03LjA3OGMxLjAwNi0yLjI3OSwxLjg3Ny00LjQ1LDIuNjMxLTYuMzA5YzEuNDg3LTMuNzI1LDIuMzYxLTYuMjg2LDIuMzYxLTYuMjg2YzAuMDY3LTAuMTk3LDAuMDMyLTAuNDI0LTAuMTE2LTAuNTkyYy0wLjIyMS0wLjI1LTAuNjAyLTAuMjczLTAuODQ4LTAuMDU2YzAsMC0yLjAyNiwxLjc5NC00Ljg5Nyw0LjYwMmMtMS40MjMsMS40MDgtMy4wOTIsMy4wNTItNC44MTEsNC44NTRjLTEuNzY3LDEuNzY5LTMuNTA0LDMuNzU3LTUuMjkxLDUuNzEzQzUxLjAxOSwyNi45OTQsNDguNzQ4LDI4LjYyNiw0Ny4zMiwzMC42MjR6Ii8+PC9zdmc+', '81' );
		$this->tests_page_hook    = add_submenu_page( 'speedguard_tests', __( 'Speed Tests', 'speedguard' ), __( 'Speed Tests', 'speedguard' ), 'manage_options', 'speedguard_tests' );
		$this->settings_page_hook = add_submenu_page( 'speedguard_tests', __( 'Settings', 'speedguard' ), __( 'Settings', 'speedguard' ), 'manage_options', 'speedguard_settings', [
			'SpeedGuard_Settings',
			'settings_page_function',
		] );
	}

	function app_output_buffer() {
		ob_start();
	}
}