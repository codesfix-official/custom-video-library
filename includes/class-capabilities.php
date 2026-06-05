<?php
/**
 * User Capabilities and Roles
 *
 * @package CustomVideoLibrary
 */

namespace CustomVideoLibrary;

/**
 * Class for managing user capabilities and roles.
 */
class Capabilities {

	/**
	 * Initialize capabilities.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_capabilities' ) );
	}

	/**
	 * Register custom capabilities for the 'video' post type.
	 *
	 * @return void
	 */
	public function register_capabilities() {
		// Get all roles that should have video management capabilities.
		$roles = array( 'administrator', 'editor' );

		// Video management capabilities.
		$video_capabilities = array(
			'edit_videos'             => true,
			'edit_others_videos'      => true,
			'edit_published_videos'   => true,
			'publish_videos'          => true,
			'delete_videos'           => true,
			'delete_others_videos'    => true,
			'delete_published_videos' => true,
			'read_private_videos'     => true,
			'manage_video_categories' => true,
			'manage_video_genres'     => true,
		);

		// Grant capabilities to roles.
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $video_capabilities as $capability => $grant ) {
					// Only write to the DB when the capability is not yet set.
					if ( ! $role->has_cap( $capability ) ) {
						$role->add_cap( $capability, $grant );
					}
				}
			}
		}

		// Custom video viewer capabilities for subscribers.
		$subscriber = get_role( 'subscriber' );
		if ( $subscriber && ! $subscriber->has_cap( 'read_video' ) ) {
			// Subscribers can read videos based on their membership.
			$subscriber->add_cap( 'read_video', true );
		}
	}

	/**
	 * Check if a user can access a specific video.
	 *
	 * This function checks:
	 * 1. If the user owns the video
	 * 2. If the user has purchased the video via WooCommerce
	 * 3. If the user has an active subscription
	 * 4. If the user has a WooMembership that includes video access
	 *
	 * @param int $user_id The user ID.
	 * @param int $video_id The video post ID.
	 *
	 * @return bool True if user can access, false otherwise.
	 */
	public static function can_user_access_video( $user_id, $video_id ) {
		// Check if user is admin or editor.
		if ( user_can( $user_id, 'edit_videos' ) ) {
			return true;
		}

		// Get video data.
		$video = get_post( $video_id );
		if ( ! $video || 'video' !== $video->post_type ) {
			return false;
		}

		// Check if video is free (no purchase required).
		// Free content is accessible to everyone — guests and subscribers alike.
		$is_free = cvl_is_video_free( $video_id );
		if ( $is_free ) {
			return true;
		}

		// If the video has no paid gates configured, treat it as public content.
		if ( ! cvl_video_has_paid_gate( $video_id ) ) {
			return true;
		}

		// Check if user is the video owner.
		if ( (int) $video->post_author === (int) $user_id ) {
			return true;
		}

		// Check WooCommerce purchase.
		if ( function_exists( 'wc_get_product' ) ) {
			//$product_id = (int) get_post_meta( $video_id, '_wc_product_id', true );
			$product_id = cvl_get_video_product_id( $video_id );
			if ( $product_id > 0 && cvl_user_has_purchased_product( $user_id, $product_id ) ) {
				return true;
			}

			// Check WooCommerce subscription.
			$subscription_ids = cvl_get_video_subscription_ids( $video_id );
			foreach ( $subscription_ids as $subscription_id ) {
				if ( cvl_user_has_active_subscription( $user_id, (int) $subscription_id ) ) {
					return true;
				}
			}
			// Fallback for stores using one global recurring plan (e.g. yearly):
			// if the user has any active subscription, grant access to gated media.
			if ( cvl_user_has_any_active_subscription( $user_id ) ) {
				return true;
			}
		}

		// Check WooMembership access.
		if ( function_exists( 'wc_memberships_is_user_member' ) ) {
			$membership_plans = cvl_get_video_membership_plans( $video_id );
			foreach ( $membership_plans as $membership_plan ) {
				if ( wc_memberships_is_user_member( $user_id, $membership_plan ) ) {
					return true;
				}
			}
			// Fallback for default WooCommerce Memberships setups where access is
			// managed by active membership status rather than per-video plan IDs.
			if ( function_exists( 'wc_memberships_is_user_active_member' ) && wc_memberships_is_user_active_member( $user_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the access level for a video.
	 *
	 * Returns: 'free', 'purchase', 'subscription', 'membership', or 'restricted'.
	 *
	 * @param int $video_id The video post ID.
	 *
	 * @return string The access level.
	 */
	public static function get_video_access_level( $video_id ) {
		$is_free = cvl_is_video_free( $video_id );
		if ( $is_free ) {
			return 'free';
		}

		//$product_id = (int) get_post_meta( $video_id, '_wc_product_id', true );
		$product_id = cvl_get_video_product_id( $video_id );
		if ( $product_id > 0 ) {
			return 'purchase';
		}

		$subscription_ids = cvl_get_video_subscription_ids( $video_id );
		if ( ! empty( $subscription_ids ) ) {
			return 'subscription';
		}

		$membership_plans = cvl_get_video_membership_plans( $video_id );
		if ( ! empty( $membership_plans ) ) {
			return 'membership';
		}

		return 'restricted';
	}
}
