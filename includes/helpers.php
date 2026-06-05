<?php
/**
 * Helper Functions for Custom Video Library
 *
 * @package CustomVideoLibrary
 */

// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the user's IP address.
 *
 * @return string The user's IP address.
 */
function cvl_get_user_ip() {
	if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
	} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
	} else {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	}

	// Validate IP address.
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/**
 * Normalize checkbox/radio style values to 0/1.
 *
 * @param mixed $value Raw value.
 *
 * @return int
 */
function cvl_normalize_flag( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 1 : 0;
	}

	if ( is_numeric( $value ) ) {
		return ( (int) $value ) > 0 ? 1 : 0;
	}

	if ( is_string( $value ) ) {
		$normalized = strtolower( trim( $value ) );
		return in_array( $normalized, array( '1', 'yes', 'true', 'on' ), true ) ? 1 : 0;
	}

	return 0;
}

/**
 * Normalize mixed meta values to a unique list of positive integer IDs.
 *
 * Supports scalars, arrays, and object values (e.g. ACF Post Object field).
 *
 * @param mixed $value Raw meta value.
 *
 * @return array<int>
 */
function cvl_normalize_meta_id_list( $value ) {
	if ( null === $value || '' === $value || false === $value ) {
		return array();
	}

	$items = is_array( $value ) ? $value : array( $value );
	$ids   = array();

	foreach ( $items as $item ) {
		if ( is_string( $item ) && preg_match( '/^field_[a-z0-9]+$/i', trim( $item ) ) ) {
			continue;
		}

		if ( is_object( $item ) ) {
			if ( isset( $item->ID ) ) {
				$item = $item->ID;
			} elseif ( isset( $item->id ) ) {
				$item = $item->id;
			} else {
				continue;
			}
		}

		$id = absint( $item );
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}

	return array_values( array_unique( $ids ) );
}

/**
 * Normalize membership plan meta to IDs and/or slugs.
 *
 * Supports scalars, arrays, and object values (e.g. ACF Post Object/Taxonomy).
 *
 * @param mixed $value Raw meta value.
 *
 * @return array<int|string>
 */
function cvl_normalize_membership_plan_values( $value ) {
	if ( null === $value || '' === $value || false === $value ) {
		return array();
	}

	$items = is_array( $value ) ? $value : array( $value );
	$out   = array();

	foreach ( $items as $item ) {
		if ( is_string( $item ) && preg_match( '/^field_[a-z0-9]+$/i', trim( $item ) ) ) {
			continue;
		}

		if ( is_object( $item ) ) {
			if ( isset( $item->ID ) ) {
				$item = $item->ID;
			} elseif ( isset( $item->id ) ) {
				$item = $item->id;
			} elseif ( isset( $item->post_name ) ) {
				$item = $item->post_name;
			} elseif ( isset( $item->slug ) ) {
				$item = $item->slug;
			} else {
				continue;
			}
		}

		if ( is_numeric( $item ) ) {
			$id = absint( $item );
			if ( $id > 0 ) {
				$out[] = $id;
			}
			continue;
		}

		$slug = sanitize_key( (string) $item );
		if ( '' !== $slug ) {
			$out[] = $slug;
		}
	}

	return array_values( array_unique( $out ) );
}

/**
 * Collect raw meta values from both underscored and non-underscored keys.
 *
 * @param int   $video_id Video post ID.
 * @param array $meta_keys Meta keys to read.
 *
 * @return array<int, mixed>
 */
function cvl_collect_meta_values( $video_id, $meta_keys ) {
	$video_id = (int) $video_id;
	$values   = array();

	foreach ( $meta_keys as $meta_key ) {
		$rows = get_post_meta( $video_id, (string) $meta_key, false );
		if ( is_array( $rows ) && ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$values[] = $row;
			}
		}

		$single = get_post_meta( $video_id, (string) $meta_key, true );
		if ( '' !== $single && null !== $single && false !== $single ) {
			$values[] = $single;
		}
	}

	return $values;
}

/**
 * Get the linked WooCommerce product ID for a video.
 *
 * @param int $video_id Video post ID.
 *
 * @return int
 */
function cvl_get_video_product_id( $video_id ) {
	$values = cvl_collect_meta_values( (int) $video_id, array( '_wc_product_id', 'wc_product_id' ) );
	$ids    = cvl_normalize_meta_id_list( $values );

	return ! empty( $ids ) ? (int) $ids[0] : 0;
}

/**
 * Get normalized subscription product IDs linked to a media post.
 *
 * @param int $video_id Video post ID.
 *
 * @return array<int>
 */
function cvl_get_video_subscription_ids( $video_id ) {
	$video_id = (int) $video_id;
	$values   = cvl_collect_meta_values( $video_id, array( '_wc_subscription_id', 'wc_subscription_id' ) );

	return cvl_normalize_meta_id_list( $values );
}

/**
 * Get normalized membership plan identifiers linked to a media post.
 *
 * @param int $video_id Video post ID.
 *
 * @return array<int|string>
 */
function cvl_get_video_membership_plans( $video_id ) {
	$video_id = (int) $video_id;
	$values   = cvl_collect_meta_values( $video_id, array( '_woo_membership_plan', 'woo_membership_plan' ) );

	return cvl_normalize_membership_plan_values( $values );
}

/**
 * Determine if a video is marked as free.
 *
 * @param int $video_id Video post ID.
 *
 * @return bool
 */
function cvl_is_video_free( $video_id ) {
	$video_id = (int) $video_id;
	$raw = get_post_meta( $video_id, '_is_free_video', true );
	return 1 === cvl_normalize_flag( $raw );
}

/**
 * Check whether a video has any paid gate configured.
 *
 * @param int $video_id Video post ID.
 *
 * @return bool
 */
function cvl_video_has_paid_gate( $video_id ) {
	$video_id = (int) $video_id;

	if ( cvl_get_video_product_id( $video_id ) > 0 ) {
		return true;
	}

	if ( ! empty( cvl_get_video_subscription_ids( $video_id ) ) ) {
		return true;
	}

	if ( ! empty( cvl_get_video_membership_plans( $video_id ) ) ) {
		return true;
	}

	return false;
}

/**
 * Check if a user has purchased a specific WooCommerce product.
 *
 * @param int $user_id The user ID.
 * @param int $product_id The WooCommerce product ID.
 *
 * @return bool True if user has purchased, false otherwise.
 */
function cvl_user_has_purchased_product( $user_id, $product_id ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return false;
	}

	$product_id = (int) $product_id;
	$user_id    = (int) $user_id;

	// Cache purchased product IDs per user for the lifetime of the request to avoid
	// calling wc_get_orders() once per video in loops (e.g. private library).
	static $purchase_cache = array();

	if ( ! isset( $purchase_cache[ $user_id ] ) ) {
		$purchase_cache[ $user_id ] = array();

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'wc-completed', 'wc-processing' ),
				'limit'       => -1,
			)
		);

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$pid = (int) $item->get_product_id();
				$vid = (int) $item->get_variation_id();
				if ( $pid > 0 ) {
					$purchase_cache[ $user_id ][ $pid ] = true;
				}
				if ( $vid > 0 ) {
					$purchase_cache[ $user_id ][ $vid ] = true;
				}
			}
		}
	}

	return isset( $purchase_cache[ $user_id ][ $product_id ] );
}

/**
 * Check if a user has an active subscription for a specific product.
 *
 * @param int $user_id The user ID.
 * @param int $subscription_product_id The WooCommerce subscription product ID.
 *
 * @return bool True if user has active subscription, false otherwise.
 */
function cvl_user_has_active_subscription( $user_id, $subscription_product_id ) {
	if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return false;
	}

	$subscription_product_id = (int) $subscription_product_id;
	$user_id                 = (int) $user_id;

	// Cache active subscription product IDs per user for the lifetime of the request.
	static $subscription_cache = array();

	if ( ! isset( $subscription_cache[ $user_id ] ) ) {
		$subscription_cache[ $user_id ] = array();

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			if ( 'active' !== $subscription->get_status() && 'on-hold' !== $subscription->get_status() ) {
				continue;
			}

			foreach ( $subscription->get_items() as $item ) {
				$pid = (int) $item->get_product_id();
				$vid = (int) $item->get_variation_id();
				if ( $pid > 0 ) {
					$subscription_cache[ $user_id ][ $pid ] = true;
				}
				if ( $vid > 0 ) {
					$subscription_cache[ $user_id ][ $vid ] = true;
				}
			}
		}
	}

	return isset( $subscription_cache[ $user_id ][ $subscription_product_id ] );
}
/**
 * Check whether a user has at least one active/on-hold WooCommerce subscription.
 *
 * @param int $user_id User ID.
 *
 * @return bool
 */
function cvl_user_has_any_active_subscription( $user_id ) {
	if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
		return false;
	}

	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}

	$subscriptions = wcs_get_users_subscriptions( $user_id );
	if ( empty( $subscriptions ) || ! is_array( $subscriptions ) ) {
		return false;
	}

	foreach ( $subscriptions as $subscription ) {
		$status = $subscription->get_status();
		if ( 'active' === $status || 'on-hold' === $status ) {
			return true;
		}
	}

	return false;
}
/**
 * Get video metadata.
 *
 * @param int $video_id The video post ID.
 *
 * @return array Video metadata array.
 */
function cvl_get_video_metadata( $video_id ) {
	$video_id = (int) $video_id;
	$subscription_ids = cvl_get_video_subscription_ids( $video_id );
	$membership_plans = cvl_get_video_membership_plans( $video_id );

	$metadata = array(
		'media_type'      => cvl_get_media_type( $video_id ),
		'duration'        => (int) get_post_meta( $video_id, '_cvl_duration', true ),
		'video_url'       => get_post_meta( $video_id, '_cvl_video_url', true ),
		'preview_url'     => get_post_meta( $video_id, '_cvl_preview_url', true ),
		'age_rating'      => get_post_meta( $video_id, '_cvl_age_rating', true ),
		'release_year'    => (int) get_post_meta( $video_id, '_cvl_release_year', true ),
		'is_free'         => cvl_is_video_free( $video_id ),
		'wc_product_id'   => cvl_get_video_product_id( $video_id ),
		'wc_subscription_id' => ! empty( $subscription_ids ) ? (int) $subscription_ids[0] : 0,
		'wc_subscription_ids' => $subscription_ids,
		'membership_plan' => ! empty( $membership_plans ) ? $membership_plans[0] : '',
		'membership_plans' => $membership_plans,
	);

	return array_filter( $metadata, function( $value ) {
		return '' !== $value && 0 !== $value;
	} );
}

/**
 * Get media type for a library item.
 *
 * @param int $video_id Video post ID.
 *
 * @return string video|audio
 */
/**
 * Read a URL-type post meta key, skipping ACF field key references.
 *
 * ACF stores the field's value at `field_name` and an internal reference at
 * `_field_name`. The reference looks like `field_6abc1234` and is never a
 * valid URL, so we check the value starts with `http` before returning it.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key to read.
 *
 * @return string URL or empty string.
 */
function cvl_get_url_meta( $post_id, $meta_key ) {
	$val = trim( (string) get_post_meta( (int) $post_id, $meta_key, true ) );
	if ( '' !== $val && ( 0 === strpos( $val, 'http://' ) || 0 === strpos( $val, 'https://' ) ) ) {
		return $val;
	}
	return '';
}

function cvl_get_media_type( $video_id ) {
	$video_id = (int) $video_id;

	// ACF value is stored at the non-prefixed key; the _-prefixed key holds the
	// internal ACF field reference string (e.g. field_6abc1234) — not usable.
	$raw_media_type = trim( (string) get_post_meta( $video_id, '_cvl_media_type', true ) );
	if ( '' === $raw_media_type ) {
		$raw_media_type = trim( (string) get_post_meta( $video_id, 'cvl_media_type', true ) );
	}
	$media_type = sanitize_key( $raw_media_type );

	if ( in_array( $media_type, array( 'video', 'audio' ), true ) ) {
		return $media_type;
	}

	// Fallback: detect audio from any URL meta containing an audio extension.
	$url_keys = array( '_cvl_video_url', 'cvl_video_url', 'cvl_audio_url', '_cvl_audio_url' );
	foreach ( $url_keys as $key ) {
		$url = cvl_get_url_meta( $video_id, $key );
		if ( '' !== $url && preg_match( '/\.(mp3|m4a|aac|wav|ogg|oga|flac)(\?.*)?$/i', $url ) ) {
			return 'audio';
		}
	}

	return 'video';
}

/**
 * Return true if the given URL must be proxied server-side.
 *
 * Bunny-hosted audio is routed through proxy to bypass geographic restrictions.
 *
 * @param string $url URL to check.
 *
 * @return bool
 */
function cvl_audio_needs_proxy( $url ) {
	if ( ! is_string( $url ) || '' === trim( $url ) ) {
		return false;
	}

	$host = (string) wp_parse_url( $url, PHP_URL_HOST );

	// Proxy Bunny CDN URLs to bypass regional restrictions.
	return (bool) preg_match( '/(^|\.)bunny\.net$/i', $host )
		|| (bool) preg_match( '/(^|\.)b-cdn\.net$/i', $host )
		|| (bool) preg_match( '/(^|\.)bunnycdn\.com$/i', $host )
		|| (bool) preg_match( '/(^|\.)mediadelivery\.net$/i', $host );
}

/**
 * Build the server-side audio proxy URL for a post.
 *
 * @param int $post_id Video post ID.
 *
 * @return string
 */
function cvl_get_audio_proxy_url( $post_id ) {
	$post_id = (int) $post_id;

	return add_query_arg(
		array(
			'cvl_audio'  => $post_id,
			'_cvl_nonce' => wp_create_nonce( 'cvl_audio_' . $post_id ),
		),
		home_url( '/' )
	);
}

/**
 * Normalize external audio URL.
 *
 * @param string $url Raw URL as entered by the user.
 *
 * @return string Normalized URL.
 */
function cvl_transform_cloud_audio_url( $url ) {
	if ( ! is_string( $url ) || '' === trim( $url ) ) {
		return $url;
	}

	$url = trim( $url );

	$parsed = wp_parse_url( $url );
	if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
		return $url;
	}

	$path    = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
	$query   = isset( $parsed['query'] ) ? (string) $parsed['query'] : '';
	$port    = isset( $parsed['port'] ) ? ':' . (int) $parsed['port'] : '';
	$frag    = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';
	$user    = isset( $parsed['user'] ) ? $parsed['user'] : '';
	$pass    = isset( $parsed['pass'] ) ? ':' . $parsed['pass']  : '';
	$pass    = ( $user || $pass ) ? "$pass@" : '';

	// Encode each path segment so spaces/special chars in Bunny file names remain valid.
	if ( '' !== $path ) {
		$segments      = explode( '/', $path );
		$encoded_parts = array();
		foreach ( $segments as $segment ) {
			$encoded_parts[] = rawurlencode( rawurldecode( $segment ) );
		}
		$path = implode( '/', $encoded_parts );
		if ( '/' === substr( $parsed['path'], 0, 1 ) && '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}
	}

	$rebuilt = $parsed['scheme'] . '://' . $user . $pass . $parsed['host'] . $port . $path;
	if ( '' !== $query ) {
		$rebuilt .= '?' . $query;
	}

	return $rebuilt . $frag;
}

/**
 * Build a Bunny fallback URL for compacted file names.
 *
 * Example fallback target:
 * Apparat-Goodbye-Dark(Netflix)ThemeSong.mp3
 * -> Apparat%20-%20Goodbye%20-%20Dark%20(Netflix)%20Theme%20Song.mp3
 *
 * @param string $url Normalized URL.
 *
 * @return string Fallback URL or empty string when not applicable.
 */
function cvl_get_bunny_audio_fallback_url( $url ) {
	if ( ! is_string( $url ) || '' === trim( $url ) ) {
		return '';
	}

	$parsed = wp_parse_url( $url );
	if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
		return '';
	}

	$host = (string) $parsed['host'];
	$is_bunny_host = (bool) preg_match( '/(^|\.)bunny\.net$/i', $host )
		|| (bool) preg_match( '/(^|\.)b-cdn\.net$/i', $host )
		|| (bool) preg_match( '/(^|\.)bunnycdn\.com$/i', $host )
		|| (bool) preg_match( '/(^|\.)mediadelivery\.net$/i', $host );

	if ( ! $is_bunny_host ) {
		return '';
	}

	$path = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
	if ( '' === $path ) {
		return '';
	}

	$segments = explode( '/', $path );
	$raw_name = rawurldecode( (string) end( $segments ) );
	if ( '' === $raw_name || false !== strpos( $raw_name, ' ' ) ) {
		return '';
	}

	$dot = strrpos( $raw_name, '.' );
	if ( false === $dot ) {
		return '';
	}

	$name = substr( $raw_name, 0, $dot );
	$ext  = substr( $raw_name, $dot + 1 );
	if ( '' === $name || '' === $ext ) {
		return '';
	}

	$fallback_name = preg_replace( '/(?<=[a-z0-9\)])(?=[A-Z])/', ' ', $name );
	$fallback_name = str_replace( '-', ' - ', $fallback_name );
	$fallback_name = preg_replace( '/\s*\(\s*/', ' (', (string) $fallback_name );
	$fallback_name = preg_replace( '/\s*\)\s*/', ') ', (string) $fallback_name );
	$fallback_name = preg_replace( '/\s+/', ' ', (string) $fallback_name );
	$fallback_name = trim( (string) $fallback_name );

	if ( '' === $fallback_name || 0 === strcmp( $fallback_name, $name ) ) {
		return '';
	}

	$segments[ count( $segments ) - 1 ] = rawurlencode( $fallback_name . '.' . $ext );
	$fallback_path = implode( '/', $segments );

	$port  = isset( $parsed['port'] ) ? ':' . (int) $parsed['port'] : '';
	$query = isset( $parsed['query'] ) ? (string) $parsed['query'] : '';
	$frag  = isset( $parsed['fragment'] ) ? (string) $parsed['fragment'] : '';

	$fallback_url = $parsed['scheme'] . '://' . $host . $port . $fallback_path;
	if ( '' !== $query ) {
		$fallback_url .= '?' . $query;
	}
	if ( '' !== $frag ) {
		$fallback_url .= '#' . $frag;
	}

	return $fallback_url;
}

/**
 * Resolve the playable media URL from ACF/meta fields.
 *
 * @param int $video_id Video post ID.
 *
 * @return string
 */
function cvl_get_media_source_url( $video_id ) {
	$video_id   = (int) $video_id;
	$media_type = cvl_get_media_type( $video_id );

	if ( 'audio' === $media_type ) {
		$audio_source = sanitize_key( trim( (string) get_post_meta( $video_id, '_cvl_audio_source', true ) ) );
		if ( '' === $audio_source ) {
			$audio_source = sanitize_key( trim( (string) get_post_meta( $video_id, 'cvl_audio_source', true ) ) );
		}

		if ( 'upload' === $audio_source ) {
			// Uploaded file: ACF File field stores attachment ID at non-prefixed key.
			$audio_file = get_post_meta( $video_id, 'cvl_audio_file', true );
			if ( empty( $audio_file ) ) {
				$audio_file = get_post_meta( $video_id, '_cvl_audio_file', true );
			}

			if ( is_numeric( $audio_file ) && (int) $audio_file > 0 ) {
				$attachment_url = wp_get_attachment_url( (int) $audio_file );
				if ( $attachment_url ) {
					return (string) $attachment_url;
				}
			}

			$url = cvl_get_url_meta( $video_id, 'cvl_audio_file' );
			if ( '' !== $url ) {
				return $url;
			}
		}

		// External audio URL: check dedicated audio URL key first (ACF value key),
		// then the underscore variant, then fall back to the shared video_url key.
		// cvl_get_url_meta() skips ACF field key references (field_xxxxxxxx strings).
		$external_keys = array( 'cvl_audio_url', '_cvl_audio_url', 'cvl_video_url', '_cvl_video_url' );
		foreach ( $external_keys as $key ) {
			$url = cvl_get_url_meta( $video_id, $key );
			if ( '' !== $url ) {
				return cvl_transform_cloud_audio_url( $url );
			}
		}

		return '';
	}

	// Video: ACF value stored at non-prefixed key; prefixed key holds a field reference.
	$url = cvl_get_url_meta( $video_id, 'cvl_video_url' );
	if ( '' === $url ) {
		$url = cvl_get_url_meta( $video_id, '_cvl_video_url' );
	}
	return $url;
}

/**
 * Detect video provider for a given video.
 *
 * @param int $video_id Video post ID.
 *
 * @return string youtube|vimeo|self
 */
function cvl_get_video_provider( $video_id ) {
	$video_id = (int) $video_id;

	if ( 'audio' === cvl_get_media_type( $video_id ) ) {
		return 'self';
	}

	$provider = get_post_meta( (int) $video_id, '_cvl_video_provider', true );
	if ( in_array( $provider, array( 'youtube', 'vimeo', 'self' ), true ) ) {
		return $provider;
	}

	$youtube_value = trim( (string) get_post_meta( $video_id, '_cvl_youtube_id', true ) );
	if ( '' !== $youtube_value ) {
		return 'youtube';
	}

	$vimeo_value = trim( (string) get_post_meta( $video_id, '_cvl_vimeo_id', true ) );
	if ( '' !== $vimeo_value ) {
		return 'vimeo';
	}

	$url = cvl_get_media_source_url( (int) $video_id );
	if ( false !== strpos( $url, 'youtube.com' ) || false !== strpos( $url, 'youtu.be' ) ) {
		return 'youtube';
	}
	if ( false !== strpos( $url, 'vimeo.com' ) ) {
		return 'vimeo';
	}

	return 'self';
}

/**
 * Extract YouTube video ID from URL or raw ID.
 *
 * @param string $value URL or ID.
 *
 * @return string
 */
function cvl_extract_youtube_video_id( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '/^[A-Za-z0-9_-]{6,}$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '#(?:v=|youtu\.be/|youtube\.com/(?:embed/|shorts/|live/))([A-Za-z0-9_-]{6,})#', $value, $matches ) ) {
		return $matches[1];
	}

	return '';
}

/**
 * Extract Vimeo video ID from URL or raw ID.
 *
 * @param string $value URL or ID.
 *
 * @return string
 */
function cvl_extract_vimeo_video_id( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '/^[0-9]+$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '#vimeo\.com/(?:video/)?([0-9]+)#', $value, $matches ) ) {
		return $matches[1];
	}

	return '';
}

/**
 * Convert source URL to embeddable URL.
 *
 * @param int $video_id Video post ID.
 *
 * @return string
 */
function cvl_get_video_embed_url( $video_id ) {
	$video_id = (int) $video_id;
	$url      = cvl_get_media_source_url( $video_id );
	$provider = cvl_get_video_provider( $video_id );

	if ( 'youtube' === $provider ) {
		$youtube_value = (string) get_post_meta( $video_id, '_cvl_youtube_id', true );
		$youtube_id    = cvl_extract_youtube_video_id( '' !== trim( $youtube_value ) ? $youtube_value : $url );
		if ( '' !== $youtube_id ) {
			return 'https://www.youtube.com/embed/' . rawurlencode( $youtube_id )
				. '?enablejsapi=1&rel=0&origin=' . rawurlencode( home_url() );
		}
	}

	if ( 'vimeo' === $provider ) {
		$vimeo_value = (string) get_post_meta( $video_id, '_cvl_vimeo_id', true );
		$vimeo_id    = cvl_extract_vimeo_video_id( '' !== trim( $vimeo_value ) ? $vimeo_value : $url );
		if ( '' !== $vimeo_id ) {
			return 'https://player.vimeo.com/video/' . rawurlencode( $vimeo_id );
		}
	}

	if ( empty( $url ) ) {
		return '';
	}

	// For audio, the URL is already normalized by cvl_get_media_source_url.
	// Skip the extra transform pass but still return the clean URL.
	$normalized = ( 'audio' === cvl_get_media_type( $video_id ) )
		? (string) $url
		: cvl_transform_cloud_audio_url( (string) $url );

	// esc_url_raw is strict about percent-encoded non-ASCII characters;
	// fall back to returning the URL directly when it is a valid https/http URL.
	$safe_url = esc_url_raw( $normalized );
	if ( '' !== $safe_url ) {
		return $safe_url;
	}

	$parsed = wp_parse_url( $normalized );
	if (
		is_array( $parsed ) &&
		! empty( $parsed['scheme'] ) &&
		! empty( $parsed['host'] ) &&
		in_array( strtolower( (string) $parsed['scheme'] ), array( 'http', 'https' ), true )
	) {
		return $normalized;
	}

	return '';
}

/**
 * Get purchase/subscription URL for a locked video.
 *
 * @param int $video_id Video post ID.
 *
 * @return string
 */
function cvl_get_video_purchase_url( $video_id ) {
	$product_id = cvl_get_video_product_id( (int) $video_id );
	if ( $product_id > 0 ) {
		return get_permalink( $product_id );
	}

	$subscription_ids = cvl_get_video_subscription_ids( (int) $video_id );
	if ( ! empty( $subscription_ids ) ) {
		return get_permalink( (int) $subscription_ids[0] );
	}

	if ( function_exists( 'wc_get_page_permalink' ) ) {
		return wc_get_page_permalink( 'shop' );
	}

	return home_url( '/' );
}

/**
 * Get CTA label for locked video.
 *
 * @param int $video_id Video post ID.
 *
 * @return string
 */
function cvl_get_video_access_cta_label( $video_id ) {
	$is_audio = 'audio' === cvl_get_media_type( (int) $video_id );
	if ( cvl_get_video_product_id( (int) $video_id ) > 0 ) {
		return $is_audio ? __( 'Buy This Audio', 'custom-video-library' ) : __( 'Buy This Video', 'custom-video-library' );
	}

	if ( ! empty( cvl_get_video_subscription_ids( (int) $video_id ) ) ) {
		return __( 'Buy Subscription', 'custom-video-library' );
	}

	if ( ! empty( cvl_get_video_membership_plans( (int) $video_id ) ) ) {
		return __( 'View Membership Plans', 'custom-video-library' );
	}

	return __( 'View Plans', 'custom-video-library' );
}

/**
 * Get wishlist IDs for a user.
 *
 * @param int $user_id Optional user ID.
 *
 * @return array
 */
function cvl_get_user_wishlist_ids( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : (int) get_current_user_id();
	if ( $user_id <= 0 ) {
		return array();
	}

	$ids = get_user_meta( $user_id, '_cvl_wishlist_video_ids', true );
	if ( ! is_array( $ids ) ) {
		$ids = array();
	}

	$ids = array_values(
		array_unique(
			array_filter(
				array_map( 'intval', $ids ),
				static function( $id ) {
					return $id > 0;
				}
			)
		)
	);

	return $ids;
}

/**
 * Check if a video is in user's wishlist.
 *
 * @param int $video_id Video ID.
 * @param int $user_id Optional user ID.
 *
 * @return bool
 */
function cvl_is_video_in_user_wishlist( $video_id, $user_id = 0 ) {
	$video_id = (int) $video_id;
	if ( $video_id <= 0 ) {
		return false;
	}

	return in_array( $video_id, cvl_get_user_wishlist_ids( $user_id ), true );
}

/**
 * Toggle wishlist state for a video.
 *
 * @param int $video_id Video ID.
 * @param int $user_id Optional user ID.
 *
 * @return array
 */
function cvl_toggle_video_wishlist( $video_id, $user_id = 0 ) {
	$user_id  = $user_id ? (int) $user_id : (int) get_current_user_id();
	$video_id = (int) $video_id;

	if ( $user_id <= 0 || $video_id <= 0 || 'video' !== get_post_type( $video_id ) ) {
		return array(
			'ok'    => false,
			'state' => 'error',
			'ids'   => array(),
		);
	}

	$ids = cvl_get_user_wishlist_ids( $user_id );

	if ( in_array( $video_id, $ids, true ) ) {
		$ids   = array_values( array_diff( $ids, array( $video_id ) ) );
		$state = 'removed';
	} else {
		$ids[] = $video_id;
		$ids   = array_values( array_unique( array_map( 'intval', $ids ) ) );
		$state = 'added';
	}

	update_user_meta( $user_id, '_cvl_wishlist_video_ids', $ids );

	return array(
		'ok'    => true,
		'state' => $state,
		'ids'   => $ids,
	);
}

/**
 * Update video metadata.
 *
 * @param int   $video_id The video post ID.
 * @param array $metadata The metadata array.
 *
 * @return void
 */
function cvl_update_video_metadata( $video_id, $metadata ) {
	$video_id = (int) $video_id;

	$meta_mapping = array(
		'media_type'         => '_cvl_media_type',
		'duration'           => '_cvl_duration',
		'video_url'          => '_cvl_video_url',
		'youtube_url'        => '_cvl_youtube_id',
		'vimeo_url'          => '_cvl_vimeo_id',
		'preview_url'        => '_cvl_preview_url',
		'age_rating'         => '_cvl_age_rating',
		'release_year'       => '_cvl_release_year',
		'is_free'            => '_is_free_video',
		'wc_product_id'      => '_wc_product_id',
		'wc_subscription_id' => '_wc_subscription_id',
		'membership_plan'    => '_woo_membership_plan',
	);

	foreach ( $meta_mapping as $key => $meta_key ) {
		if ( ! isset( $metadata[ $key ] ) ) {
			continue;
		}

		$value = $metadata[ $key ];

		switch ( $meta_key ) {
			case '_cvl_media_type':
				$value = in_array( sanitize_key( (string) $value ), array( 'video', 'audio' ), true ) ? sanitize_key( (string) $value ) : 'video';
				break;
			case '_is_free_video':
				$value = cvl_normalize_flag( $value );
				break;
			case '_cvl_video_url':
			case '_cvl_youtube_id':
			case '_cvl_vimeo_id':
			case '_cvl_preview_url':
				$value = esc_url_raw( (string) $value, array( 'http', 'https' ) );
				break;
			case '_cvl_duration':
			case '_cvl_release_year':
			case '_wc_product_id':
				$value = absint( $value );
				break;
			case '_wc_subscription_id':
				$value = is_array( $value ) ? cvl_normalize_meta_id_list( $value ) : absint( $value );
				break;
			case '_woo_membership_plan':
				$value = is_array( $value ) ? cvl_normalize_membership_plan_values( $value ) : sanitize_text_field( (string) $value );
				break;
			default:
				$value = sanitize_text_field( (string) $value );
				break;
		}

		update_post_meta( $video_id, $meta_key, $value );
	}
}

/**
 * Log a message for debugging.
 *
 * @param string $message The message to log.
 * @param mixed  $data Optional data to log.
 *
 * @return void
 */
function cvl_log( $message, $data = null ) {
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}

	$log_message = '[Custom Video Library] ' . $message;

	if ( $data ) {
		$log_message .= ': ' . wp_json_encode( $data );
	}

	error_log( $log_message );
}

/**
 * Verify nonce for AJAX requests.
 *
 * @param string $nonce The nonce to verify.
 * @param string $action Optional action name for the nonce.
 *
 * @return bool True if nonce is valid, false otherwise.
 */
function cvl_verify_nonce( $nonce, $action = 'cvl_nonce' ) {
	return wp_verify_nonce( $nonce, $action ) > 0;
}

/**
 * Create a nonce for AJAX requests.
 *
 * @param string $action Optional action name for the nonce.
 *
 * @return string The nonce.
 */
function cvl_create_nonce( $action = 'cvl_nonce' ) {
	return wp_create_nonce( $action );
}

/**
 * Sanitize and validate a video URL.
 *
 * @param string $url The URL to sanitize.
 *
 * @return string|false The sanitized URL or false if invalid.
 */
function cvl_sanitize_video_url( $url ) {
	$allowed_protocols = array( 'http', 'https' );
	return wp_http_validate_url( $url ) ? esc_url_raw( $url, $allowed_protocols ) : false;
}

/**
 * Get the video player URL for embedding.
 *
 * @param int $video_id The video post ID.
 *
 * @return string The video player URL.
 */
function cvl_get_player_url( $video_id ) {
	/**
	 * Filter the video player URL.
	 *
	 * @param string $url The player URL.
	 * @param int    $video_id The video post ID.
	 */
	return apply_filters(
		'cvl_player_url',
		CVL_PLUGIN_URL . 'assets/js/player.js',
		(int) $video_id
	);
}
