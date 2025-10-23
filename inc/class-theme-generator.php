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
		return $this->generate_basic_theme( $site_name, $business_type, $tagline, $description, $primary_color, $secondary_color, $theme_slug );
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
					'max_tokens'  => 2000,
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
		$prompt = sprintf(
			"Create a modern WordPress block theme with these details:\n\n" .
			"Website Name: %s\n" .
			"Business Type: %s\n",
			$data['site_name'],
			$data['business_type']
		);
		
		if ( ! empty( $data['tagline'] ) ) {
			$prompt .= "Tagline: {$data['tagline']}\n";
		}
		
		if ( ! empty( $data['description'] ) ) {
			$prompt .= "Description: {$data['description']}\n";
		}
		
		$prompt .= sprintf(
			"Primary Color: %s\n" .
			"Secondary Color: %s\n\n" .
			"Please suggest a complementary color palette and provide theme recommendations.",
			$data['primary_color'],
			$data['secondary_color']
		);
		
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
		// For now, use AI insights but generate basic theme
		// In future, parse AI JSON responses for more advanced theme generation
		
		return $this->generate_basic_theme(
			$data['site_name'],
			$data['business_type'],
			$data['tagline'],
			$data['description'],
			$data['primary_color'],
			$data['secondary_color'],
			$theme_slug
		);
	}
	
	/**
	 * Generate basic theme without AI
	 *
	 * @param string $site_name Site name.
	 * @param string $business_type Business type.
	 * @param string $tagline Tagline.
	 * @param string $description Description.
	 * @param string $primary_color Primary color.
	 * @param string $secondary_color Secondary color.
	 * @param string $theme_slug Theme slug.
	 * @return array|\WP_Error
	 */
	private function generate_basic_theme( $site_name, $business_type, $tagline, $description, $primary_color, $secondary_color, $theme_slug ) {
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
		
		// Generate theme files
		$this->create_style_css( $theme_dir, $site_name, $tagline, $description );
		$this->create_theme_json( $theme_dir, $primary_color, $secondary_color );
		$this->create_index_template( $theme_dir, $site_name );
		$this->create_header_part( $theme_dir, $site_name, $tagline );
		$this->create_footer_part( $theme_dir, $site_name );
		$this->create_front_page_template( $theme_dir, $site_name, $business_type );
		$this->create_readme( $theme_dir, $site_name, $description );
		
		return array(
			'success'    => true,
			'theme_slug' => $theme_slug,
			'theme_name' => $site_name,
			'theme_dir'  => $theme_dir,
		);
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
		$content .= "*/\n";
		
		file_put_contents( $theme_dir . '/style.css', $content );
	}
	
	/**
	 * Create theme.json file
	 *
	 * @param string $theme_dir Theme directory.
	 * @param string $primary_color Primary color.
	 * @param string $secondary_color Secondary color.
	 * @return void
	 */
	private function create_theme_json( $theme_dir, $primary_color, $secondary_color ) {
		$theme_json = array(
			'$schema'  => 'https://schemas.wp.org/trunk/theme.json',
			'version'  => 2,
			'settings' => array(
				'color'      => array(
					'palette' => array(
						array(
							'slug'  => 'primary',
							'color' => $primary_color,
							'name'  => 'Primary',
						),
						array(
							'slug'  => 'secondary',
							'color' => $secondary_color,
							'name'  => 'Secondary',
						),
						array(
							'slug'  => 'white',
							'color' => '#ffffff',
							'name'  => 'White',
						),
						array(
							'slug'  => 'black',
							'color' => '#000000',
							'name'  => 'Black',
						),
						array(
							'slug'  => 'gray',
							'color' => '#6b7280',
							'name'  => 'Gray',
						),
					),
				),
				'typography' => array(
					'fontFamilies' => array(
						array(
							'fontFamily' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
							'slug'       => 'system',
							'name'       => 'System Font',
						),
					),
					'fontSizes'    => array(
						array(
							'slug' => 'small',
							'size' => '0.875rem',
							'name' => 'Small',
						),
						array(
							'slug' => 'medium',
							'size' => '1rem',
							'name' => 'Medium',
						),
						array(
							'slug' => 'large',
							'size' => '1.5rem',
							'name' => 'Large',
						),
						array(
							'slug' => 'x-large',
							'size' => '2.25rem',
							'name' => 'Extra Large',
						),
					),
				),
				'layout'     => array(
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
					'fontFamily' => 'var(--wp--preset--font-family--system)',
					'fontSize'   => 'var(--wp--preset--font-size--medium)',
				),
				'elements'   => array(
					'link' => array(
						'color' => array(
							'text' => $primary_color,
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
