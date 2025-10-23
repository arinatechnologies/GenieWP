<?php
/**
 * Main class.
 * 
 * @package Codeinwp/QuickWP
 */

namespace ThemeIsle\QuickWP;

use ThemeIsle\QuickWP\API;

/**
 * Main class.
 */
class Main {
	/**
	 * API instance.
	 *
	 * @var API
	 */
	private $api;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Check if plugin is ready
		if (!$this->is_ready()) {
			return;
		}
		
		$this->register_hooks();

		$this->api = new API();
		
		// Add admin notice if needed
		add_action('admin_init', [$this, 'check_requirements']);
	}
	
	/**
	 * Check if all requirements are met
	 *
	 * @return bool
	 */
	private function is_ready() {
		// Check required extensions
		$extensions = ['curl', 'json', 'mbstring'];
		foreach ($extensions as $ext) {
			if (!extension_loaded($ext)) {
				return false;
			}
		}
		
		// Check PHP version
		if (version_compare(PHP_VERSION, '8.1', '<')) {
			return false;
		}
		
		// Check WordPress version
		if (version_compare($GLOBALS['wp_version'], '6.5', '<')) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Check requirements and show admin notices
	 *
	 * @return void
	 */
	public function check_requirements() {
		if (!$this->is_ready()) {
			add_action('admin_notices', [$this, 'requirements_notice']);
		}
	}
	
	/**
	 * Display requirements notice
	 *
	 * @return void
	 */
	public function requirements_notice() {
		if (!current_user_can('manage_options')) {
			return;
		}
		
		echo '<div class="notice notice-error">';
		echo '<p>' . esc_html__('GenieWP requires PHP 8.1 or higher, WordPress 6.5 or higher, and the curl, json, and mbstring PHP extensions.', 'quickwp') . '</p>';
		echo '</div>';
	}

	/**
	 * Register hooks and actions.
	 * 
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );

		if ( defined( 'QUICKWP_APP_GUIDED_MODE' ) && QUICKWP_APP_GUIDED_MODE ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
			add_action( 'admin_init', array( $this, 'guided_access' ) );
			add_filter( 'show_admin_bar', '__return_false' ); // phpcs:ignore WordPressVIPMinimum.UserExperience.AdminBarRemoval.RemovalDetected
		}

		add_action( 'wp_print_footer_scripts', array( $this, 'print_footer_scripts' ) );
	}

	/**
	 * Add admin menu items.
	 * 
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			'GenieWP',
			'GenieWP',
			'manage_options',
			'geniewp',
			array( $this, 'admin_page_html' ),
			'dashicons-admin-generic',
			30
		);
		
		add_submenu_page(
			'geniewp',
			'Settings',
			'Settings',
			'manage_options',
			'geniewp-settings',
			array( $this, 'settings_page_html' )
		);
	}

	/**
	 * Initialize settings.
	 * 
	 * @return void
	 */
	public function settings_init() {
		register_setting( 'geniewp_settings', 'open_ai_api_key' );
		
		add_settings_section(
			'geniewp_settings_section',
			'API Settings',
			null,
			'geniewp_settings'
		);
		
		add_settings_field(
			'open_ai_api_key',
			'OpenAI API Key',
			array( $this, 'api_key_render' ),
			'geniewp_settings',
			'geniewp_settings_section'
		);
	}

	/**
	 * Render API key field.
	 * 
	 * @return void
	 */
	public function api_key_render() {
		$api_key = get_option( 'open_ai_api_key' );
		?>
		<input type='password' name='open_ai_api_key' value='<?php echo esc_attr( $api_key ); ?>' class='regular-text'>
		<p class="description">Enter your OpenAI API key to enable AI-powered theme generation.</p>
		<?php
	}

	/**
	 * Main admin page HTML.
	 * 
	 * @return void
	 */
	public function admin_page_html() {
		?>
		<div class="wrap">
			<h1>GenieWP - AI Theme Generator</h1>
			<p>Generate full WordPress block themes from AI prompts.</p>
			
			<?php if ( ! get_option( 'open_ai_api_key' ) ) : ?>
				<div class="notice notice-warning">
					<p><?php _e( 'Please add your OpenAI API key in the Settings to enable AI generation. You can still generate a minimal theme without AI.', 'quickwp' ); ?></p>
				</div>
			<?php endif; ?>
			
			<div id="quickwp-app">
				<!-- This is where the React app will be mounted -->
			</div>
		</div>
		<?php
	}

	/**
	 * Settings page HTML.
	 * 
	 * @return void
	 */
	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'geniewp_messages', 'geniewp_updated', 'Settings Saved', 'updated' );
		}
		
		settings_errors( 'geniewp_messages' );
		
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'geniewp_settings' );
				do_settings_sections( 'geniewp_settings' );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue assets.
	 * 
	 * @return void
	 */
	public function enqueue_assets() {
		$current_screen = get_current_screen();

		if (
			! current_user_can( 'manage_options' ) ||
			! isset( $current_screen->id ) ||
			'site-editor' !== $current_screen->id
		) {
			return;
		}

		// Check if asset file exists
		$asset_file_path = QUICKWP_APP_PATH . '/build/backend/index.asset.php';
		if (!file_exists($asset_file_path)) {
			return;
		}
		
		$asset_file = include $asset_file_path;

		wp_enqueue_style(
			'quickwp',
			QUICKWP_APP_URL . 'build/backend/style-index.css',
			array( 'wp-components' ),
			$asset_file['version']
		);

		wp_enqueue_script(
			'quickwp',
			QUICKWP_APP_URL . 'build/backend/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_set_script_translations( 'quickwp', 'quickwp' );

		wp_localize_script(
			'quickwp',
			'quickwp',
			array(
				'api'          => $this->api->get_endpoint(),
				'siteUrl'      => get_site_url(),
				'themeSlug'    => get_template(),
				'isGuidedMode' => defined( 'QUICKWP_APP_GUIDED_MODE' ) && QUICKWP_APP_GUIDED_MODE,
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 * 
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		// Check if asset file exists
		$asset_file_path = QUICKWP_APP_PATH . '/build/frontend/frontend.asset.php';
		if (!file_exists($asset_file_path)) {
			return;
		}
		
		$asset_file = include $asset_file_path;

		wp_enqueue_style(
			'quickwp-frontend',
			QUICKWP_APP_URL . 'build/frontend/style-index.css',
			array(),
			$asset_file['version']
		);

		wp_enqueue_script(
			'quickwp-frontend',
			QUICKWP_APP_URL . 'build/frontend/frontend.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);
	}

	/**
	 * Print footer scripts.
	 * 
	 * @return void
	 */
	public function print_footer_scripts() {
		if ( ! is_admin() ) {
			return;
		} 

		$current_screen = get_current_screen();
	
		if (
			! current_user_can( 'manage_options' ) ||
			! isset( $current_screen->id ) ||
			'site-editor' !== $current_screen->id
		) {
			return;
		}

		?>
		<script>
			/**
			 * We use this script to adjust the vh units in the DOM to match the actual viewport height in the editor.
			 * This is necessary because the editor's viewport height is not the same as the browser's viewport height.
			 * In some blocks we use the vh unit to set the height of elements, and we need to adjust it to match the editor's viewport height.
			 */
			const adjustVHUnits = () => {
				const parentVHValue = getComputedStyle( document.documentElement).getPropertyValue( '--parent-vh' );
				const parentVH = parseInt( parentVHValue, 10 );

				if ( isNaN( parentVH ) ) {
					return;
				}

				const convertVHtoPixels = ( vhValue ) => ( vhValue / 100 ) * parentVH;

				document.querySelectorAll( '*' ).forEach( el => {
					const style = el.getAttribute( 'style' );

					if ( style && style.includes( 'vh' ) ) {
						const updatedStyle = style.replace( /(\d+(\.\d+)?)vh/g, ( match, vhValue ) => {
							const pixelValue = convertVHtoPixels( parseFloat( vhValue ) );
							return `${ pixelValue }px`;
						});

						el.setAttribute( 'style', updatedStyle );
					}
				});
			}

			document.addEventListener( 'DOMContentLoaded', adjustVHUnits );
		</script>
		<?php
	}

	/**
	 * Guided access.
	 * 
	 * @return void
	 */
	public function guided_access() {
		global $pagenow;

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX || defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( 'site-editor.php' !== $pagenow ) {
			wp_safe_redirect( admin_url( 'site-editor.php?quickwp=true&canvas=edit' ) );
			exit;
		}
	}
}