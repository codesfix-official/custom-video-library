<?php
/**
 * Custom Post Types Registration
 *
 * @package CustomVideoLibrary
 */

namespace CustomVideoLibrary;

/**
 * Class for registering custom post types.
 */
class Post_Types {

	/**
	 * Initialize the post types.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_video_meta' ) );
		add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ) );
	}

	/**
	 * Register custom image sizes.
	 *
	 * @return void
	 */
	public function register_image_sizes() {
		// 2:3 poster ratio used by card UI for consistent thumbnails.
		add_image_size( 'cvl-card', 720, 1080, true );
	}

	/**
	 * Register custom post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		/**
		 * Register content library post type.
		 */
		register_post_type(
			'video',
			array(
				'label'               => esc_html__( 'Content Library', 'custom-video-library' ),
				'labels'              => array(
					'name'               => esc_html__( 'Content Library', 'custom-video-library' ),
					'singular_name'      => esc_html__( 'Content Item', 'custom-video-library' ),
					'add_new'            => esc_html__( 'Add New Content', 'custom-video-library' ),
					'add_new_item'       => esc_html__( 'Add New Content', 'custom-video-library' ),
					'edit_item'          => esc_html__( 'Edit Content', 'custom-video-library' ),
					'new_item'           => esc_html__( 'New Content', 'custom-video-library' ),
					'view_item'          => esc_html__( 'View Content', 'custom-video-library' ),
					'search_items'       => esc_html__( 'Search Content', 'custom-video-library' ),
					'not_found'          => esc_html__( 'No content found', 'custom-video-library' ),
					'not_found_in_trash' => esc_html__( 'No content found in trash', 'custom-video-library' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'rest_base'           => 'videos',
				// NOTE: 'custom-fields' is intentionally omitted.
				// Including it causes WordPress to expose ALL post meta — including
				// unregistered keys stored by ACF — to unauthenticated REST API requests.
				// ACF does not require 'custom-fields' support to function.
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'taxonomies'          => array( 'video_genre', 'video_category' ),
				'capability_type'     => 'video',
				'map_meta_cap'        => true,
				'rewrite'             => array(
					'slug'       => 'library',
					'with_front' => true,
				),
				'has_archive'         => true,
				'menu_position'       => 5,
				'menu_icon'           => 'dashicons-format-video',
			)
		);
	}

	/**
	 * Register custom taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		/**
		 * Register 'video_genre' taxonomy.
		 */
		register_taxonomy(
			'video_genre',
			array( 'video' ),
			array(
				'label'              => esc_html__( 'Genres', 'custom-video-library' ),
				'labels'             => array(
					'name'              => esc_html__( 'Genres', 'custom-video-library' ),
					'singular_name'     => esc_html__( 'Genre', 'custom-video-library' ),
					'add_new'           => esc_html__( 'Add New Genre', 'custom-video-library' ),
					'edit_item'         => esc_html__( 'Edit Genre', 'custom-video-library' ),
					'new_item'          => esc_html__( 'New Genre', 'custom-video-library' ),
					'view_item'         => esc_html__( 'View Genre', 'custom-video-library' ),
					'search_items'      => esc_html__( 'Search Genres', 'custom-video-library' ),
					'not_found'         => esc_html__( 'No genres found', 'custom-video-library' ),
					'popular_items'     => esc_html__( 'Popular Genres', 'custom-video-library' ),
					'back_to_items'     => esc_html__( 'Back to Genres', 'custom-video-library' ),
				),
				'public'             => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rest_base'          => 'video-genres',
				'hierarchical'       => false,
				'rewrite'            => array( 'slug' => 'genre' ),
			)
		);

		/**
		 * Register 'video_category' taxonomy.
		 */
		register_taxonomy(
			'video_category',
			array( 'video' ),
			array(
				'label'              => esc_html__( 'Categories', 'custom-video-library' ),
				'labels'             => array(
					'name'              => esc_html__( 'Categories', 'custom-video-library' ),
					'singular_name'     => esc_html__( 'Category', 'custom-video-library' ),
					'add_new'           => esc_html__( 'Add New Category', 'custom-video-library' ),
					'edit_item'         => esc_html__( 'Edit Category', 'custom-video-library' ),
					'new_item'          => esc_html__( 'New Category', 'custom-video-library' ),
					'view_item'         => esc_html__( 'View Category', 'custom-video-library' ),
					'search_items'      => esc_html__( 'Search Categories', 'custom-video-library' ),
					'not_found'         => esc_html__( 'No categories found', 'custom-video-library' ),
					'popular_items'     => esc_html__( 'Popular Categories', 'custom-video-library' ),
					'back_to_items'     => esc_html__( 'Back to Categories', 'custom-video-library' ),
				),
				'public'             => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rest_base'          => 'video-categories',
				'hierarchical'       => true,
				'rewrite'            => array( 'slug' => 'video-category' ),
			)
		);
	}

	/**
	 * Register video metadata fields.
	 *
	 * @return void
	 */
	public function register_video_meta() {
		// Non-sensitive fields: safe to expose publicly.
		register_post_meta(
			'video',
			'_cvl_media_type',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => 'video',
				'sanitize_callback' => static function( $value ) {
					$value = sanitize_key( (string) $value );
					return in_array( $value, array( 'video', 'audio' ), true ) ? $value : 'video';
				},
				'auth_callback'     => static function() {
					return current_user_can( 'edit_videos' );
				},
			)
		);

		register_post_meta(
			'video',
			'_cvl_video_provider',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function() {
					return current_user_can( 'edit_videos' );
				},
			)
		);

		// Sensitive fields: explicitly registered with show_in_rest: false.
		// Covers BOTH the underscore-prefixed internal keys AND the non-prefixed ACF
		// name variants (ACF stores the actual value at the non-prefixed key, e.g.
		// 'cvl_video_url', and uses '_cvl_video_url' only as an internal field-key
		// reference). Both must be blocked so neither variant leaks via REST.
		$sensitive_fields = array(
			'_cvl_video_url',       'cvl_video_url',
			'_cvl_youtube_id',      'cvl_youtube_id',
			'_cvl_vimeo_id',        'cvl_vimeo_id',
			'_cvl_audio_file',      'cvl_audio_file',
			'_cvl_preview_url',     'cvl_preview_url',
			'_is_free_video',       'is_free_video',
// 			'_wc_product_id',       'wc_product_id',
// 			'_wc_subscription_id',  'wc_subscription_id',
// 			'_woo_membership_plan', 'woo_membership_plan',
		);
		$commerce_id_fields = array(
			'_wc_product_id',
			'wc_product_id',
		);

		$commerce_list_fields = array(
			'_wc_subscription_id',
			'wc_subscription_id',
			'_woo_membership_plan',
			'woo_membership_plan',
		);

		foreach ( $sensitive_fields as $meta_key ) {
			$sanitize_callback = 'sanitize_text_field';

			if ( in_array( $meta_key, $commerce_id_fields, true ) ) {
				$sanitize_callback = static function( $value ) {
					$ids = cvl_normalize_meta_id_list( $value );

					return ! empty( $ids ) ? (int) $ids[0] : 0;
				};
			} elseif ( in_array( $meta_key, $commerce_list_fields, true ) ) {
				$sanitize_callback = static function( $value ) use ( $meta_key ) {
					if ( false !== strpos( $meta_key, 'membership_plan' ) ) {
						$normalized = cvl_normalize_membership_plan_values( $value );
						if ( is_array( $value ) ) {
							return $normalized;
						}

						return ! empty( $normalized ) ? $normalized[0] : '';
					}

					$normalized = cvl_normalize_meta_id_list( $value );
					if ( is_array( $value ) ) {
						return $normalized;
					}

					return ! empty( $normalized ) ? (int) $normalized[0] : 0;
				};
			}
			register_post_meta(
				'video',
				$meta_key,
				array(
					'show_in_rest'      => false,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => static function() {
						return current_user_can( 'edit_videos' );
					},
				)
			);
		}

		// Defence-in-depth: strip any sensitive key that a third-party plugin
		// (e.g. ACF REST API add-on) may re-register with show_in_rest: true.
		// Priority 99 runs after ACF's own REST hooks.
		add_filter( 'rest_prepare_video', array( $this, 'strip_sensitive_meta_from_rest' ), 99, 3 );
	}

	/**
	 * Last-resort REST response filter: removes any sensitive field that leaked
	 * through a third-party registration (e.g. ACF REST add-on, other plugins).
	 *
	 * Strips both the underscore-prefixed internal key AND the non-prefixed ACF
	 * name variant for every sensitive field.
	 * Editors/admins always receive the full response (Gutenberg requires it).
	 *
	 * @param \WP_REST_Response $response REST response object.
	 * @param \WP_Post          $post     The post.
	 * @param \WP_REST_Request  $request  The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function strip_sensitive_meta_from_rest( $response, $post, $request ) {
		// Editors/admins always receive full data.
		if ( current_user_can( 'edit_videos' ) ) {
			return $response;
		}

		$video_id = (int) $post->ID;

		// Determine whether the current user may access this item.
		$can_access = cvl_is_video_free( $video_id ) || ! cvl_video_has_paid_gate( $video_id );

		if ( ! $can_access ) {
			$user_id    = get_current_user_id();
			$can_access = $user_id && Capabilities::can_user_access_video( $user_id, $video_id );
		}

		if ( $can_access ) {
			return $response;
		}

		// Both underscore-prefixed and non-prefixed variants must be stripped.
		$sensitive_keys = array(
			'_cvl_video_url',       'cvl_video_url',
			'_cvl_youtube_id',      'cvl_youtube_id',
			'_cvl_vimeo_id',        'cvl_vimeo_id',
			'_cvl_audio_file',      'cvl_audio_file',
			'_cvl_preview_url',     'cvl_preview_url',
			'_is_free_video',       'is_free_video',
			'_wc_product_id',       'wc_product_id',
			'_wc_subscription_id',  'wc_subscription_id',
			'_woo_membership_plan', 'woo_membership_plan',
		);

		$data = $response->get_data();

		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( $sensitive_keys as $key ) {
				unset( $data['meta'][ $key ] );
			}
			$response->set_data( $data );
		}

		return $response;
	}
}
