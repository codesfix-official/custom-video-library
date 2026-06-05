<?php
/**
 * Admin Pages and Settings
 *
 * @package CustomVideoLibrary\Admin
 */

namespace CustomVideoLibrary\Admin;

/**
 * Class for managing admin pages and settings.
 */
class Admin {

	/**
	 * Initialize admin functionality.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		// Add settings page under Videos menu.
		add_submenu_page(
			'edit.php?post_type=video',
			esc_html__( 'Content Library Settings', 'custom-video-library' ),
			esc_html__( 'Settings', 'custom-video-library' ),
			'manage_options',
			'cvl-settings',
			array( $this, 'render_settings_page' )
		);

		// Add analytics page.
		add_submenu_page(
			'edit.php?post_type=video',
			esc_html__( 'Content Analytics', 'custom-video-library' ),
			esc_html__( 'Analytics', 'custom-video-library' ),
			'view_video_analytics',
			'cvl-analytics',
			array( $this, 'render_analytics_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		// Register settings section.
		register_setting(
			'cvl_settings',
			'cvl_video_player_type',
			array(
				'type'              => 'string',
				'default'           => 'html5',
				'sanitize_callback' => static function( $value ) {
					$allowed = array( 'html5', 'hls', 'dash' );
					return in_array( $value, $allowed, true ) ? $value : 'html5';
				},
			)
		);
		register_setting(
			'cvl_settings',
			'cvl_enable_analytics',
			array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			'cvl_settings',
			'cvl_default_access_level',
			array(
				'type'              => 'string',
				'default'           => 'restricted',
				'sanitize_callback' => static function( $value ) {
					$allowed = array( 'free', 'purchase', 'subscription', 'membership', 'restricted' );
					return in_array( $value, $allowed, true ) ? $value : 'restricted';
				},
			)
		);
		register_setting(
			'cvl_settings',
			'cvl_video_storage_path',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		add_settings_section(
			'cvl_general_settings',
			esc_html__( 'General Settings', 'custom-video-library' ),
			array( $this, 'render_general_settings_section' ),
			'cvl_settings'
		);

		add_settings_field(
			'cvl_video_player_type',
			esc_html__( 'Video Player Type', 'custom-video-library' ),
			array( $this, 'render_player_type_field' ),
			'cvl_settings',
			'cvl_general_settings'
		);

		add_settings_field(
			'cvl_enable_analytics',
			esc_html__( 'Enable Analytics', 'custom-video-library' ),
			array( $this, 'render_analytics_checkbox' ),
			'cvl_settings',
			'cvl_general_settings'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'custom-video-library' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Custom Content Library Settings', 'custom-video-library' ); ?></h1>
			<form method="post" action="options.php">
				<?php
					settings_fields( 'cvl_settings' );
					do_settings_sections( 'cvl_settings' );
					submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general settings section.
	 *
	 * @return void
	 */
	public function render_general_settings_section() {
		echo '<p>' . esc_html__( 'Configure general settings for the Custom Video Library plugin.', 'custom-video-library' ) . '</p>';
	}

	/**
	 * Render player type field.
	 *
	 * @return void
	 */
	public function render_player_type_field() {
		$player_type = get_option( 'cvl_video_player_type', 'html5' );
		?>
		<select name="cvl_video_player_type">
			<option value="html5" <?php selected( $player_type, 'html5' ); ?>>
				<?php echo esc_html__( 'HTML5 Player', 'custom-video-library' ); ?>
			</option>
			<option value="hls" <?php selected( $player_type, 'hls' ); ?>>
				<?php echo esc_html__( 'HLS Streaming', 'custom-video-library' ); ?>
			</option>
			<option value="dash" <?php selected( $player_type, 'dash' ); ?>>
				<?php echo esc_html__( 'DASH Streaming', 'custom-video-library' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render analytics checkbox.
	 *
	 * @return void
	 */
	public function render_analytics_checkbox() {
		$enable = get_option( 'cvl_enable_analytics', 1 );
		?>
		<input type="checkbox" name="cvl_enable_analytics" value="1" <?php checked( $enable, 1 ); ?> />
		<label>
			<?php echo esc_html__( 'Enable detailed analytics tracking for videos', 'custom-video-library' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the analytics page.
	 *
	 * @return void
	 */
	public function render_analytics_page() {
		if ( ! current_user_can( 'view_video_analytics' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'custom-video-library' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Content Analytics', 'custom-video-library' ); ?></h1>
			<p><?php echo esc_html__( 'Content analytics are coming soon.', 'custom-video-library' ); ?></p>
		</div>
		<?php
	}
}
