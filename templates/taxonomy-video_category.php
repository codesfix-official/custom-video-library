<?php
/**
 * Taxonomy template for video_category.
 *
 * @package CustomVideoLibrary
 */

get_header();

$term   = get_queried_object();
$user_id = get_current_user_id();
?>
<main class="cvl-page cvl-taxonomy-page">
	<section class="cvl-hero">
		<div class="cvl-shell">
			<p class="cvl-kicker"><?php esc_html_e( 'Category', 'custom-video-library' ); ?></p>
			<h1><?php echo esc_html( single_term_title( '', false ) ); ?></h1>
			<?php if ( ! empty( $term->description ) ) : ?>
				<p><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="cvl-shell cvl-content-section">
		<div class="cvl-chip-row">
			<a class="cvl-chip" href="<?php echo esc_url( get_post_type_archive_link( 'video' ) ); ?>"><?php esc_html_e( 'All Media', 'custom-video-library' ); ?></a>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="cvl-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php
					$video_id  = get_the_ID();
					$media_type = cvl_get_media_type( $video_id );
					$thumb     = get_the_post_thumbnail_url( $video_id, 'cvl-card' );
					$duration  = (int) get_post_meta( $video_id, '_cvl_duration', true );
					$excerpt   = wp_trim_words( get_the_excerpt(), 5, '...' );
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
			<div class="cvl-pagination"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p><?php esc_html_e( 'No videos in this category.', 'custom-video-library' ); ?></p>
		<?php endif; ?>
	</section>
</main>
<?php
get_footer();
