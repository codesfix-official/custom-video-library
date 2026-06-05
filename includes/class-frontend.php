<?php
/**
 * Frontend features (player + private library).
 *
 * @package CustomVideoLibrary
 */

namespace CustomVideoLibrary;

/**
 * Frontend class.
 */
class Frontend {

	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'cvl_video_player', array( $this, 'render_video_player_shortcode' ) );
		add_shortcode( 'cvl_private_library', array( $this, 'render_private_library_shortcode' ) );
		add_shortcode( 'cvl_free_library', array( $this, 'render_free_library_shortcode' ) );
		add_shortcode( 'cvl_paid_library', array( $this, 'render_paid_library_shortcode' ) );
		add_shortcode( 'cvl_audio_library', array( $this, 'render_audio_library_shortcode' ) );

		add_filter( 'template_include', array( $this, 'maybe_load_plugin_template' ) );
		add_filter( 'body_class', array( $this, 'add_library_body_class' ) );

		add_action( 'wp_ajax_cvl_save_progress', array( $this, 'save_progress' ) );
		add_action( 'wp_ajax_nopriv_cvl_save_progress', array( $this, 'save_progress_unauthorized' ) );
		add_action( 'wp_ajax_cvl_toggle_wishlist', array( $this, 'toggle_wishlist' ) );
		add_action( 'wp_ajax_nopriv_cvl_toggle_wishlist', array( $this, 'toggle_wishlist_unauthorized' ) );
		add_action( 'wp_ajax_cvl_filter_library', array( $this, 'filter_library' ) );
		add_action( 'wp_ajax_nopriv_cvl_filter_library', array( $this, 'filter_library' ) );

		// Gate single video pages at the routing level, not just inside the shortcode.
		add_action( 'template_redirect', array( $this, 'protect_single_video' ) );
	}

	/**
	 * Add frontend body class for library pages.
	 *
	 * @param array $classes Existing classes.
	 *
	 * @return array
	 */
	public function add_library_body_class( $classes ) {
		if ( is_post_type_archive( 'video' ) || is_singular( 'video' ) || is_tax( array( 'video_category', 'video_genre' ) ) ) {
			$classes[] = 'cvl-library-page';
		}

		return $classes;
	}

	/**
	 * Load plugin templates for video pages.
	 *
	 * @param string $template Resolved theme template.
	 *
	 * @return string
	 */
	public function maybe_load_plugin_template( $template ) {
		$candidates = array();

		if ( is_post_type_archive( 'video' ) ) {
			$candidates = array( 'archive-video.php' );
		} elseif ( is_tax( 'video_category' ) ) {
			$candidates = array( 'taxonomy-video_category.php' );
		} elseif ( is_tax( 'video_genre' ) ) {
			$candidates = array( 'taxonomy-video_genre.php' );
		} elseif ( is_singular( 'video' ) ) {
			$candidates = array( 'single-video.php' );
		}

		if ( empty( $candidates ) ) {
			return $template;
		}

		// Allow the active theme to override plugin templates.
		$theme_template = locate_template( $candidates );
		if ( $theme_template ) {
			return $theme_template;
		}

		// Fall back to the plugin's bundled template.
		$plugin_template = CVL_PLUGIN_DIR . 'templates/' . $candidates[0];
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	/**
	 * Check whether current user can play a video.
	 *
	 * @param int $video_id Video ID.
	 *
	 * @return bool
	 */
	public function can_current_user_play_video( $video_id ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return Capabilities::can_user_access_video( $user_id, (int) $video_id );
	}

	/**
	 * Block direct access to protected single video pages.
	 *
	 * @return void
	 */
	public function protect_single_video() {
		if ( ! is_post_type_archive( 'video' ) ) {
			return;
		}
		
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			// Get my-account page URL
			$redirect_to = home_url( '/my-account/' );

			// Fallback: try WooCommerce my-account page
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				$wc_account = wc_get_page_permalink( 'myaccount' );
				if ( $wc_account ) {
					$redirect_to = $wc_account;
				}
			}

			wp_redirect( $redirect_to );
			exit;
		}
	}

	/**
	 * Render the protected video player.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_video_player_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => get_the_ID(),
			),
			$atts,
			'cvl_video_player'
		);

		$video_id = (int) $atts['id'];
		$user_id  = get_current_user_id();

		if ( ! $video_id || 'video' !== get_post_type( $video_id ) ) {
			return '<p>' . esc_html__( 'Invalid video.', 'custom-video-library' ) . '</p>';
		}

		$media_type = cvl_get_media_type( $video_id );
		$thumb_url  = get_the_post_thumbnail_url( $video_id, 'large' );
		$thumb_html = $thumb_url ? '<img class="cvl-paywall-thumb" src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( get_the_title( $video_id ) ) . '" />' : '';

		$is_free   = cvl_is_video_free( $video_id );
		$can_play  = $is_free || ! cvl_video_has_paid_gate( $video_id ) || ( $user_id && Capabilities::can_user_access_video( $user_id, $video_id ) );

		if ( ! $can_play ) {
			return '<div class="cvl-paywall">' . $thumb_html . '</div>';
		}

		$provider  = cvl_get_video_provider( $video_id );
		$media_type = cvl_get_media_type( $video_id );
		$embed_url = cvl_get_video_embed_url( $video_id );
		$duration  = (int) get_post_meta( $video_id, '_cvl_duration', true );
		$resume_seconds = 0;
		if ( $user_id ) {
			$progress_map    = Database::get_user_progress_map( $user_id );
			$resume_seconds = isset( $progress_map[ $video_id ]['duration_watched'] ) ? (int) $progress_map[ $video_id ]['duration_watched'] : 0;
		}

		if ( ! $embed_url ) {
			return '<p>' . esc_html__( 'No video URL configured.', 'custom-video-library' ) . '</p>';
		}

		ob_start();
		?>
		<div class="cvl-player-wrap" data-video-id="<?php echo esc_attr( $video_id ); ?>" data-provider="<?php echo esc_attr( $provider ); ?>" data-media-type="<?php echo esc_attr( $media_type ); ?>" data-duration="<?php echo esc_attr( $duration ); ?>" data-resume-seconds="<?php echo esc_attr( $resume_seconds ); ?>">
			<?php if ( 'youtube' === $provider || 'vimeo' === $provider ) : ?>
				<iframe
					class="cvl-iframe-player"
					src="<?php echo esc_url( $embed_url ); ?>"
					allow="autoplay; encrypted-media; picture-in-picture"
					allowfullscreen
					loading="lazy"
					title="<?php echo esc_attr( get_the_title( $video_id ) ); ?>"
				></iframe>
			<?php elseif ( 'audio' === $media_type ) : ?>
			<?php
			// Route through proxy only when a source explicitly requires it.
			$audio_src = cvl_audio_needs_proxy( $embed_url )
				? cvl_get_audio_proxy_url( $video_id )
				: $embed_url;
			$audio_fallback_src = cvl_get_bunny_audio_fallback_url( $audio_src );
			$audio_primary_src  = $audio_fallback_src ? $audio_fallback_src : $audio_src;
			$audio_retry_src    = $audio_fallback_src ? $audio_src : '';
			?>
				<div class="cvl-audio-player-wrap">
<audio class="cvl-html5-media cvl-html5-audio-player" controls preload="none" crossorigin="anonymous" src="<?php echo esc_url( $audio_primary_src ); ?>"<?php echo $audio_retry_src ? ' data-fallback-src="' . esc_attr( $audio_retry_src ) . '"' : ''; ?>>
						<source src="<?php echo esc_url( $audio_primary_src ); ?>" type="audio/mpeg" />
					</audio>
					<canvas class="cvl-audio-visualizer" aria-hidden="true"></canvas>
				</div>
			<?php else : ?>
				<video class="cvl-html5-media cvl-html5-player" controls playsinline preload="metadata">
					<source src="<?php echo esc_url( $embed_url ); ?>" type="video/mp4" />
				</video>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render private user library with purchased and unfinished videos.
	 *
	 * @return string
	 */
	public function render_private_library_shortcode() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			 return '<p class="logout-error-msg">Please <a style="color:#D4967D;" href="' . esc_url( home_url( '/my-account/' ) ) . '">log in</a> to view your private library.</p>';
		}

		$videos = get_posts(
			array(
				'post_type'      => 'video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$progress_map      = Database::get_user_progress_map( $user_id );
		$purchased_videos  = array();
		$unfinished_videos = array();
		$wishlist_videos   = array();

		$wishlist_ids = cvl_get_user_wishlist_ids( $user_id );
		if ( ! empty( $wishlist_ids ) ) {
			$wishlist_videos = get_posts(
				array(
					'post_type'      => 'video',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'post__in'       => $wishlist_ids,
					'orderby'        => 'post__in',
				)
			);
		}

		foreach ( $videos as $video ) {
			$video_id = (int) $video->ID;

			if ( ! Capabilities::can_user_access_video( $user_id, $video_id ) ) {
				continue;
			}

			//$product_id = (int) get_post_meta( $video_id, '_wc_product_id', true );
			$product_id = cvl_get_video_product_id( $video_id );
			if ( $product_id > 0 && cvl_user_has_purchased_product( $user_id, $product_id ) ) {
				$purchased_videos[] = $video;
			}

			if ( isset( $progress_map[ $video_id ] ) ) {
				$progress = $progress_map[ $video_id ];
				if ( ! $progress['completed'] && $progress['duration_watched'] > 0 ) {
					$unfinished_videos[] = $video;
				}
			}
		}

		ob_start();
		?>
		<div class="cvl-library-page">
			<div class="cvl-shell">
				<div class="cvl-private-library">
					<h2><?php echo esc_html__( 'My Private Library', 'custom-video-library' ); ?></h2>

					<div class="cvl-library-section">
						<h3><?php echo esc_html__( 'My Wishlist', 'custom-video-library' ); ?></h3>
						<?php echo $this->render_library_cards( $wishlist_videos, $progress_map ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<div class="cvl-library-section">
						<h3><?php echo esc_html__( 'Purchased', 'custom-video-library' ); ?></h3>
						<?php echo $this->render_library_cards( $purchased_videos, $progress_map ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<div class="cvl-library-section">
						<h3><?php echo esc_html__( 'Continue Watching / Listening', 'custom-video-library' ); ?></h3>
						<?php echo $this->render_library_cards( $unfinished_videos, $progress_map ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render video cards for private library.
	 *
	 * @param array $videos Video posts.
	 * @param array $progress_map User progress by video id.
	 *
	 * @return string
	 */
	private function render_library_cards( $videos, $progress_map ) {
		if ( empty( $videos ) ) {
			return '<p>' . esc_html__( 'No videos found in this section.', 'custom-video-library' ) . '</p>';
		}

		ob_start();
		echo '<div class="cvl-private-grid">';

		foreach ( $videos as $video ) {
			$video_id   = (int) $video->ID;
			$thumb_url  = get_the_post_thumbnail_url( $video_id, 'cvl-card' );
			$media_type = cvl_get_media_type( $video_id );
			$card_title = get_the_title( $video_id );
			$progress   = isset( $progress_map[ $video_id ] ) ? $progress_map[ $video_id ] : array(
				'percent'          => 0,
				'completed'        => 0,
				'duration_watched' => 0,
			);

			$pct   = (int) $progress['percent'];
			$media_label = 'audio' === $media_type ? __( 'listened', 'custom-video-library' ) : __( 'watched', 'custom-video-library' );
			if ( $pct > 0 ) {
				$progress_label = $pct . '% ' . $media_label;
			} elseif ( $progress['duration_watched'] > 0 ) {
				$progress_label = __( 'In Progress', 'custom-video-library' );
			} else {
				$progress_label = __( 'Not started', 'custom-video-library' );
			}

			echo '<article class="cvl-card cvl-private-card">';
			echo '<a class="cvl-card-link" href="' . esc_url( get_permalink( $video_id ) ) . '">';
			echo '<div class="cvl-card-media">';
			echo '<span class="cvl-media-badge cvl-media-badge-' . esc_attr( $media_type ) . '">' . esc_html( 'audio' === $media_type ? __( 'Audio', 'custom-video-library' ) : __( 'Video', 'custom-video-library' ) ) . '</span>';
			echo '<span class="cvl-pill is-open">' . esc_html( $progress_label ) . '</span>';
			echo '<span class="cvl-card-play-icon" aria-hidden="true"></span>';
			if ( $thumb_url ) {
				echo '<img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $card_title ) . '" loading="lazy" />';
			} else {
				echo '<div class="cvl-card-placeholder"></div>';
			}
			echo '</div>';
			echo '<div class="cvl-card-body">';
			echo '<h2>' . esc_html( $card_title ) . '</h2>';
			echo '<div class="cvl-private-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( max( 0, min( 100, $pct ) ) ) . '"><span style="width:' . esc_attr( max( 0, min( 100, $pct ) ) ) . '%"></span></div>';
			echo '<span class="cvl-private-progress-label">' . esc_html( $progress_label ) . '</span>';
			echo '<div class="cvl-meta-row cvl-private-meta">';
			echo '<span class="cvl-meta-item">' . esc_html( 'audio' === $media_type ? __( 'Audio', 'custom-video-library' ) : __( 'Video', 'custom-video-library' ) ) . '</span>';
			echo '</div>';
			echo '</div>';
			echo '</a>';

			echo '</article>';
		}

		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Render shortcode for audio library (all audio posts, free and paid).
	 *
	 * Usage: [cvl_audio_library]
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_audio_library_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'posts_per_page' => 12,
				'paged'          => max( 1, (int) get_query_var( 'paged' ) ),
			),
			$atts,
			'cvl_audio_library'
		);

		$posts_per_page = (int) $atts['posts_per_page'];
		$paged          = (int) $atts['paged'];
		$selected_cat   = isset( $_GET['cvl_cat'] ) ? sanitize_title( wp_unslash( $_GET['cvl_cat'] ) ) : '';
		if ( ! $selected_cat && ! empty( $atts['cvl_cat'] ) ) {
			$selected_cat = sanitize_title( (string) $atts['cvl_cat'] );
		}

		$args = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				array(
					'key'     => '_cvl_media_type',
					'value'   => 'audio',
					'compare' => '=',
				),
			),
		);

		if ( '' !== $selected_cat ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				array(
					'taxonomy' => 'video_category',
					'field'    => 'slug',
					'terms'    => $selected_cat,
				),
			);
		}

		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<p>' . esc_html__( 'No audio found.', 'custom-video-library' ) . '</p>';
		}

		// Get all categories that have at least one audio post.
		$audio_post_ids = get_posts(
			array(
				'post_type'      => 'video',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
					array(
						'key'     => '_cvl_media_type',
						'value'   => 'audio',
						'compare' => '=',
					),
				),
			)
		);

		$term_args = array(
			'taxonomy'   => 'video_category',
			'hide_empty' => true,
		);
		if ( ! empty( $audio_post_ids ) ) {
			$term_args['object_ids'] = $audio_post_ids;
		} else {
			$term_args['include'] = array( 0 );
		}

		ob_start();
		?>
		<div class="cvl-page cvl-archive-page" data-library-type="audio">
			<div class="cvl-shell cvl-content-section">
				<?php
				$terms = get_terms( $term_args );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) :
					$all_link = remove_query_arg( array( 'cvl_cat', 'paged' ) );
					?>
					<div class="cvl-chip-row">
						<a class="cvl-chip <?php echo '' === $selected_cat ? 'is-active' : ''; ?>" href="<?php echo esc_url( $all_link ); ?>">
							<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
							<?php esc_html_e( 'All', 'custom-video-library' ); ?>
						</a>
						<?php foreach ( $terms as $term ) : ?>
							<a class="cvl-chip <?php echo $selected_cat === $term->slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'cvl_cat' => $term->slug ), remove_query_arg( 'paged' ) ) ); ?>">
								<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
								<?php echo esc_html( $term->name ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="cvl-grid" id="cvl-library-grid">
					<?php
					while ( $query->have_posts() ) {
						$query->the_post();
						$video_id = get_the_ID();
						$thumb    = get_the_post_thumbnail_url( $video_id, 'cvl-card' );
						$duration = (int) get_post_meta( $video_id, '_cvl_duration', true );
						$excerpt  = wp_trim_words( get_the_excerpt(), 5, '...' );
						?>
						<article class="cvl-card">
							<a href="<?php the_permalink(); ?>" class="cvl-card-link">
								<div class="cvl-card-media">
									<span class="cvl-media-badge cvl-media-badge-audio"><?php esc_html_e( 'Audio', 'custom-video-library' ); ?></span>
									<span class="cvl-card-play-icon" aria-hidden="true"></span>
									<?php if ( $duration > 0 ) : ?>
										<span class="cvl-duration-badge"><?php echo esc_html( gmdate( 'i:s', $duration ) ); ?></span>
									<?php endif; ?>
									<?php if ( $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
									<?php else : ?>
										<div class="cvl-card-placeholder"></div>
									<?php endif; ?>
								</div>
								<div class="cvl-card-body">
									<h2><?php the_title(); ?></h2>
									<?php if ( $excerpt ) : ?>
										<p class="cvl-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
									<?php endif; ?>
								</div>
							</a>
						</article>
					<?php } ?>
				</div>

				<div class="cvl-pagination" id="cvl-library-pagination">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => __( '&larr; Previous', 'custom-video-library' ),
								'next_text' => __( 'Next &rarr;', 'custom-video-library' ),
								'total'     => $query->max_num_pages,
								'current'   => $paged,
								'add_args'  => '' !== $selected_cat ? array( 'cvl_cat' => $selected_cat ) : array(),
							)
						)
					);
					?>
				</div>
			</div>
		</div>
		<?php
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Render shortcode for free media library.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_free_library_shortcode( $atts ) {
		return $this->render_filtered_library_shortcode( true, $atts );
	}

	/**
	 * Render shortcode for paid media library.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_paid_library_shortcode( $atts ) {
		return $this->render_filtered_library_shortcode( false, $atts );
	}

	/**
	 * Render filtered library shortcode (free or paid).
	 *
	 * @param bool  $is_free Show free (true) or paid (false) media.
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	private function render_filtered_library_shortcode( $is_free, $atts ) {
		$atts = shortcode_atts(
			array(
				'posts_per_page' => 12,
				'paged'          => max( 1, (int) get_query_var( 'paged' ) ),
			),
			$atts,
			'cvl_library'
		);

		$posts_per_page = (int) $atts['posts_per_page'];
		$paged          = (int) $atts['paged'];
		$selected_cat   = isset( $_GET['cvl_cat'] ) ? sanitize_title( wp_unslash( $_GET['cvl_cat'] ) ) : '';
		if ( ! $selected_cat && ! empty( $atts['cvl_cat'] ) ) {
			$selected_cat = sanitize_title( (string) $atts['cvl_cat'] );
		}

		// Query videos filtered by free/paid status; exclude audio posts.
		$free_flag_value = $is_free ? 1 : 0;
		$args            = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				'relation' => 'AND',
				array(
					'key'   => '_is_free_video',
					'value' => $free_flag_value,
					'type'  => 'NUMERIC',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_cvl_media_type',
						'value'   => 'audio',
						'compare' => '!=',
					),
					array(
						'key'     => '_cvl_media_type',
						'compare' => 'NOT EXISTS',
					),
				),
			),
		);

		if ( '' !== $selected_cat ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				array(
					'taxonomy' => 'video_category',
					'field'    => 'slug',
					'terms'    => $selected_cat,
				),
			);
		}

		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<p>' . esc_html__( 'No videos found.', 'custom-video-library' ) . '</p>';
		}

		ob_start();
		?>
		<div class="cvl-page cvl-archive-page" data-library-type="<?php echo $is_free ? 'free' : 'paid'; ?>">
			<div class="cvl-shell cvl-content-section">
				<?php
				$term_args = array(
					'taxonomy'   => 'video_category',
					'hide_empty' => true,
				);

				if ( $is_free ) {
					$free_video_ids = get_posts(
						array(
							'post_type'      => 'video',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'no_found_rows'  => true,
							'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
								'relation' => 'AND',
								array(
									'key'   => '_is_free_video',
									'value' => 1,
									'type'  => 'NUMERIC',
								),
								array(
									'relation' => 'OR',
									array(
										'key'     => '_cvl_media_type',
										'value'   => 'audio',
										'compare' => '!=',
									),
									array(
										'key'     => '_cvl_media_type',
										'compare' => 'NOT EXISTS',
									),
								),
							),
						)
					);

					if ( ! empty( $free_video_ids ) ) {
						$term_args['object_ids'] = $free_video_ids;
					} else {
						$term_args['include'] = array( 0 );
					}
				} else {
					// For paid videos, also exclude audio
					$paid_video_ids = get_posts(
						array(
							'post_type'      => 'video',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'fields'         => 'ids',
							'no_found_rows'  => true,
							'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
								'relation' => 'AND',
								array(
									'key'   => '_is_free_video',
									'value' => 0,
									'type'  => 'NUMERIC',
								),
								array(
									'relation' => 'OR',
									array(
										'key'     => '_cvl_media_type',
										'value'   => 'audio',
										'compare' => '!=',
									),
									array(
										'key'     => '_cvl_media_type',
										'compare' => 'NOT EXISTS',
									),
								),
							),
						)
					);

					if ( ! empty( $paid_video_ids ) ) {
						$term_args['object_ids'] = $paid_video_ids;
					} else {
						$term_args['include'] = array( 0 );
					}
				}

				$terms = get_terms( $term_args );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) :
					$all_link = remove_query_arg( array( 'cvl_cat', 'paged' ) );
					?>
					<div class="cvl-chip-row">
						<a class="cvl-chip <?php echo '' === $selected_cat ? 'is-active' : ''; ?>" href="<?php echo esc_url( $all_link ); ?>">
							<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
							<?php esc_html_e( 'All', 'custom-video-library' ); ?>
						</a>
						<?php foreach ( $terms as $term ) : ?>
							<a class="cvl-chip <?php echo $selected_cat === $term->slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'cvl_cat' => $term->slug ), remove_query_arg( 'paged' ) ) ); ?>">
								<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
								<?php echo esc_html( $term->name ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="cvl-grid" id="cvl-library-grid">
					<?php
					while ( $query->have_posts() ) {
						$query->the_post();
						$video_id   = get_the_ID();
						$media_type = cvl_get_media_type( $video_id );
						$thumb      = get_the_post_thumbnail_url( $video_id, 'cvl-card' );
						$duration   = (int) get_post_meta( $video_id, '_cvl_duration', true );
						$excerpt    = wp_trim_words( get_the_excerpt(), 5, '...' );
						?>
						<article class="cvl-card">
							<a href="<?php the_permalink(); ?>" class="cvl-card-link">
								<div class="cvl-card-media">
									<span class="cvl-media-badge cvl-media-badge-<?php echo esc_attr( $media_type ); ?>"><?php echo esc_html( 'audio' === $media_type ? __( 'Audio', 'custom-video-library' ) : __( 'Video', 'custom-video-library' ) ); ?></span>
									<span class="cvl-card-play-icon" aria-hidden="true"></span>
									<?php if ( $duration > 0 ) : ?>
										<span class="cvl-duration-badge"><?php echo esc_html( gmdate( 'i:s', $duration ) ); ?></span>
									<?php endif; ?>
									<?php if ( $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
									<?php else : ?>
										<div class="cvl-card-placeholder"></div>
									<?php endif; ?>
								</div>
								<div class="cvl-card-body">
									<h2><?php the_title(); ?></h2>
									<?php if ( $excerpt ) : ?>
										<p class="cvl-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
									<?php endif; ?>
								</div>
							</a>
						</article>
					<?php } ?>
				</div>

				<div class="cvl-pagination">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => __( '&larr; Previous', 'custom-video-library' ),
								'next_text' => __( 'Next &rarr;', 'custom-video-library' ),
								'total'     => $query->max_num_pages,
								'current'   => $paged,
								'add_args'  => '' !== $selected_cat ? array( 'cvl_cat' => $selected_cat ) : array(),
							)
						)
					);
					?>
				</div>
			</div>
		</div>
		<?php
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Save viewing progress via AJAX.
	 *
	 * @return void
	 */
	public function save_progress() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! cvl_verify_nonce( $nonce, 'cvl_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		$user_id  = get_current_user_id();
		$video_id = isset( $_POST['video_id'] ) ? (int) $_POST['video_id'] : 0;
		$current  = isset( $_POST['current_seconds'] ) ? max( 0, (int) $_POST['current_seconds'] ) : 0;
		$duration = isset( $_POST['duration_seconds'] ) ? max( 0, (int) $_POST['duration_seconds'] ) : 0;

		if ( ! $user_id || ! $video_id || ! Capabilities::can_user_access_video( $user_id, $video_id ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}

		if ( $duration > 0 ) {
			$stored_duration = (int) get_post_meta( $video_id, '_cvl_duration', true );
			if ( $stored_duration <= 0 || abs( $duration - $stored_duration ) > 5 ) {
				update_post_meta( $video_id, '_cvl_duration', $duration );
			}
		}

		$completed = ( $duration > 0 && $current >= (int) floor( $duration * 0.95 ) ) ? 1 : 0;

		$saved = Database::save_user_progress( $user_id, $video_id, $current, $completed );

		if ( false === $saved ) {
			global $wpdb;
			if ( function_exists( 'cvl_log' ) ) {
				cvl_log(
					'Progress save failed',
					array(
						'user_id'          => $user_id,
						'video_id'         => $video_id,
						'current_seconds'  => $current,
						'duration_seconds' => $duration,
						'db_error'         => isset( $wpdb->last_error ) ? $wpdb->last_error : '',
					)
				);
			}

			wp_send_json_error( array( 'message' => 'Could not save progress.' ), 500 );
		}

		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Deny progress save for guests.
	 *
	 * @return void
	 */
	public function save_progress_unauthorized() {
		wp_send_json_error( array( 'message' => 'Login required.' ), 401 );
	}

	/**
	 * Toggle wishlist item for logged in users.
	 *
	 * @return void
	 */
	public function toggle_wishlist() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! cvl_verify_nonce( $nonce, 'cvl_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		$user_id  = get_current_user_id();
		$video_id = isset( $_POST['video_id'] ) ? (int) $_POST['video_id'] : 0;

		if ( ! $user_id || ! $video_id ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 400 );
		}

		$result = cvl_toggle_video_wishlist( $video_id, $user_id );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => 'Could not update wishlist.' ), 500 );
		}

		wp_send_json_success(
			array(
				'state'       => $result['state'],
				'is_wishlist' => 'added' === $result['state'],
			)
		);
	}

	/**
	 * Deny wishlist toggles for guests.
	 *
	 * @return void
	 */
	public function toggle_wishlist_unauthorized() {
		wp_send_json_error( array( 'message' => 'Login required.' ), 401 );
	}
	
	
	/**
	 * AJAX endpoint for filtering library (free, paid, or audio).
	 *
	 * @return void
	 */
	public function filter_library() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! cvl_verify_nonce( $nonce, 'cvl_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		$library_type = isset( $_POST['library_type'] ) ? sanitize_key( wp_unslash( $_POST['library_type'] ) ) : '';
		$category     = isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '';
		$page         = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;

		// Validate library type.
		if ( ! in_array( $library_type, array( 'free', 'paid', 'audio', 'all' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid library type.' ), 400 );
		}

		// Get the appropriate query args based on library type.
		if ( 'audio' === $library_type ) {
			$query_args = $this->get_audio_library_query_args( $category, $page );
			$query      = new \WP_Query( $query_args );
			} elseif ( 'all' === $library_type ) {
			$query_args = $this->get_all_videos_query_args( $category, $page );
			$query      = new \WP_Query( $query_args );
			} else {
			$is_free    = 'free' === $library_type;
			$query_args = $this->get_filtered_library_query_args( $is_free, $category, $page );
			$query      = new \WP_Query( $query_args );
		}

		// Render cards and pagination HTML.
		$grid_html       = $this->render_library_grid( $query, $library_type );
		$pagination_html = $this->render_library_pagination( $query, $library_type, $category, $page );

		wp_send_json_success(
			array(
				'html'          => $grid_html,
				'pagination'    => $pagination_html,
				'total_pages'   => $query->max_num_pages,
				'current_page'  => $page,
			)
		);
	}

	/**
	 * Build query args for all videos (general archive).
	 *
	 * @param string $category Category slug filter.
	 * @param int    $page Page number.
	 *
	 * @return array WP_Query arguments.
	 */
	private function get_all_videos_query_args( $category, $page ) {
		$posts_per_page = 12;

		$args = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $page,
		);

		if ( '' !== $category ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				array(
					'taxonomy' => 'video_category',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		return $args;
	}
	/**
	 * Build query args for free or paid library.
	 *
	 * @param bool   $is_free Whether to show free (true) or paid (false) media.
	 * @param string $category Category slug filter.
	 * @param int    $page Page number.
	 *
	 * @return array WP_Query arguments.
	 */
	private function get_filtered_library_query_args( $is_free, $category, $page ) {
		$posts_per_page = 12;
		$free_flag_value = $is_free ? 1 : 0;

		$args = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $page,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				'relation' => 'AND',
				array(
					'key'   => '_is_free_video',
					'value' => $free_flag_value,
					'type'  => 'NUMERIC',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_cvl_media_type',
						'value'   => 'audio',
						'compare' => '!=',
					),
					array(
						'key'     => '_cvl_media_type',
						'compare' => 'NOT EXISTS',
					),
				),
			),
		);

		if ( '' !== $category ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				array(
					'taxonomy' => 'video_category',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		return $args;
	}

	/**
	 * Build query args for audio library.
	 *
	 * @param string $category Category slug filter.
	 * @param int    $page Page number.
	 *
	 * @return array WP_Query arguments.
	 */
	private function get_audio_library_query_args( $category, $page ) {
		$posts_per_page = 12;

		$args = array(
			'post_type'      => 'video',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $page,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				array(
					'key'     => '_cvl_media_type',
					'value'   => 'audio',
					'compare' => '=',
				),
			),
		);

		if ( '' !== $category ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query
				array(
					'taxonomy' => 'video_category',
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}

		return $args;
	}

	/**
	 * Render library grid cards HTML.
	 *
	 * @param \WP_Query $query Query results.
	 * @param string    $library_type 'free', 'paid', or 'audio'.
	 *
	 * @return string HTML markup for cards.
	 */
	private function render_library_grid( $query, $library_type ) {
		$html = '';

		if ( ! $query->have_posts() ) {
			$html = '<p>' . esc_html__( 'No content found.', 'custom-video-library' ) . '</p>';
			return $html;
		}

		while ( $query->have_posts() ) {
			$query->the_post();
			$video_id   = get_the_ID();
			$media_type = cvl_get_media_type( $video_id );
			$thumb      = get_the_post_thumbnail_url( $video_id, 'cvl-card' );
			$duration   = (int) get_post_meta( $video_id, '_cvl_duration', true );
			$excerpt    = wp_trim_words( get_the_excerpt(), 5, '...' );

			$html .= '<article class="cvl-card">';
			$html .= '<a href="' . esc_url( get_permalink( $video_id ) ) . '" class="cvl-card-link">';
			$html .= '<div class="cvl-card-media">';
			$html .= '<span class="cvl-media-badge cvl-media-badge-' . esc_attr( $media_type ) . '">';
			$html .= esc_html( 'audio' === $media_type ? __( 'Audio', 'custom-video-library' ) : __( 'Video', 'custom-video-library' ) );
			$html .= '</span>';
			$html .= '<span class="cvl-card-play-icon" aria-hidden="true"></span>';
			if ( $duration > 0 ) {
				$html .= '<span class="cvl-duration-badge">' . esc_html( gmdate( 'i:s', $duration ) ) . '</span>';
			}
			if ( $thumb ) {
				$html .= '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy" />';
			} else {
				$html .= '<div class="cvl-card-placeholder"></div>';
			}
			$html .= '</div>';
			$html .= '<div class="cvl-card-body">';
			$html .= '<h2>' . esc_html( get_the_title() ) . '</h2>';
			if ( $excerpt ) {
				$html .= '<p class="cvl-card-excerpt">' . esc_html( $excerpt ) . '</p>';
			}
			$html .= '</div>';
			$html .= '</a>';
			$html .= '</article>';
		}

		wp_reset_postdata();
		return $html;
	}

	/**
	 * Render pagination HTML.
	 *
	 * @param \WP_Query $query Query results.
	 * @param string    $library_type 'free', 'paid', or 'audio'.
	 * @param string    $category Category slug.
	 * @param int       $page Current page.
	 *
	 * @return string HTML markup for pagination.
	 */
	private function render_library_pagination( $query, $library_type, $category, $page ) {
		if ( $query->max_num_pages <= 1 ) {
			return '';
		}

		$html = '<div class="cvl-pagination">';

		// Previous button.
		if ( $page > 1 ) {
			$html .= '<a href="#" class="cvl-pagination-link cvl-pagination-prev" data-page="' . esc_attr( $page - 1 ) . '">';
			$html .= esc_html__( '&larr; Previous', 'custom-video-library' );
			$html .= '</a>';
		}

		// Page numbers.
		for ( $i = 1; $i <= $query->max_num_pages; $i++ ) {
			if ( $i === $page ) {
				$html .= '<span class="cvl-pagination-page-num cvl-current">' . esc_html( $i ) . '</span>';
			} else {
				$html .= '<a href="#" class="cvl-pagination-link cvl-pagination-page-num" data-page="' . esc_attr( $i ) . '">';
				$html .= esc_html( $i );
				$html .= '</a>';
			}
		}

		// Next button.
		if ( $page < $query->max_num_pages ) {
			$html .= '<a href="#" class="cvl-pagination-link cvl-pagination-next" data-page="' . esc_attr( $page + 1 ) . '">';
			$html .= esc_html__( 'Next &rarr;', 'custom-video-library' );
			$html .= '</a>';
		}

		$html .= '</div>';
		return $html;
	}
}
