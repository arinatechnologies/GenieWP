<?php
/**
 * Theme Generator class.
 * 
 * @package GenieWP
 */

namespace ThemeIsle\QuickWP;

/**
 * Theme Generator class.
 */
class Theme_Generator {
	
	/**
	 * Generate theme from form data
	 *
	 * @param array $data Form data containing site_name, business_type, etc.
	 * @return array|\WP_Error Theme generation result or error
	 */
	public function generate_theme( $data ) {
		// Sanitize and validate input
		$site_name = sanitize_text_field( $data['site_name'] ?? '' );
		$business_type = sanitize_text_field( $data['business_type'] ?? '' );
		$tagline = sanitize_text_field( $data['tagline'] ?? '' );
		$description = sanitize_textarea_field( $data['description'] ?? '' );
		$primary_color = sanitize_hex_color( $data['primary_color'] ?? '#2563eb' );
		$secondary_color = sanitize_hex_color( $data['secondary_color'] ?? '#10b981' );
		
		if ( empty( $site_name ) || empty( $business_type ) ) {
			return new \WP_Error( 'missing_fields', __( 'Site name and business type are required.', 'geniewp' ) );
		}
		
		// Generate theme slug
		$theme_slug = $this->generate_theme_slug( $site_name );
		
		// Check if OpenAI API is available
		$api_key = get_option( 'open_ai_api_key' );
		
		if ( ! empty( $api_key ) ) {
			// Try AI-powered generation
			$ai_result = $this->generate_with_ai( $api_key, $data, $theme_slug );
			
			if ( ! is_wp_error( $ai_result ) ) {
				return $ai_result;
			}
			
			// If AI fails, log error and fall back to basic theme
			error_log( 'GenieWP AI generation failed: ' . $ai_result->get_error_message() );
		}
		
		// Generate basic theme (fallback or if no API key)
		return $this->generate_enhanced_theme( $site_name, $business_type, $tagline, $description, $primary_color, $secondary_color, $theme_slug, null );
	}
	
	/**
	 * Generate theme slug from site name
	 *
	 * @param string $site_name Site name.
	 * @return string Theme slug
	 */
	private function generate_theme_slug( $site_name ) {
		$base_slug = sanitize_title( $site_name );
		$theme_slug = 'geniewp-' . $base_slug;
		
		// Ensure unique slug
		$counter = 1;
		$original_slug = $theme_slug;
		while ( wp_get_theme( $theme_slug )->exists() ) {
			$theme_slug = $original_slug . '-' . $counter;
			$counter++;
		}
		
		return $theme_slug;
	}
	
	/**
	 * Generate theme using OpenAI API
	 *
	 * @param string $api_key OpenAI API key.
	 * @param array  $data Form data.
	 * @param string $theme_slug Theme slug.
	 * @return array|\WP_Error
	 */
	private function generate_with_ai( $api_key, $data, $theme_slug ) {
		// Create a prompt for AI
		$prompt = $this->build_ai_prompt( $data );
		
		// Call OpenAI API (using gpt-4 or gpt-3.5-turbo)
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'model'       => 'gpt-3.5-turbo',
					'messages'    => array(
						array(
							'role'    => 'system',
							'content' => 'You are a WordPress block theme expert. Generate theme.json configuration and HTML templates based on user requirements.',
						),
						array(
							'role'    => 'user',
							'content' => $prompt,
						),
					),
					'temperature' => 0.7,
					'max_tokens'  => 3000,
				) ),
				'timeout' => 30,
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( isset( $body['error'] ) ) {
			return new \WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown API error' );
		}
		
		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			return new \WP_Error( 'invalid_response', 'Invalid API response' );
		}
		
		$ai_content = $body['choices'][0]['message']['content'];
		
		// Parse AI response and create theme
		return $this->create_theme_from_ai_response( $ai_content, $data, $theme_slug );
	}
	
	/**
	 * Build AI prompt
	 *
	 * @param array $data Form data.
	 * @return string
	 */
	private function build_ai_prompt( $data ) {
		$theme_slug_suggestion = sanitize_title( $data['site_name'] );
		
		$prompt = <<<PROMPT
You are an expert WordPress block theme designer. Create a comprehensive, professional theme structure.

Website Details:
- Name: {$data['site_name']}
- Business Type: {$data['business_type']}
- Tagline: {$data['tagline']}
- Description: {$data['description']}
- Primary Color: {$data['primary_color']}
- Secondary Color: {$data['secondary_color']}

Generate a complete, professional WordPress block theme with the following requirements:

1. **Color Palette**: Create a harmonious 8-color palette based on the primary and secondary colors, including:
   - Primary and secondary colors
   - Complementary accent colors
   - Neutral colors (white, black, light gray, dark gray)
   - Background and surface colors

2. **Typography**: Suggest:
   - Modern, web-safe font pairings (heading + body fonts)
   - Font size scale (6 sizes from small to 2xl)
   - Line heights and letter spacing

3. **Layout Sections**: Provide content suggestions for:
   - Hero section with compelling headline and call-to-action
   - Services/Features section (3-4 items)
   - About Us section
   - Testimonials or social proof
   - Call-to-action section
   - Footer with 3 widget areas

4. **Navigation**: Suggest menu items appropriate for {$data['business_type']} (Home, About, Services, Blog, Contact, etc.)

5. **Design Style**: Modern, clean, professional, mobile-responsive

Please respond with a JSON object in this exact structure:
{
  "theme_name": "{$data['site_name']}",
  "theme_slug": "geniewp-{$theme_slug_suggestion}",
  "colors": [
    {"name": "Primary", "slug": "primary", "color": "{$data['primary_color']}"},
    {"name": "Secondary", "slug": "secondary", "color": "{$data['secondary_color']}"},
    ...more colors
  ],
  "typography": {
    "headingFont": "Font name",
    "bodyFont": "Font name",
    "fontSizes": [...]
  },
  "content": {
    "hero": {
      "headline": "...",
      "subheadline": "...",
      "ctaText": "..."
    },
    "services": [
      {"title": "...", "description": "..."},
      ...3-4 items
    ],
    "about": {
      "heading": "...",
      "content": "..."
    },
    "cta": {
      "heading": "...",
      "text": "...",
      "buttonText": "..."
    }
  },
  "navigation": ["Home", "About", "Services", "Blog", "Contact"]
}

Return ONLY the JSON, no explanations.
PROMPT;

		return $prompt;
	}
	
	/**
	 * Create theme from AI response
	 *
	 * @param string $ai_content AI response content.
	 * @param array  $data Form data.
	 * @param string $theme_slug Theme slug.
	 * @return array|\WP_Error
	 */
	private function create_theme_from_ai_response( $ai_content, $data, $theme_slug ) {
		// Try to parse JSON from AI response
		$ai_data = json_decode( $ai_content, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Try to extract JSON from markdown code blocks
			if ( preg_match( '/```json\s*(.*?)\s*```/s', $ai_content, $matches ) ) {
				$ai_data = json_decode( $matches[1], true );
			}
		}
		
		// If we have valid AI data, use it to enhance the theme
		if ( is_array( $ai_data ) && ! empty( $ai_data ) ) {
			return $this->generate_enhanced_theme(
				$data['site_name'],
				$data['business_type'],
				$data['tagline'],
				$data['description'],
				$data['primary_color'],
				$data['secondary_color'],
				$theme_slug,
				$ai_data
			);
		}
		
		// Fallback to basic enhanced theme
		return $this->generate_enhanced_theme(
			$data['site_name'],
			$data['business_type'],
			$data['tagline'],
			$data['description'],
			$data['primary_color'],
			$data['secondary_color'],
			$theme_slug,
			null
		);
	}
	
	/**
	 * Generate enhanced professional theme
	 *
	 * @param string $site_name Site name.
	 * @param string $business_type Business type.
	 * @param string $tagline Tagline.
	 * @param string $description Description.
	 * @param string $primary_color Primary color.
	 * @param string $secondary_color Secondary color.
	 * @param string $theme_slug Theme slug.
	 * @param array|null $ai_data Optional AI-generated data.
	 * @return array|\WP_Error
	 */
	private function generate_enhanced_theme( $site_name, $business_type, $tagline, $description, $primary_color, $secondary_color, $theme_slug, $ai_data = null ) {
		// Create theme directory
		$themes_dir = get_theme_root();
		$theme_dir = $themes_dir . '/' . $theme_slug;
		
		if ( file_exists( $theme_dir ) ) {
			return new \WP_Error( 'theme_exists', __( 'A theme with this name already exists.', 'geniewp' ) );
		}
		
		// Create directories
		wp_mkdir_p( $theme_dir );
		wp_mkdir_p( $theme_dir . '/templates' );
		wp_mkdir_p( $theme_dir . '/parts' );
		wp_mkdir_p( $theme_dir . '/patterns' );
		wp_mkdir_p( $theme_dir . '/assets' );
		wp_mkdir_p( $theme_dir . '/assets/css' );
		
		// Extract AI data or use defaults
		$theme_data = $this->prepare_theme_data( $site_name, $business_type, $tagline, $description, $primary_color, $secondary_color, $ai_data );
		
		// Generate theme files
		$this->create_style_css( $theme_dir, $site_name, $tagline, $description );
		$this->create_functions_php( $theme_dir, $theme_data );
		$this->create_enhanced_theme_json( $theme_dir, $theme_data );
		$this->create_custom_css( $theme_dir );
		$this->create_enhanced_index_template( $theme_dir, $theme_data );
		$this->create_enhanced_front_page_template( $theme_dir, $theme_data );
		$this->create_page_template( $theme_dir, $theme_data );
		$this->create_single_template( $theme_dir, $theme_data );
		$this->create_enhanced_header_part( $theme_dir, $theme_data );
		$this->create_enhanced_footer_part( $theme_dir, $theme_data );
		$this->create_patterns( $theme_dir, $theme_data );
		$this->create_readme( $theme_dir, $site_name, $description );
		
		return array(
			'success'    => true,
			'theme_slug' => $theme_slug,
			'theme_name' => $site_name,
			'theme_dir'  => $theme_dir,
		);
	}
	
	/**
	 * Prepare theme data from AI response or defaults
	 *
	 * @param string $site_name Site name.
	 * @param string $business_type Business type.
	 * @param string $tagline Tagline.
	 * @param string $description Description.
	 * @param string $primary_color Primary color.
	 * @param string $secondary_color Secondary color.
	 * @param array|null $ai_data AI-generated data.
	 * @return array
	 */
	private function prepare_theme_data( $site_name, $business_type, $tagline, $description, $primary_color, $secondary_color, $ai_data ) {
		$data = array(
			'site_name'      => $site_name,
			'business_type'  => $business_type,
			'tagline'        => $tagline ?: "Your trusted partner for $business_type",
			'description'    => $description ?: "A professional $business_type website",
			'colors'         => $this->get_color_palette( $primary_color, $secondary_color, $ai_data ),
			'typography'     => $this->get_typography( $ai_data ),
			'content'        => $this->get_default_content( $site_name, $business_type, $ai_data ),
			'navigation'     => $this->get_navigation_items( $business_type, $ai_data ),
		);
		
		return $data;
	}
	
	/**
	 * Get color palette
	 *
	 * @param string $primary_color Primary color.
	 * @param string $secondary_color Secondary color.
	 * @param array|null $ai_data AI data.
	 * @return array
	 */
	private function get_color_palette( $primary_color, $secondary_color, $ai_data ) {
		if ( ! empty( $ai_data['colors'] ) && is_array( $ai_data['colors'] ) ) {
			return $ai_data['colors'];
		}
		
		// Default professional color palette
		return array(
			array( 'name' => 'Primary', 'slug' => 'primary', 'color' => $primary_color ),
			array( 'name' => 'Secondary', 'slug' => 'secondary', 'color' => $secondary_color ),
			array( 'name' => 'Accent', 'slug' => 'accent', 'color' => '#f59e0b' ),
			array( 'name' => 'White', 'slug' => 'white', 'color' => '#ffffff' ),
			array( 'name' => 'Black', 'slug' => 'black', 'color' => '#000000' ),
			array( 'name' => 'Light Gray', 'slug' => 'light-gray', 'color' => '#f3f4f6' ),
			array( 'name' => 'Gray', 'slug' => 'gray', 'color' => '#6b7280' ),
			array( 'name' => 'Dark Gray', 'slug' => 'dark-gray', 'color' => '#1f2937' ),
		);
	}
	
	/**
	 * Get typography settings
	 *
	 * @param array|null $ai_data AI data.
	 * @return array
	 */
	private function get_typography( $ai_data ) {
		if ( ! empty( $ai_data['typography'] ) ) {
			return $ai_data['typography'];
		}
		
		return array(
			'headingFont' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			'bodyFont'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
		);
	}
	
	/**
	 * Get default content
	 *
	 * @param string $site_name Site name.
	 * @param string $business_type Business type.
	 * @param array|null $ai_data AI data.
	 * @return array
	 */
	private function get_default_content( $site_name, $business_type, $ai_data ) {
		if ( ! empty( $ai_data['content'] ) ) {
			return $ai_data['content'];
		}
		
		return array(
			'hero'     => array(
				'headline'    => "Welcome to $site_name",
				'subheadline' => "Your trusted partner for professional $business_type services",
				'ctaText'     => 'Get Started Today',
			),
			'services' => array(
				array(
					'title'       => 'Professional Service',
					'description' => 'We deliver high-quality solutions tailored to your needs.',
				),
				array(
					'title'       => 'Expert Team',
					'description' => 'Our experienced professionals are dedicated to your success.',
				),
				array(
					'title'       => 'Proven Results',
					'description' => 'Track record of excellence and customer satisfaction.',
				),
				array(
					'title'       => 'Support & Care',
					'description' => 'Ongoing support to ensure your continued success.',
				),
			),
			'about'    => array(
				'heading' => "About $site_name",
				'content' => "We are a leading provider of $business_type services, committed to delivering exceptional results. Our team combines expertise, innovation, and dedication to help you achieve your goals.",
			),
			'cta'      => array(
				'heading'    => 'Ready to Get Started?',
				'text'       => "Let's work together to bring your vision to life. Contact us today for a consultation.",
				'buttonText' => 'Contact Us Now',
			),
		);
	}
	
	/**
	 * Get navigation items
	 *
	 * @param string $business_type Business type.
	 * @param array|null $ai_data AI data.
	 * @return array
	 */
	private function get_navigation_items( $business_type, $ai_data ) {
		if ( ! empty( $ai_data['navigation'] ) && is_array( $ai_data['navigation'] ) ) {
			return $ai_data['navigation'];
		}
		
		return array( 'Home', 'About', 'Services', 'Blog', 'Contact' );
	}
	
	/**
	 * Create style.css file
	 *
	 * @param string $theme_dir Theme directory.
	 * @param string $site_name Site name.
	 * @param string $tagline Tagline.
	 * @param string $description Description.
	 * @return void
	 */
	private function create_style_css( $theme_dir, $site_name, $tagline, $description ) {
		$content = "/*\n";
		$content .= "Theme Name: " . $site_name . "\n";
		$content .= "Theme URI: https://example.com\n";
		$content .= "Author: GenieWP\n";
		$content .= "Author URI: https://example.com\n";
		$content .= "Description: " . ( ! empty( $description ) ? $description : 'A custom WordPress block theme generated by GenieWP.' ) . "\n";
		$content .= "Version: 1.0.0\n";
		$content .= "Requires at least: 6.5\n";
		$content .= "Tested up to: 6.7\n";
		$content .= "Requires PHP: 7.4\n";
		$content .= "License: GNU General Public License v2 or later\n";
		$content .= "License URI: https://www.gnu.org/licenses/gpl-2.0.html\n";
		$content .= "Text Domain: " . sanitize_title( $site_name ) . "\n";
		if ( ! empty( $tagline ) ) {
			$content .= "Tags: " . $tagline . "\n";
		}
		$content .= "*/\n\n";
		
		// Add essential CSS directly to style.css as backup
		$content .= <<<CSS
/* Essential Responsive Styles */

/* Mobile Navigation */
@media (max-width: 781px) {
	.wp-block-navigation__responsive-container-open {
		display: flex;
	}
	
	.wp-block-navigation__responsive-container {
		padding: 2rem;
	}
	
	.wp-block-columns {
		flex-direction: column !important;
	}
	
	.wp-block-column {
		flex-basis: 100% !important;
	}
}

/* Hero Section */
.hero-section {
	min-height: 500px;
	display: flex;
	align-items: center;
	justify-content: center;
}

/* Service Cards */
.service-card {
	transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.service-card:hover {
	transform: translateY(-5px);
	box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

/* Button Effects */
.wp-block-button__link {
	transition: all 0.3s ease;
}

.wp-block-button__link:hover {
	transform: translateY(-2px);
	box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

/* Smooth Scrolling */
html {
	scroll-behavior: smooth;
}
CSS;
		
		file_put_contents( $theme_dir . '/style.css', $content );
	}
	
	/**
	 * Create functions.php to enqueue custom CSS
	 *
	 * @param string $theme_dir Theme directory.
	 * @return void
	 */
	private function create_functions_php( $theme_dir, $theme_data ) {
		// Store theme data in option for later use
		$theme_slug = basename( $theme_dir );
		update_option( 'geniewp_theme_data_' . $theme_slug, $theme_data );
		
		$content = <<<'PHP'
<?php
/**
 * Theme functions and definitions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue theme styles
 */
function geniewp_theme_enqueue_styles() {
	// Enqueue custom CSS for additional styling
	wp_enqueue_style(
		'geniewp-custom-style',
		get_template_directory_uri() . '/assets/css/custom.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'geniewp_theme_enqueue_styles' );

/**
 * Register navigation menus
 */
function geniewp_register_menus() {
	register_nav_menus( array(
		'primary' => esc_html__( 'Primary Menu', 'geniewp' ),
		'footer'  => esc_html__( 'Footer Menu', 'geniewp' ),
	) );
}
add_action( 'after_setup_theme', 'geniewp_register_menus' );

/**
 * Add theme support
 */
function geniewp_theme_support() {
	// Add support for responsive embeds
	add_theme_support( 'responsive-embeds' );
	
	// Add support for experimental link color
	add_theme_support( 'experimental-link-color' );
	
	// Add support for block styles
	add_theme_support( 'wp-block-styles' );
	
	// Add support for editor styles
	add_theme_support( 'editor-styles' );
}
add_action( 'after_setup_theme', 'geniewp_theme_support' );
PHP;
		
		file_put_contents( $theme_dir . '/functions.php', $content );
	}
	
	/**
	 * Create enhanced theme.json file
	 *
	 * @param string $theme_dir Theme directory.
	 * @param array  $theme_data Theme data.
	 * @return void
	 */
	private function create_enhanced_theme_json( $theme_dir, $theme_data ) {
		$colors = $theme_data['colors'];
		
		$theme_json = array(
			'$schema'  => 'https://schemas.wp.org/trunk/theme.json',
			'version'  => 2,
			'settings' => array(
				'appearanceTools' => true,
				'color'           => array(
					'custom'         => true,
					'customDuotone'  => true,
					'customGradient' => true,
					'defaultGradients' => false,
					'defaultPalette' => false,
					'palette'        => $colors,
				),
				'typography'      => array(
					'customFontSize' => true,
					'fontFamilies'   => array(
						array(
							'fontFamily' => $theme_data['typography']['headingFont'],
							'slug'       => 'heading',
							'name'       => 'Heading Font',
						),
						array(
							'fontFamily' => $theme_data['typography']['bodyFont'],
							'slug'       => 'body',
							'name'       => 'Body Font',
						),
					),
					'fontSizes'      => array(
						array( 'slug' => 'small', 'size' => '0.875rem', 'name' => 'Small' ),
						array( 'slug' => 'medium', 'size' => '1rem', 'name' => 'Medium' ),
						array( 'slug' => 'large', 'size' => '1.25rem', 'name' => 'Large' ),
						array( 'slug' => 'x-large', 'size' => '1.75rem', 'name' => 'Extra Large' ),
						array( 'slug' => '2-x-large', 'size' => '2.25rem', 'name' => '2X Large' ),
						array( 'slug' => '3-x-large', 'size' => '3rem', 'name' => '3X Large' ),
					),
					'lineHeight'     => true,
				),
				'spacing'         => array(
					'padding'    => true,
					'margin'     => true,
					'blockGap'   => true,
					'units'      => array( 'px', 'em', 'rem', 'vh', 'vw', '%' ),
					'spacingScale' => array(
						'steps' => 0,
					),
					'spacingSizes' => array(
						array( 'slug' => '10', 'size' => '0.5rem', 'name' => 'XSmall' ),
						array( 'slug' => '20', 'size' => '1rem', 'name' => 'Small' ),
						array( 'slug' => '30', 'size' => '1.5rem', 'name' => 'Medium' ),
						array( 'slug' => '40', 'size' => '2rem', 'name' => 'Large' ),
						array( 'slug' => '50', 'size' => '3rem', 'name' => 'XLarge' ),
						array( 'slug' => '60', 'size' => '4rem', 'name' => '2XLarge' ),
						array( 'slug' => '70', 'size' => '6rem', 'name' => '3XLarge' ),
					),
				),
				'layout'          => array(
					'contentSize' => '800px',
					'wideSize'    => '1200px',
				),
			),
			'styles'   => array(
				'color'      => array(
					'background' => '#ffffff',
					'text'       => '#000000',
				),
				'typography' => array(
					'fontFamily' => 'var(--wp--preset--font-family--body)',
					'fontSize'   => 'var(--wp--preset--font-size--medium)',
					'lineHeight' => '1.6',
				),
				'spacing'    => array(
					'blockGap' => '1.5rem',
				),
				'elements'   => array(
					'link'    => array(
						'color' => array(
							'text' => $colors[0]['color'], // Primary color
						),
						':hover' => array(
							'color' => array(
								'text' => $colors[1]['color'], // Secondary color
							),
						),
					),
					'button'  => array(
						'color'      => array(
							'background' => $colors[0]['color'],
							'text'       => '#ffffff',
						),
						'border'     => array(
							'radius' => '0.375rem',
						),
						'typography' => array(
							'fontSize'   => 'var(--wp--preset--font-size--medium)',
							'fontWeight' => '600',
						),
						'spacing'    => array(
							'padding' => array(
								'top'    => '0.75rem',
								'right'  => '1.5rem',
								'bottom' => '0.75rem',
								'left'   => '1.5rem',
							),
						),
					),
					'heading' => array(
						'typography' => array(
							'fontFamily' => 'var(--wp--preset--font-family--heading)',
							'fontWeight' => '700',
							'lineHeight' => '1.2',
						),
					),
					'h1'      => array(
						'typography' => array(
							'fontSize' => 'var(--wp--preset--font-size--3-x-large)',
						),
					),
					'h2'      => array(
						'typography' => array(
							'fontSize' => 'var(--wp--preset--font-size--2-x-large)',
						),
					),
					'h3'      => array(
						'typography' => array(
							'fontSize' => 'var(--wp--preset--font-size--x-large)',
						),
					),
				),
				'blocks'     => array(
					'core/navigation' => array(
						'typography' => array(
							'fontSize' => 'var(--wp--preset--font-size--medium)',
						),
					),
					'core/site-title' => array(
						'typography' => array(
							'fontSize'   => 'var(--wp--preset--font-size--x-large)',
							'fontWeight' => '700',
						),
					),
				),
			),
		);
		
		file_put_contents(
			$theme_dir . '/theme.json',
			wp_json_encode( $theme_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);
	}
	
	/**
	 * Create custom CSS file for additional styling
	 *
	 * @param string $theme_dir Theme directory.
	 * @return void
	 */
	private function create_custom_css( $theme_dir ) {
		$css = <<<CSS
/* Custom Responsive Styles */

/* Mobile Navigation */
@media (max-width: 781px) {
	.wp-block-navigation__responsive-container-open {
		display: flex;
	}
	
	.wp-block-navigation__responsive-container {
		padding: 2rem;
	}
}

/* Responsive Columns */
@media (max-width: 781px) {
	.wp-block-columns {
		flex-direction: column !important;
	}
	
	.wp-block-column {
		flex-basis: 100% !important;
	}
}

/* Hero Section */
.hero-section {
	min-height: 500px;
	display: flex;
	align-items: center;
	justify-content: center;
}

@media (max-width: 781px) {
	.hero-section {
		min-height: 400px;
		padding: 3rem 1.5rem;
	}
}

/* Service Cards */
.service-card {
	transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.service-card:hover {
	transform: translateY(-5px);
	box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

/* Buttons */
.wp-block-button__link {
	transition: all 0.3s ease;
}

.wp-block-button__link:hover {
	transform: translateY(-2px);
	box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

/* Smooth Scrolling */
html {
	scroll-behavior: smooth;
}

/* Container Padding on Mobile */
@media (max-width: 781px) {
	.wp-block-group.has-global-padding {
		padding-left: 1.5rem;
		padding-right: 1.5rem;
	}
}
CSS;
		
		file_put_contents( $theme_dir . '/assets/css/custom.css', $css );
	}
	
	/**
	 * Create enhanced index template with modern blog layout
	 *
	 * @param string $theme_dir Theme directory.
	 * @param array  $theme_data Theme data.
	 * @return void
	 */
	private function create_enhanced_index_template( $theme_dir, $theme_data ) {
		$content = <<<HTML
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:heading {"textAlign":"center","level":1} -->
	<h1 class="wp-block-heading has-text-align-center">Blog</h1>
	<!-- /wp:heading -->
	
	<!-- wp:spacer {"height":"40px"} -->
	<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	
	<!-- wp:query {"queryId":1,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"layout":{"type":"constrained"}} -->
	<div class="wp-block-query">
		<!-- wp:post-template {"layout":{"type":"grid","columnCount":2}} -->
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"margin":{"bottom":"var:preset|spacing|40"}},"border":{"radius":"8px"}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
			<div class="wp-block-group has-light-gray-background-color has-background" style="border-radius:8px;margin-bottom:var(--wp--preset--spacing--40);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
				<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9","style":{"border":{"radius":"8px"}}} /-->
				
				<!-- wp:post-title {"level":2,"isLink":true} /-->
				
				<!-- wp:post-date {"style":{"typography":{"fontSize":"0.875rem"}},"textColor":"gray"} /-->
				
				<!-- wp:post-excerpt {"moreText":"Read More →"} /-->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
		
		<!-- wp:spacer {"height":"40px"} -->
		<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
		<!-- /wp:spacer -->
		
		<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-query-pagination">
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
		</div>
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:query -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
		
		file_put_contents( $theme_dir . '/templates/index.html', $content );
	}
	
	/**
	 * Create enhanced front page template with hero, services, about, and CTA sections
	 *
	 * @param string $theme_dir Theme directory.
	 * @param array  $theme_data Theme data.
	 * @return void
	 */
	private function create_enhanced_front_page_template( $theme_dir, $theme_data ) {
		$hero = $theme_data['content']['hero'];
		$services = $theme_data['content']['services'];
		$about = $theme_data['content']['about'];
		$cta = $theme_data['content']['cta'];
		$primary_color = $theme_data['colors'][0]['slug'];
		$secondary_color = $theme_data['colors'][1]['slug'];
		
		$services_html = '';
		foreach ( $services as $service ) {
			$services_html .= <<<SERVICE
		<!-- wp:column -->
		<div class="wp-block-column service-card">
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}},"border":{"radius":"12px"}},"backgroundColor":"white","className":"service-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group service-card has-white-background-color has-background" style="border-radius:12px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--30)">
				<!-- wp:heading {"level":3,"textAlign":"center"} -->
				<h3 class="wp-block-heading has-text-align-center">{$service['title']}</h3>
				<!-- /wp:heading -->
				
				<!-- wp:paragraph {"align":"center"} -->
				<p class="has-text-align-center">{$service['description']}</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

SERVICE;
		}
		
		$content = <<<HTML
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"default"}} -->
<main class="wp-block-group">
	<!-- wp:cover {"url":"","dimRatio":40,"overlayColor":"{$primary_color}","minHeight":600,"align":"full","className":"hero-section"} -->
	<div class="wp-block-cover alignfull hero-section" style="min-height:600px">
		<span aria-hidden="true" class="wp-block-cover__background has-{$primary_color}-background-color has-background-dim-40 has-background-dim"></span>
		<div class="wp-block-cover__inner-container">
			<!-- wp:group {"layout":{"type":"constrained","contentSize":"900px"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":"3rem","lineHeight":"1.2"}},"textColor":"white"} -->
				<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color" style="font-size:3rem;line-height:1.2">{$hero['headline']}</h1>
				<!-- /wp:heading -->
				
				<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1.25rem"}},"textColor":"white"} -->
				<p class="has-text-align-center has-white-color has-text-color" style="font-size:1.25rem">{$hero['subheadline']}</p>
				<!-- /wp:paragraph -->
				
				<!-- wp:spacer {"height":"30px"} -->
				<div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>
				<!-- /wp:spacer -->
				
				<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
				<div class="wp-block-buttons">
					<!-- wp:button {"backgroundColor":"{$secondary_color}","textColor":"white","style":{"typography":{"fontSize":"1.125rem"},"spacing":{"padding":{"top":"1rem","bottom":"1rem","left":"2rem","right":"2rem"}}}} -->
					<div class="wp-block-button has-custom-font-size" style="font-size:1.125rem"><a class="wp-block-button__link has-white-color has-{$secondary_color}-background-color has-text-color has-background wp-element-button" style="padding-top:1rem;padding-right:2rem;padding-bottom:1rem;padding-left:2rem">{$hero['ctaText']}</a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
	</div>
	<!-- /wp:cover -->
	
	<!-- wp:spacer {"height":"80px"} -->
	<div style="height:80px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
	<div class="wp-block-group has-light-gray-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
		<!-- wp:heading {"textAlign":"center","level":2} -->
		<h2 class="wp-block-heading has-text-align-center">Our Services</h2>
		<!-- /wp:heading -->
		
		<!-- wp:spacer {"height":"50px"} -->
		<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
		<!-- /wp:spacer -->
		
		<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
		<div class="wp-block-columns">
$services_html		</div>
		<!-- /wp:columns -->
	</div>
	<!-- /wp:group -->
	
	<!-- wp:spacer {"height":"80px"} -->
	<div style="height:80px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	
	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
		<div class="wp-block-columns are-vertically-aligned-center">
			<!-- wp:column {"verticalAlignment":"center"} -->
			<div class="wp-block-column is-vertically-aligned-center">
				<!-- wp:heading {"level":2} -->
				<h2 class="wp-block-heading">{$about['heading']}</h2>
				<!-- /wp:heading -->
				
				<!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.8"}}} -->
				<p style="line-height:1.8">{$about['content']}</p>
				<!-- /wp:paragraph -->
				
				<!-- wp:buttons -->
				<div class="wp-block-buttons">
					<!-- wp:button {"backgroundColor":"{$primary_color}","textColor":"white"} -->
					<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-{$primary_color}-background-color has-text-color has-background wp-element-button">Learn More</a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:column -->
			
			<!-- wp:column {"verticalAlignment":"center"} -->
			<div class="wp-block-column is-vertically-aligned-center">
				<!-- wp:image {"sizeSlug":"large","linkDestination":"none","style":{"border":{"radius":"12px"}}} -->
				<figure class="wp-block-image size-large has-custom-border"><img src="" alt="" style="border-radius:12px"/></figure>
				<!-- /wp:image -->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->
	</div>
	<!-- /wp:group -->
	
	<!-- wp:spacer {"height":"80px"} -->
	<div style="height:80px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"{$primary_color}","textColor":"white","layout":{"type":"constrained"}} -->
	<div class="wp-block-group has-white-color has-{$primary_color}-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
		<!-- wp:heading {"textAlign":"center","level":2,"textColor":"white"} -->
		<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color">{$cta['heading']}</h2>
		<!-- /wp:heading -->
		
		<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1.125rem"}},"textColor":"white"} -->
		<p class="has-text-align-center has-white-color has-text-color" style="font-size:1.125rem">{$cta['text']}</p>
		<!-- /wp:paragraph -->
		
		<!-- wp:spacer {"height":"30px"} -->
		<div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>
		<!-- /wp:spacer -->
		
		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-buttons">
			<!-- wp:button {"backgroundColor":"white","textColor":"{$primary_color}","style":{"typography":{"fontSize":"1.125rem"}}} -->
			<div class="wp-block-button has-custom-font-size" style="font-size:1.125rem"><a class="wp-block-button__link has-{$primary_color}-color has-white-background-color has-text-color has-background wp-element-button">{$cta['buttonText']}</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
		
		file_put_contents( $theme_dir . '/templates/front-page.html', $content );
	}
	
	/**
	 * Create page template
	 *
	 * @param string $theme_dir Theme directory.
	 * @param array  $theme_data Theme data.
	 * @return void
	 */
	private function create_page_template( $theme_dir, $theme_data ) {
		$content = <<<HTML
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:post-title {"level":1} /-->
	
	<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
		
		file_put_contents( $theme_dir . '/templates/page.html', $content );
	}
	
	/**
	 * Create single post template
	 *
	 * @param string $theme_dir Theme directory.
	 * @param array  $theme_data Theme data.
	 * @return void
	 */
	private function create_single_template( $theme_dir, $theme_data ) {
		$content = <<<HTML
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:post-featured-image {"style":{"border":{"radius":"12px"}}} /-->
	
	<!-- wp:post-title {"level":1} /-->
	
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|30"}}}} -->
	<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--20);margin-bottom:var(--wp--preset--spacing--30)">
		<!-- wp:post-date {"textColor":"gray"} /-->
		<!-- wp:post-author {"showAvatar":false,"textColor":"gray"} /-->
	</div>
	<!-- /wp:group -->
	
	<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
	
	<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50)">
		<!-- wp:separator -->
		<hr class="wp-block-separator has-alpha-channel-opacity"/>
		<!-- /wp:separator -->
		
		<!-- wp:post-terms {"term":"category"} /-->
		<!-- wp:post-terms {"term":"post_tag"} /-->
	</div>
	<!-- /wp:group -->
	
	<!-- wp:comments -->
	<div class="wp-block-comments"></div>
	<!-- /wp:comments -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
		
		file_put_contents( $theme_dir . '/templates/single.html', $content );
	}
	
	/**
	 * Create enhanced header with responsive navigation
	 *
	 * @param string $theme_dir Theme directory.
	 * @param array  $theme_data Theme data.
	 * @return void
	 */
	private function create_enhanced_header_part( $theme_dir, $theme_data ) {
		$primary_color = $theme_data['colors'][0]['slug'];
		
		$content = <<<HTML
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"backgroundColor":"white","className":"site-header","layout":{"type":"constrained"}} -->
<div class="wp-block-group site-header has-white-background-color has-background" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group">
		<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group">
			<!-- wp:site-logo {"width":48} /-->
			
			<!-- wp:group {"style":{"spacing":{"blockGap":"0px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:site-title {"style":{"typography":{"fontSize":"1.5rem","fontWeight":"700"}},"textColor":"{$primary_color}"} /-->
				<!-- wp:site-tagline {"style":{"typography":{"fontSize":"0.875rem"}},"textColor":"gray"} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
		
		<!-- wp:navigation {"textColor":"{$primary_color}","overlayBackgroundColor":"white","overlayTextColor":"{$primary_color}","layout":{"type":"flex","justifyContent":"right"},"style":{"spacing":{"blockGap":"var:preset|spacing|40"}}} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
HTML;
		
		file_put_contents( $theme_dir . '/parts/header.html', $content );
	}
	
	/**
	 * Create enhanced footer with widget areas and social links
	 *
	 * @param string $theme_dir Theme directory.
	 * @param array  $theme_data Theme data.
	 * @return void
	 */
	private function create_enhanced_footer_part( $theme_dir, $theme_data ) {
		$year = date( 'Y' );
		$site_name = $theme_data['site_name'];
		
		$content = <<<HTML
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|30"}}},"backgroundColor":"dark-gray","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-white-color has-dark-gray-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|50"}}}} -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"textColor":"white"} -->
			<h3 class="wp-block-heading has-white-color has-text-color">About {$site_name}</h3>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph {"textColor":"white"} -->
			<p class="has-white-color has-text-color">{$theme_data['description']}</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"textColor":"white"} -->
			<h3 class="wp-block-heading has-white-color has-text-color">Quick Links</h3>
			<!-- /wp:heading -->
			
			<!-- wp:navigation {"textColor":"white","overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} /-->
		</div>
		<!-- /wp:column -->
		
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"textColor":"white"} -->
			<h3 class="wp-block-heading has-white-color has-text-color">Contact</h3>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph {"textColor":"white"} -->
			<p class="has-white-color has-text-color">Email: info@{$site_name}.com</p>
			<!-- /wp:paragraph -->
			
			<!-- wp:paragraph {"textColor":"white"} -->
			<p class="has-white-color has-text-color">Phone: (555) 123-4567</p>
			<!-- /wp:paragraph -->
			
			<!-- wp:social-links {"iconColor":"white","iconColorValue":"#ffffff","className":"is-style-logos-only"} -->
			<ul class="wp-block-social-links has-icon-color is-style-logos-only">
				<!-- wp:social-link {"url":"#","service":"facebook"} /-->
				<!-- wp:social-link {"url":"#","service":"twitter"} /-->
				<!-- wp:social-link {"url":"#","service":"instagram"} /-->
				<!-- wp:social-link {"url":"#","service":"linkedin"} /-->
			</ul>
			<!-- /wp:social-links -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	
	<!-- wp:spacer {"height":"40px"} -->
	<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	
	<!-- wp:separator {"backgroundColor":"gray"} -->
	<hr class="wp-block-separator has-text-color has-gray-background-color has-alpha-channel-opacity has-gray-background-color has-background"/>
	<!-- /wp:separator -->
	
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30)">
		<!-- wp:paragraph {"fontSize":"small","textColor":"white"} -->
		<p class="has-white-color has-text-color has-small-font-size">© {$year} {$site_name}. All rights reserved.</p>
		<!-- /wp:paragraph -->
		
		<!-- wp:paragraph {"fontSize":"small","textColor":"white"} -->
		<p class="has-white-color has-text-color has-small-font-size">Powered by <a href="https://wordpress.org" style="color: inherit;">WordPress</a> & GenieWP</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
HTML;
		
		file_put_contents( $theme_dir . '/parts/footer.html', $content );
	}
	
	/**
	 * Create block patterns
	 *
	 * @param string $theme_dir Theme directory.
	 * @param array  $theme_data Theme data.
	 * @return void
	 */
	private function create_patterns( $theme_dir, $theme_data ) {
		// For now, patterns are included inline in templates
		// Future enhancement: Create separate pattern files
	}
	
	/**
	 * Create index.html template
	 *
	 * @param string $theme_dir Theme directory.
	 * @param string $site_name Site name.
	 * @return void
	 */
	private function create_index_template( $theme_dir, $site_name ) {
		$content = <<<HTML
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:query {"queryId":1,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template {"layout":{"type":"default"}} -->
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"0","right":"0"},"margin":{"bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--40);padding-top:var(--wp--preset--spacing--40);padding-right:0;padding-bottom:var(--wp--preset--spacing--40);padding-left:0">
				<!-- wp:post-title {"level":1,"isLink":true} /-->
				<!-- wp:post-date /-->
				<!-- wp:post-excerpt /-->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
		
		<!-- wp:query-pagination -->
		<div class="wp-block-query-pagination">
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
		</div>
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:query -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
		
		file_put_contents( $theme_dir . '/templates/index.html', $content );
	}
	
	/**
	 * Create header.html template part
	 *
	 * @param string $theme_dir Theme directory.
	 * @param string $site_name Site name.
	 * @param string $tagline Tagline.
	 * @return void
	 */
	private function create_header_part( $theme_dir, $site_name, $tagline ) {
		$content = <<<HTML
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"backgroundColor":"primary","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-white-color has-primary-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group">
		<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group">
			<!-- wp:site-title {"style":{"typography":{"fontSize":"1.5rem","fontWeight":"700"}},"textColor":"white"} /-->
			<!-- wp:site-tagline {"style":{"typography":{"fontSize":"0.875rem"}},"textColor":"white"} /-->
		</div>
		<!-- /wp:group -->
		
		<!-- wp:navigation {"textColor":"white","overlayBackgroundColor":"primary","overlayTextColor":"white","layout":{"type":"flex","justifyContent":"right"}} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
HTML;
		
		file_put_contents( $theme_dir . '/parts/header.html', $content );
	}
	
	/**
	 * Create footer.html template part
	 *
	 * @param string $theme_dir Theme directory.
	 * @param string $site_name Site name.
	 * @return void
	 */
	private function create_footer_part( $theme_dir, $site_name ) {
		$year = date( 'Y' );
		$content = <<<HTML
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"backgroundColor":"black","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-white-color has-black-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size">© {$year} {$site_name}. All rights reserved.</p>
		<!-- /wp:paragraph -->
		
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size">Powered by <a href="https://wordpress.org">WordPress</a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
HTML;
		
		file_put_contents( $theme_dir . '/parts/footer.html', $content );
	}
	
	/**
	 * Create front-page.html template
	 *
	 * @param string $theme_dir Theme directory.
	 * @param string $site_name Site name.
	 * @param string $business_type Business type.
	 * @return void
	 */
	private function create_front_page_template( $theme_dir, $site_name, $business_type ) {
		$content = <<<HTML
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
	<!-- wp:cover {"url":"","dimRatio":50,"overlayColor":"primary","minHeight":400,"align":"full"} -->
	<div class="wp-block-cover alignfull" style="min-height:400px">
		<span aria-hidden="true" class="wp-block-cover__background has-primary-background-color has-background-dim"></span>
		<div class="wp-block-cover__inner-container">
			<!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
			<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color">Welcome to {$site_name}</h1>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph {"align":"center","textColor":"white"} -->
			<p class="has-text-align-center has-white-color has-text-color">Your trusted partner for {$business_type}</p>
			<!-- /wp:paragraph -->
			
			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
			<div class="wp-block-buttons">
				<!-- wp:button {"backgroundColor":"secondary","textColor":"white"} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-secondary-background-color has-text-color has-background wp-element-button">Get Started</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
	</div>
	<!-- /wp:cover -->
	
	<!-- wp:spacer {"height":"60px"} -->
	<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	
	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading">Feature One</h3>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph -->
			<p>Describe your first amazing feature or service here.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading">Feature Two</h3>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph -->
			<p>Highlight another great aspect of what you offer.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading">Feature Three</h3>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph -->
			<p>Share one more compelling reason to choose you.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	
	<!-- wp:spacer {"height":"60px"} -->
	<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
		
		file_put_contents( $theme_dir . '/templates/front-page.html', $content );
	}
	
	/**
	 * Create README.md file
	 *
	 * @param string $theme_dir Theme directory.
	 * @param string $site_name Site name.
	 * @param string $description Description.
	 * @return void
	 */
	private function create_readme( $theme_dir, $site_name, $description ) {
		$content = "# {$site_name}\n\n";
		
		if ( ! empty( $description ) ) {
			$content .= "{$description}\n\n";
		}
		
		$content .= "## About\n\n";
		$content .= "This theme was generated by GenieWP - AI Theme Generator.\n\n";
		$content .= "## Features\n\n";
		$content .= "- WordPress block theme\n";
		$content .= "- Custom color palette\n";
		$content .= "- Responsive design\n";
		$content .= "- Modern and clean layout\n\n";
		$content .= "## Installation\n\n";
		$content .= "1. Upload the theme to your `/wp-content/themes/` directory\n";
		$content .= "2. Activate the theme through the 'Appearance' menu in WordPress\n";
		$content .= "3. Customize your theme using the Site Editor\n";
		
		file_put_contents( $theme_dir . '/README.md', $content );
	}
}
