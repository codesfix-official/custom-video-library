<?php
/**
 * Audio Proxy — optional server-side stream proxy for external audio.
 *
 * @package CustomVideoLibrary
 */

namespace CustomVideoLibrary;

// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles server-side streaming proxy for cloud audio files.
 */
class Audio_Proxy {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_proxy' ) );
	}

	/**
	 * Register the custom query variable.
	 *
	 * @param array $vars Existing query vars.
	 *
	 * @return array
	 */
	public function register_query_var( $vars ) {
		$vars[] = 'cvl_audio';
		return $vars;
	}

	/**
	 * Intercept the request if our query var is present.
	 *
	 * @return void
	 */
	public function maybe_proxy() {
		$post_id = (int) get_query_var( 'cvl_audio' );
		if ( $post_id < 1 ) {
			return;
		}
		$this->stream_audio( $post_id );
	}

	/**
	 * Validate access and stream the audio from the stored cloud URL.
	 *
	 * @param int $post_id Video post ID.
	 *
	 * @return void
	 */
	private function stream_audio( $post_id ) {
		// Verify nonce.
		$nonce = isset( $_GET['_cvl_nonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['_cvl_nonce'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		if ( ! wp_verify_nonce( $nonce, 'cvl_audio_' . $post_id ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Forbidden', 'custom-video-library' ), 403 );
		}

		// Ensure the post exists and is the right type.
		if ( 'video' !== get_post_type( $post_id ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'Not found', 'custom-video-library' ), 404 );
		}

		// Check access — free content is always accessible to everyone.
		$is_free = cvl_normalize_flag( get_post_meta( $post_id, '_is_free_video', true ) );
		$user_id = get_current_user_id();

		if ( ! $is_free && ! ( $user_id && Capabilities::can_user_access_video( $user_id, $post_id ) ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Access denied', 'custom-video-library' ), 403 );
		}

		// Get the stored URL (already transformed by cvl_transform_cloud_audio_url).
		$url = cvl_get_media_source_url( $post_id );

		if ( '' === $url ) {
			status_header( 404 );
			wp_die( esc_html__( 'No audio source configured', 'custom-video-library' ), 404 );
		}

		// SSRF protection: only proxy Bunny domains.
		$host          = (string) wp_parse_url( $url, PHP_URL_HOST );

		$is_allowed = (bool) preg_match( '/(^|\.)bunny\.net$/i', $host )
			|| (bool) preg_match( '/(^|\.)b-cdn\.net$/i', $host )
			|| (bool) preg_match( '/(^|\.)bunnycdn\.com$/i', $host )
			|| (bool) preg_match( '/(^|\.)mediadelivery\.net$/i', $host );

		if ( ! $is_allowed ) {
			status_header( 400 );
			wp_die( esc_html__( 'Unsupported audio source domain', 'custom-video-library' ), 400 );
		}

		$this->stream_from_url( $url );
	}

	/**
	 * Stream bytes from a remote URL to the browser, forwarding Range headers
	 * so seeking works correctly in the audio player.
	 *
	 * @param string $url Fully-qualified remote URL.
	 *
	 * @return void
	 */
	private function stream_from_url( $url ) {
		// Validate and forward the Range header (for audio seeking).
		$range = '';
		if ( ! empty( $_SERVER['HTTP_RANGE'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) );
			// Only allow the standard "bytes=start-end" format to prevent header injection.
			if ( preg_match( '/^bytes=\d*-\d*$/', $raw ) ) {
				$range = $raw;
			}
		}

		$http_headers = "User-Agent: Mozilla/5.0 (compatible; WordPress)\r\n";
		if ( '' !== $range ) {
			$http_headers .= 'Range: ' . $range . "\r\n";
		}

		$context = stream_context_create(
			array(
				'http' => array(
					'method'          => 'GET',
					'header'          => $http_headers,
					'follow_location' => 1,
					'max_redirects'   => 8,
					'timeout'         => 60,
					'ignore_errors'   => true,
				),
				'ssl'  => array(
					'verify_peer' => true,
				),
			)
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = @fopen( $url, 'rb', false, $context );

		if ( false === $handle ) {
			status_header( 502 );
			wp_die( esc_html__( 'Could not connect to audio source', 'custom-video-library' ), 502 );
		}

		// Read response metadata to forward status code and headers.
		$meta             = stream_get_meta_data( $handle );
		$response_headers = isset( $meta['wrapper_data'] ) && is_array( $meta['wrapper_data'] )
			? $meta['wrapper_data']
			: array();

		// Determine HTTP status code from the last status line (after redirects).
		$status_code = 200;
		foreach ( $response_headers as $h ) {
			if ( preg_match( '#^HTTP/\S+ (\d+)#', $h, $m ) ) {
				$status_code = (int) $m[1];
			}
		}
		status_header( $status_code );

		// Forward audio-relevant response headers.
		$forward_headers = array( 'content-type', 'content-length', 'content-range', 'accept-ranges' );
		foreach ( $response_headers as $h ) {
			if ( false === strpos( $h, ':' ) ) {
				continue;
			}
			$parts      = explode( ':', $h, 2 );
			$name_lower = strtolower( trim( $parts[0] ) );
			if ( in_array( $name_lower, $forward_headers, true ) ) {
				header( trim( $parts[0] ) . ':' . $parts[1], true );
			}
		}

		// Prevent browser and proxy caching of proxied content.
		header( 'Cache-Control: no-store, no-cache', true );
		// Allow audio element on same origin to load the resource.
		header( 'Access-Control-Allow-Origin: ' . esc_url_raw( home_url() ), true );

		// Flush all output buffers before streaming raw bytes.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Stream in 64 KB chunks.
		while ( ! feof( $handle ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			echo fread( $handle, 65536 ); // 64 KB
			flush();
		}

		fclose( $handle );
		exit;
	}
}
