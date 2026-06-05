<?php
/**
 * Single template for Video.
 *
 * @package CustomVideoLibrary
 */

get_header();

while ( have_posts() ) :
	the_post();

	$video_id         = get_the_ID();
	$user_id          = get_current_user_id();
	$media_type       = cvl_get_media_type( $video_id );
	$is_audio         = 'audio' === $media_type;
	$is_free_content  = cvl_is_video_free( $video_id );
	$can_play         = $is_free_content || ! cvl_video_has_paid_gate( $video_id ) || ( $user_id && \CustomVideoLibrary\Capabilities::can_user_access_video( $user_id, $video_id ) );
	$video_tags_terms = get_the_terms( $video_id, 'video_genre' );
	$video_cat_terms  = get_the_terms( $video_id, 'video_category' );
	$is_wishlist      = $user_id ? cvl_is_video_in_user_wishlist( $video_id, $user_id ) : false;
	$login_url        = function_exists( 'wc_get_page_permalink' )
		? wc_get_page_permalink( 'myaccount' )
		: wp_login_url( get_permalink( $video_id ) );

	$video_tags = ( ! is_wp_error( $video_tags_terms ) && ! empty( $video_tags_terms ) )
		? wp_list_pluck( $video_tags_terms, 'name' )
		: array();

	$video_categories = ( ! is_wp_error( $video_cat_terms ) && ! empty( $video_cat_terms ) )
		? wp_list_pluck( $video_cat_terms, 'name' )
		: array();
	?>
	<main class="cvl-page cvl-single-page">
		<section class="cvl-shell cvl-single-layout">
			<div class="cvl-single-main">
				<h2><?php the_title(); ?></h2>

				<div class="cvl-player-area">
					<?php echo do_shortcode( '[cvl_video_player id="' . (int) $video_id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<div class="cvl-single-meta">
					<span class="cvl-pill <?php echo $can_play ? 'is-open' : 'is-locked'; ?>">
						<?php if ( $can_play ) : ?>
						<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
						<?php else : ?>
						<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
						<?php endif; ?>
						<?php echo esc_html( $can_play ? __( 'Unlocked', 'custom-video-library' ) : __( 'Locked', 'custom-video-library' ) ); ?>
					</span>

					<?php if ( ! empty( $video_tags_terms ) && ! is_wp_error( $video_tags_terms ) ) : ?>
						<span class="cvl-meta-icon-group">
							<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-label="<?php esc_attr_e( 'Genres', 'custom-video-library' ); ?>" title="<?php esc_attr_e( 'Genres', 'custom-video-library' ); ?>"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>
							<?php foreach ( $video_tags_terms as $term ) : ?>
								<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="cvl-term-chip"><?php echo esc_html( $term->name ); ?></a>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>

					<?php if ( ! empty( $video_cat_terms ) && ! is_wp_error( $video_cat_terms ) ) : ?>
						<span class="cvl-meta-icon-group">
							<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-label="<?php esc_attr_e( 'Categories', 'custom-video-library' ); ?>" title="<?php esc_attr_e( 'Categories', 'custom-video-library' ); ?>"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
							<?php foreach ( $video_cat_terms as $term ) : ?>
								<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="cvl-term-chip"><?php echo esc_html( $term->name ); ?></a>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>

					<?php if ( $user_id ) : ?>
						<button
							type="button"
							class="cvl-wishlist-toggle cvl-wishlist-inline<?php echo $is_wishlist ? ' is-active' : ''; ?>"
							data-video-id="<?php echo esc_attr( $video_id ); ?>"
							aria-label="<?php echo esc_attr( $is_wishlist ? __( 'Remove from Wishlist', 'custom-video-library' ) : __( 'Add to Wishlist', 'custom-video-library' ) ); ?>"
						>
							<svg class="cvl-icon cvl-heart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
							<span class="cvl-wishlist-label"><?php echo esc_html( $is_wishlist ? __( 'Remove from Wishlist', 'custom-video-library' ) : __( 'Add to Wishlist', 'custom-video-library' ) ); ?></span>
						</button>
					<?php else : ?>
						<a class="cvl-wishlist-login-hint" href="<?php echo esc_url( wp_login_url( get_permalink( $video_id ) ) ); ?>">
							<svg class="cvl-icon cvl-heart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
							<?php esc_html_e( 'Log in to Wishlist', 'custom-video-library' ); ?>
						</a>
					<?php endif; ?>
				</div>

				<div class="cvl-single-content">
					<?php the_content(); ?>
				</div>
			</div>

			<aside class="cvl-single-side">
				<?php if ( ! $can_play ) : ?>
					<div class="cvl-side-card">
						<h3><?php esc_html_e( 'Access', 'custom-video-library' ); ?></h3>
						<p>
							<?php
							if ( ! $user_id ) {
								echo esc_html( $is_audio ? __( 'Log in to buy this audio individually or get a subscription.', 'custom-video-library' ) : __( 'Log in to buy this video individually or get a subscription.', 'custom-video-library' ) );
							} else {
								echo esc_html( $is_audio ? __( 'Buy this audio individually or get a subscription.', 'custom-video-library' ) : __( 'Buy this video individually or get a subscription.', 'custom-video-library' ) );
							}
							?>
						</p>
						<a class="cvl-cta" href="<?php echo esc_url( cvl_get_video_purchase_url( $video_id ) ); ?>"><?php echo esc_html( cvl_get_video_access_cta_label( $video_id ) ); ?></a>
						<?php if ( ! $user_id ) : ?>
							<a class="cvl-cta cvl-cta-secondary" href="<?php echo esc_url( wp_login_url( get_permalink( $video_id ) ) ); ?>"><?php esc_html_e( 'Log In', 'custom-video-library' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="cvl-side-card">
					<h3><?php esc_html_e( 'Need your list?', 'custom-video-library' ); ?></h3>
					<p><?php esc_html_e( 'Open your private page to continue unfinished videos, see purchased titles, and manage your wishlist.', 'custom-video-library' ); ?></p>
				</div>
			</aside>
		</section>
	</main>
<?php endwhile; ?>
<?php
get_footer();
