<?php

class Voce_GA_Post_Views {

	CONST VERSION = 0.5;
	CONST SERVICE_EMAIL_ACCOUNT = '141819473776@developer.gserviceaccount.com';
	CONST SERVICE_CLIENT_ID = '141819473776.apps.googleusercontent.com';
	CONST API_CONTEXT = 'https://www.googleapis.com/auth/analytics.readonly';
	CONST PRIVATE_KEY_DIR = '/privatekey.p12';

	private static $profile_id;
	private static $error_msg;
	private static $query_timespan;
	private static $is_enabled;

	function init() {

		require_once( __DIR__ . '/google-api-php-client/src/Google_Client.php');
		require_once( __DIR__ . '/google-api-php-client/src/contrib/Google_AnalyticsService.php');

		if ( class_exists( 'Voce_Settings_Api' ) ) {

			self::get_settings();

			if ( is_admin() ) {
				self::admin_settings();
				wp_enqueue_script( 'ajax-refresh-views', plugins_url( '/js/postview-refresh.js', __FILE__ ) );
				wp_enqueue_style( 'post-views-style', plugins_url( '/css/style.css', __FILE__ ) );
			}
			
			if ( self::$is_enabled && self::$profile_id ) {
				add_action( 'ga_post_views_renew', array( __CLASS__, 'query_google_api' ) );
				add_action( 'wp', array( __CLASS__, 'setup_cron' ) );
			} else {
				wp_clear_scheduled_hook( 'ga_post_views_renew' );
			}
		}
	}

	function setup_cron() {
		if ( !wp_next_scheduled( 'ga_post_views_renew' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'ga_post_views_renew' );
		}
	}

	function check_enabled() {

		$voce_settings = Voce_Settings_API::GetInstance();
		$enabled = $voce_settings->get_setting( 'page_views_enable', 'google_analytics_page_views', false );
		if ( $enabled ) {
			return true;
		} else {
			return false;
		}
	}

	function admin_settings() {

		$voce_settings = Voce_Settings_API::GetInstance();
		$voce_settings->add_page( 'Page View Settings', 'Page Views', 'analytics-settings', 'manage_options', 'Settings for Google Analytics Page Views' )
			->add_group( 'Basic Settings and Options', 'google_analytics_page_views' )
			->add_setting( 'Google Analytics Profile ID', 'ga_profile_id', array(
				'description' => 'More information <a href="https://developers.google.com/analytics/devguides/reporting/core/v3/#user_reports">here.</a>'
			) )->group
			->add_setting( 'Timespan (days)', 'ga_query_timespan', array(
				'default_value' => '30',
				'description' => 'Days prior to now to query.'
			) )->group
			->add_setting( 'Enable Page Views', 'page_views_enable', array(
				'display_callback' => 'vs_display_checkbox',
				'description' => 'Disabling will remove widgets and stop auto queries.'
			) );
		if ( self::$profile_id ) {
			add_action( 'admin_init', function() {
					add_settings_field( 'ga_pageviews_admin_refresh_btn', 'Refresh Page Views', array( __CLASS__, 'vs_display_button' ), 'analytics-settings-page', 'google_analytics_page_views' );
				} );
			add_action( 'wp_ajax_refresh_post_views', array( __CLASS__, 'ajax_refresh' ) );
		} elseif ( !self::$profile_id && self::$is_enabled ) {
			$message = 'Page Views is enabled but no Analytics profile ID is set. Please set the profile ID or disable Page Views.';
			self::$error_msg = $message;
			add_action( 'admin_notices', array( __CLASS__, 'show_error' ) );
		}

		add_action( 'update_option_google_analytics_page_views', array( __CLASS__, 'query_on_update' ), 10, 2 );
	}

	function vs_display_button( $args ) {
		?>
		<input type="button" id="analytics-settings-page-google_analytics_page_views-ga_manual_refresh" value="Refresh" />
		<?php

	}

	function query_on_update( $old, $new ) {

		if ( self::$is_enabled && $new[ 'page_views_enable' ] === 'on' ) {
			self::$profile_id = $new[ 'ga_profile_id' ];
			self::$query_timespan = $new[ 'ga_query_timespan' ];
			if ( !self::query_google_api() ) {
				$voce_settings = Voce_Settings_API::GetInstance();
				$voce_settings->add_page( 'Page View Settings', 'Page Views', 'analytics-settings', 'manage_options', 'Settings for Google Analytics Page Views' )
					->add_group( 'Google Analytics Information', 'google_analytics_page_views' )
					->add_error( '404', 'Error Authenticating with Google, Please check your profile ID.' );
			}
		}
	}

	function show_error() {
		echo '<div id="message" class="error">';
		echo "<p><strong>" . self::$error_msg . "</strong></p></div>";
	}

	function ajax_refresh() {

		if ( self::query_google_api() ) {
			echo 'Successfully updated view counts.';
		} else {
			echo 'There was an error updating.';
		}

		die();
	}

	function get_settings() {

		$voce_settings = Voce_Settings_API::GetInstance();
		self::$profile_id = $voce_settings->get_setting( 'ga_profile_id', 'google_analytics_page_views', false );
		self::$query_timespan = $voce_settings->get_setting( 'ga_query_timespan', 'google_analytics_page_views', false );
		self::$is_enabled = $voce_settings->get_setting( 'page_views_enable', 'google_analytics_page_views', false );
	}

	function setup_widgets() {
		add_action( 'widgets_init', function() {
				return register_widget( 'Popular_Posts_Widget' );
			} );
		add_action( 'wp_dashboard_setup', array( 'Popular_Posts_Widget', 'pageviews_dashboard' ) );
	}

	function query_google_api() {

		$client = new Google_Client();
		$client->setApplicationName( 'Voce Google Analytics Plugin' );
		$client->setAssertionCredentials(
			new Google_AssertionCredentials(
				self::SERVICE_EMAIL_ACCOUNT,
				array( self::API_CONTEXT ),
				file_get_contents( __DIR__ . self::PRIVATE_KEY_DIR )
		) );
		$client->setClientId( self::SERVICE_CLIENT_ID );
		$client->setAccessType( 'offline_access' );

		$optParams = array(
			'dimensions' => 'ga:pagePath',
			'max-results' => '50' );
		$ga_profile_id = self::$profile_id;

		$date = new DateTime( the_date() );
		$timespan = (intval( strip_tags( self::$query_timespan ) )) ? intval( strip_tags( self::$query_timespan ) ) : '30';
		$interval = DateInterval::createFromDateString( $timespan . ' days' );
		$date = date_sub( $date, $interval );
		$query_start = date( 'Y-m-d', $date->getTimestamp() );
		$query_end = date( 'Y-m-d' );

		$service = new Google_AnalyticsService( $client );
		try {
			$results = $service->data_ga->get( 'ga:' . $ga_profile_id, $query_start, $query_end, 'ga:pageViews, ga:uniquePageviews', $optParams );
			self::process_query( $results );
			return true;
		} catch ( Exception $e ) {

			return false;
		}
	}

	function process_query( $data ) {

		if ( intval( $data[ 'totalResults' ] ) > 0 ) {
			$rows = $data[ 'rows' ];
			$columnHeaders = $data[ 'columnHeaders' ];
			$columns;
			foreach ( $columnHeaders as $column ) {
				$columns[ ] = $column[ 'name' ];
			}
			$posts = array( );
			foreach ( $rows as $row ) {
				$post = array( );
				$id = url_to_postid( site_url() . $row[ array_search( 'ga:pagePath', $columns ) ] );
				if ( $id ) {
					$post[ 'id' ] = $id;
					$post[ 'views' ] = $row[ array_search( 'ga:pageviews', $columns ) ];
					$post[ 'uniqueViews' ] = $row[ array_search( 'ga:uniquePageviews', $columns ) ];
					$posts[ ] = $post;
				}
			}
			//print '<h2>Analytics Result:</h2><pre>' . print_r($posts, true) . '</pre>';
			update_option( 'google-analytics-post-views', $posts );
		}
	}

}

add_action( 'init', array( 'Voce_GA_Post_Views', 'init' ) );