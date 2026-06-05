<?php
/**
 * Archive template for Video post type.
 *
 * @package CustomVideoLibrary
 */

get_header();

$user_id = get_current_user_id();
?>
<main class="cvl-page cvl-archive-page"  data-library-type="all">
	<section class="cvl-hero">
		<div class="cvl-shell">
			<h1><?php post_type_archive_title(); ?></h1>
			<p><?php esc_html_e( 'Explore all titles. Unlock videos individually or with a subscription.', 'custom-video-library' ); ?></p>
		</div>
	</section>

	<section class="cvl-shell cvl-content-section">
		<?php
		$terms = get_terms(
			array(
				'taxonomy'   => 'video_category',
				'hide_empty' => true,
			)
		);
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) :
			?>
			<div class="cvl-chip-row">
				<a class="cvl-chip is-active" href="<?php echo esc_url( get_post_type_archive_link( 'video' ) ); ?>">
					<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
					<?php esc_html_e( 'All', 'custom-video-library' ); ?>
				</a>
				<?php foreach ( $terms as $term ) : ?>
					<a class="cvl-chip" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
						<svg class="cvl-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
						<?php echo esc_html( $term->name ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="cvl-grid" id="cvl-library-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					$video_id     = get_the_ID();
					$media_type   = cvl_get_media_type( $video_id );
					$thumb        = get_the_post_thumbnail_url( $video_id, 'cvl-card' );
					$duration     = (int) get_post_meta( $video_id, '_cvl_duration', true );
					$excerpt      = wp_trim_words( get_the_excerpt(), 5, '...' );
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
				<?php endwhile; ?>
			</div>

			<div class="cvl-pagination" id="cvl-library-pagination">
				<?php the_posts_pagination(); ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No videos found.', 'custom-video-library' ); ?></p>
		<?php endif; ?>
	</section>
</main>
<?php
get_footer();
