<?php
/**
 * Database Schema and Management
 *
 * @package CustomVideoLibrary
 */

namespace CustomVideoLibrary;

/**
 * Class for managing database schema and operations.
 */
class Database {

	/**
	 * Initialize the database class.
	 *
	 * @return void
	 */
	public function init() {
		// Hook into plugin activation for table creation.
		// Tables are created in the Plugin::activate() method.
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Video access logs table.
		$video_access_logs_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cvl_video_access_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			video_id bigint(20) unsigned NOT NULL,
			access_type varchar(50) NOT NULL,
			access_timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			duration_watched int(11) DEFAULT 0,
			completed tinyint(1) DEFAULT 0,
			ip_address varchar(45),
			user_agent varchar(255),
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY video_id (video_id),
			KEY access_timestamp (access_timestamp)
		) $charset_collate;";

		// Video metadata table.
		$video_metadata_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cvl_video_metadata (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			video_id bigint(20) unsigned NOT NULL UNIQUE,
			duration int(11) DEFAULT 0,
			file_size bigint(20) DEFAULT 0,
			video_format varchar(20),
			resolution varchar(20),
			bitrate varchar(20),
			upload_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			view_count bigint(20) DEFAULT 0,
			rating_count int(11) DEFAULT 0,
			rating_sum int(11) DEFAULT 0,
			PRIMARY KEY  (id),
			KEY video_id (video_id),
			KEY upload_date (upload_date)
		) $charset_collate;";

		// Video transactions table (for tracking purchases).
		$video_transactions_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cvl_video_transactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			video_id bigint(20) unsigned NOT NULL,
			transaction_type varchar(50) NOT NULL,
			wc_order_id bigint(20) unsigned,
			wc_subscription_id bigint(20) unsigned,
			wc_product_id bigint(20) unsigned,
			amount decimal(10,2),
			currency varchar(3),
			status varchar(50) NOT NULL DEFAULT 'pending',
			transaction_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expiration_date datetime,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY video_id (video_id),
			KEY transaction_type (transaction_type),
			KEY status (status)
		) $charset_collate;";

		// Execute SQL statements.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// User progress table — stores only the latest playback position per user/video.
		// Uses a composite primary key so $wpdb->replace() acts as an upsert.
		$user_progress_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cvl_user_progress (
			user_id bigint(20) unsigned NOT NULL,
			video_id bigint(20) unsigned NOT NULL,
			current_seconds int(11) unsigned NOT NULL DEFAULT 0,
			completed tinyint(1) NOT NULL DEFAULT 0,
			last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (user_id, video_id)
		) $charset_collate;";

		dbDelta( $video_access_logs_sql );
		dbDelta( $video_metadata_sql );
		dbDelta( $video_transactions_sql );
		dbDelta( $user_progress_sql );

		// Update database version.
		update_option( 'cvl_database_version', CVL_VERSION );
	}

	/**
	 * Log video access event.
	 *
	 * @param int    $user_id The user ID.
	 * @param int    $video_id The video post ID.
	 * @param string $access_type The type of access (view, stream, etc.).
	 * @param array  $data Additional data to log.
	 *
	 * @return int|false The insert ID or false on failure.
	 */
	public static function log_video_access( $user_id, $video_id, $access_type = 'view', $data = array() ) {
		global $wpdb;

		$insert_data = array(
			'user_id'        => (int) $user_id,
			'video_id'       => (int) $video_id,
			'access_type'    => sanitize_text_field( $access_type ),
			'access_timestamp' => current_time( 'mysql' ),
			'ip_address'     => cvl_get_user_ip(),
			'user_agent'     => sanitize_text_field( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ),
		);

		// Add optional data.
		if ( isset( $data['duration_watched'] ) ) {
			$insert_data['duration_watched'] = (int) $data['duration_watched'];
		}
		if ( isset( $data['completed'] ) ) {
			$insert_data['completed'] = (int) $data['completed'];
		}

		return $wpdb->insert(
			$wpdb->prefix . 'cvl_video_access_logs',
			$insert_data,
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get video access logs for a user.
	 *
	 * @param int $user_id The user ID.
	 * @param int $limit Number of results to return.
	 * @param int $offset Number of results to skip.
	 *
	 * @return array Array of access log objects.
	 */
	public static function get_user_access_logs( $user_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cvl_video_access_logs 
				WHERE user_id = %d 
				ORDER BY access_timestamp DESC 
				LIMIT %d OFFSET %d",
				(int) $user_id,
				(int) $limit,
				(int) $offset
			)
		);
	}

	/**
	 * Get video statistics.
	 *
	 * @param int $video_id The video post ID.
	 *
	 * @return array Video statistics.
	 */
	public static function get_video_statistics( $video_id ) {
		global $wpdb;

		$stats = array(
			'total_views'    => 0,
			'unique_viewers' => 0,
			'total_duration' => 0,
			'completion_rate' => 0,
		);

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as total_views,
					COUNT(DISTINCT user_id) as unique_viewers,
					SUM(duration_watched) as total_duration,
					SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100 as completion_rate
				FROM {$wpdb->prefix}cvl_video_access_logs
				WHERE video_id = %d",
				(int) $video_id
			)
		);

		if ( ! empty( $results ) ) {
			$result = $results[0];
			$stats['total_views']    = (int) $result->total_views;
			$stats['unique_viewers'] = (int) $result->unique_viewers;
			$stats['total_duration'] = (int) $result->total_duration;
			$stats['completion_rate'] = (float) $result->completion_rate;
		}

		return $stats;
	}

	/**
	 * Save user progress snapshot.
	 *
	 * @param int $user_id User ID.
	 * @param int $video_id Video ID.
	 * @param int $current_seconds Current watched seconds.
	 * @param int $completed Completion state (0/1).
	 *
	 * @return int|false
	 */
	public static function save_user_progress( $user_id, $video_id, $current_seconds, $completed ) {
		global $wpdb;

		$user_id         = (int) $user_id;
		$video_id        = (int) $video_id;
		$current_seconds = max( 0, (int) $current_seconds );
		$completed       = $completed ? 1 : 0;

		// Lazy migration: create tables if this install pre-dates the progress table.
		if ( get_option( 'cvl_database_version' ) !== CVL_VERSION ) {
			$db = new self();
			$db->create_tables();
		}

		$ip_address = cvl_get_user_ip();
		if ( strlen( $ip_address ) > 45 ) {
			$ip_address = substr( $ip_address, 0, 45 );
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$user_agent = sanitize_text_field( $user_agent );
		if ( strlen( $user_agent ) > 255 ) {
			$user_agent = substr( $user_agent, 0, 255 );
		}

		// Append an analytics event to the access log.
		$wpdb->insert(
			$wpdb->prefix . 'cvl_video_access_logs',
			array(
				'user_id'          => $user_id,
				'video_id'         => $video_id,
				'access_type'      => 'progress',
				'access_timestamp' => current_time( 'mysql' ),
				'duration_watched' => $current_seconds,
				'completed'        => $completed,
				'ip_address'       => $ip_address,
				'user_agent'       => $user_agent,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		// Upsert the latest progress snapshot into the dedicated progress table.
		// $wpdb->replace() maps to REPLACE INTO which deletes + reinserts on PK collision,
		// keeping only one row per (user_id, video_id) and avoiding unbounded row growth.
		return $wpdb->replace(
			$wpdb->prefix . 'cvl_user_progress',
			array(
				'user_id'         => $user_id,
				'video_id'        => $video_id,
				'current_seconds' => $current_seconds,
				'completed'       => $completed,
				'last_updated'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Get last known progress per video for a user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	public static function get_user_progress_map( $user_id ) {
		global $wpdb;

		// Read from the dedicated progress table — one row per video, no GROUP BY needed.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT video_id, current_seconds, completed
				FROM {$wpdb->prefix}cvl_user_progress
				WHERE user_id = %d",
				(int) $user_id
			)
		);

		$map = array();
		foreach ( $rows as $row ) {
			$video_id = (int) $row->video_id;
			$watched  = (int) $row->current_seconds;
			$duration = (int) get_post_meta( $video_id, '_cvl_duration', true );

			$percent = 0;
			if ( $duration > 0 ) {
				$percent = (int) floor( min( 100, ( $watched / $duration ) * 100 ) );
			} elseif ( (int) $row->completed > 0 ) {
				$percent = 100;
			}

			$map[ $video_id ] = array(
				'duration_watched' => $watched,
				'completed'        => (int) $row->completed,
				'percent'          => $percent,
			);
		}

		return $map;
	}
}
