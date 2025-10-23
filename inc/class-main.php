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
		
		// Register AJAX handlers
		add_action( 'wp_ajax_geniewp_generate_theme', array( $this, 'ajax_generate_theme' ) );
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
		$api_key = get_option( 'open_ai_api_key' );
		?>
		<div class="wrap">
			<h1>GenieWP - AI Theme Generator</h1>
			<p>Generate full WordPress block themes from AI prompts.</p>
			
			<?php if ( empty( $api_key ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php 
						printf(
							__( 'OpenAI API key not configured. You can still generate a minimal theme, or <a href="%s">add your API key</a> to enable AI-powered generation.', 'geniewp' ),
							admin_url( 'admin.php?page=geniewp-settings' )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			
			<div id="geniewp-generator" style="max-width: 800px;">
				<div class="card" style="margin-top: 20px;">
					<h2 style="margin-top: 0;">Generate Your Theme</h2>
					
					<form id="geniewp-form" method="post" action="">
						<?php wp_nonce_field( 'geniewp_generate_theme', 'geniewp_nonce' ); ?>
						
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label for="site_name"><?php _e( 'Website Name / Brand', 'geniewp' ); ?> <span style="color: red;">*</span></label>
									</th>
									<td>
										<input type="text" id="site_name" name="site_name" class="regular-text" required 
											placeholder="e.g., My Awesome Site" />
										<p class="description"><?php _e( 'The name of your website or brand.', 'geniewp' ); ?></p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="business_type"><?php _e( 'Business Type / Industry', 'geniewp' ); ?> <span style="color: red;">*</span></label>
									</th>
									<td>
										<input type="text" id="business_type" name="business_type" class="regular-text" required 
											placeholder="e.g., Photography, Restaurant, Tech Startup" />
										<p class="description"><?php _e( 'What type of business or website is this?', 'geniewp' ); ?></p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="tagline"><?php _e( 'Tagline (Optional)', 'geniewp' ); ?></label>
									</th>
									<td>
										<input type="text" id="tagline" name="tagline" class="regular-text" 
											placeholder="e.g., Creating beautiful moments" />
										<p class="description"><?php _e( 'A short tagline or slogan.', 'geniewp' ); ?></p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="description"><?php _e( 'Description (Optional)', 'geniewp' ); ?></label>
									</th>
									<td>
										<textarea id="description" name="description" rows="4" class="large-text" 
											placeholder="<?php _e( 'Tell us more about your website...', 'geniewp' ); ?>"></textarea>
										<p class="description"><?php _e( 'Provide more details about your website purpose and content.', 'geniewp' ); ?></p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="primary_color"><?php _e( 'Primary Color', 'geniewp' ); ?></label>
									</th>
									<td>
										<input type="color" id="primary_color" name="primary_color" value="#2563eb" />
										<span id="primary_color_value" style="margin-left: 10px; font-family: monospace;">#2563eb</span>
										<p class="description"><?php _e( 'Choose your primary brand color.', 'geniewp' ); ?></p>
									</td>
								</tr>
								
								<tr>
									<th scope="row">
										<label for="secondary_color"><?php _e( 'Secondary Color', 'geniewp' ); ?></label>
									</th>
									<td>
										<input type="color" id="secondary_color" name="secondary_color" value="#10b981" />
										<span id="secondary_color_value" style="margin-left: 10px; font-family: monospace;">#10b981</span>
										<p class="description"><?php _e( 'Choose your secondary color.', 'geniewp' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>
						
						<p class="submit">
							<button type="submit" id="geniewp-submit-btn" class="button button-primary button-large">
								<span class="dashicons dashicons-admin-appearance" style="margin-top: 4px;"></span>
								<?php _e( 'Generate Theme', 'geniewp' ); ?>
							</button>
							<span id="geniewp-loading" style="display: none; margin-left: 15px;">
								<span class="spinner is-active" style="float: none; margin: 0;"></span>
								<span style="margin-left: 10px;"><?php _e( 'Generating your theme...', 'geniewp' ); ?></span>
							</span>
						</p>
					</form>
					
					<div id="geniewp-message" style="margin-top: 20px;"></div>
				</div>
			</div>
			
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Update color value displays
				$('#primary_color').on('input', function() {
					$('#primary_color_value').text($(this).val());
				});
				$('#secondary_color').on('input', function() {
					$('#secondary_color_value').text($(this).val());
				});
				
				// Handle form submission
				$('#geniewp-form').on('submit', function(e) {
					e.preventDefault();
					
					var $form = $(this);
					var $submitBtn = $('#geniewp-submit-btn');
					var $loading = $('#geniewp-loading');
					var $message = $('#geniewp-message');
					
					// Disable submit button and show loading
					$submitBtn.prop('disabled', true);
					$loading.show();
					$message.html('');
					
					// Prepare form data
					var formData = {
						action: 'geniewp_generate_theme',
						nonce: $('input[name="geniewp_nonce"]').val(),
						site_name: $('#site_name').val(),
						business_type: $('#business_type').val(),
						tagline: $('#tagline').val(),
						description: $('#description').val(),
						primary_color: $('#primary_color').val(),
						secondary_color: $('#secondary_color').val()
					};
					
					// Send AJAX request
					$.post(ajaxurl, formData, function(response) {
						$submitBtn.prop('disabled', false);
						$loading.hide();
						
						if (response.success) {
							$message.html(
								'<div class="notice notice-success is-dismissible"><p>' +
								'<strong>✅ Theme Generated Successfully!</strong><br>' +
								'Theme Name: <strong>' + response.data.theme_name + '</strong><br>' +
								'Theme Slug: <code>' + response.data.theme_slug + '</code><br><br>' +
								'<a href="' + response.data.activate_url + '" class="button button-primary">Activate Theme</a> ' +
								'<a href="' + response.data.customize_url + '" class="button">Customize</a>' +
								'</p></div>'
							);
							$form[0].reset();
							$('#primary_color_value').text('#2563eb');
							$('#secondary_color_value').text('#10b981');
						} else {
							$message.html(
								'<div class="notice notice-error is-dismissible"><p>' +
								'<strong>❌ Error:</strong> ' + response.data.message +
								'</p></div>'
							);
						}
					}).fail(function() {
						$submitBtn.prop('disabled', false);
						$loading.hide();
						$message.html(
							'<div class="notice notice-error is-dismissible"><p>' +
							'<strong>❌ Error:</strong> An unexpected error occurred. Please try again.' +
							'</p></div>'
						);
					});
				});
			});
			</script>
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
/**
 * AJAX handler for theme generation
 *
 * @return void
 */
public function ajax_generate_theme() {
// Verify nonce
if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'geniewp_generate_theme' ) ) {
wp_send_json_error( array(
'message' => __( 'Security check failed. Please refresh the page and try again.', 'geniewp' ),
) );
}

// Check user permissions
if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array(
'message' => __( 'You do not have permission to generate themes.', 'geniewp' ),
) );
}

// Get form data
$form_data = array(
'site_name'       => sanitize_text_field( $_POST['site_name'] ?? '' ),
'business_type'   => sanitize_text_field( $_POST['business_type'] ?? '' ),
'tagline'         => sanitize_text_field( $_POST['tagline'] ?? '' ),
'description'     => sanitize_textarea_field( $_POST['description'] ?? '' ),
'primary_color'   => sanitize_hex_color( $_POST['primary_color'] ?? '#2563eb' ),
'secondary_color' => sanitize_hex_color( $_POST['secondary_color'] ?? '#10b981' ),
);

// Validate required fields
if ( empty( $form_data['site_name'] ) || empty( $form_data['business_type'] ) ) {
wp_send_json_error( array(
'message' => __( 'Please fill in all required fields (Site Name and Business Type).', 'geniewp' ),
) );
}

// Generate theme
$generator = new Theme_Generator();
$result = $generator->generate_theme( $form_data );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array(
'message' => $result->get_error_message(),
) );
}

// Send success response
wp_send_json_success( array(
'message'    => sprintf(
__( 'Theme "%s" created successfully!', 'geniewp' ),
$result['theme_name']
),
'theme_slug' => $result['theme_slug'],
'theme_name' => $result['theme_name'],
'activate_url'  => admin_url( 'themes.php' ),
			'customize_url' => admin_url( 'customize.php?theme=' . $result['theme_slug'] ),
) );
}
}
