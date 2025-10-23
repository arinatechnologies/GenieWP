<?php
/**
 * Theme Setup class - handles page and menu creation after theme activation
 * 
 * @package GenieWP
 */

namespace ThemeIsle\QuickWP;

/**
 * Theme Setup class
 */
class Theme_Setup {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'after_switch_theme', array( $this, 'create_initial_content' ) );
	}
	
	/**
	 * Create default pages and menu on theme activation
	 */
	public function create_initial_content() {
		// Get theme slug
		$theme_slug = get_option( 'stylesheet' );
		
		// Check if already setup for THIS specific theme
		if ( get_option( 'geniewp_theme_setup_complete_' . $theme_slug ) ) {
			return;
		}
		
		// Get theme data from option
		$theme_data = get_option( 'geniewp_theme_data_' . $theme_slug );
		
		if ( ! $theme_data ) {
			// Fallback: try to create basic pages with default data
			$theme_data = array(
				'site_name' => get_bloginfo( 'name' ),
				'business_type' => 'Business',
				'content' => array(
					'hero' => array(
						'headline' => 'Welcome to ' . get_bloginfo( 'name' ),
						'subheadline' => 'Your trusted partner for professional services',
						'cta_text' => 'Get Started',
					),
					'about' => array(
						'content' => 'We are a dedicated team committed to providing exceptional service and value to our clients.',
					),
					'services' => array(
						array(
							'title' => 'Service One',
							'description' => 'Professional service description here.',
						),
						array(
							'title' => 'Service Two',
							'description' => 'Quality service for your needs.',
						),
						array(
							'title' => 'Service Three',
							'description' => 'Expert solutions tailored for you.',
						),
						array(
							'title' => 'Service Four',
							'description' => 'Comprehensive support and guidance.',
						),
					),
				),
			);
		}
		
		// Create pages
		$pages = array(
			'Home' => array(
				'content' => $this->get_home_content( $theme_data ),
				'template' => 'front-page'
			),
			'About' => array(
				'content' => $this->get_about_content( $theme_data ),
				'template' => ''
			),
			'Services' => array(
				'content' => $this->get_services_content( $theme_data ),
				'template' => ''
			),
			'Blog' => array(
				'content' => $this->get_blog_content( $theme_data ),
				'template' => ''
			),
			'Contact' => array(
				'content' => $this->get_contact_content( $theme_data ),
				'template' => ''
			),
		);
		
		$page_ids = array();
		$home_page_id = 0;
		$blog_page_id = 0;
		
		foreach ( $pages as $title => $page_data ) {
			// Check if page exists
			$existing_page = get_page_by_title( $title );
			
			if ( ! $existing_page ) {
				$page_id = wp_insert_post( array(
					'post_title'   => $title,
					'post_content' => $page_data['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_author'  => get_current_user_id(),
				) );
				
				if ( $page_id && ! is_wp_error( $page_id ) ) {
					$page_ids[$title] = $page_id;
					
					if ( $title === 'Home' ) {
						$home_page_id = $page_id;
					}
					if ( $title === 'Blog' ) {
						$blog_page_id = $page_id;
					}
				}
			} else {
				$page_ids[$title] = $existing_page->ID;
				if ( $title === 'Home' ) {
					$home_page_id = $existing_page->ID;
				}
				if ( $title === 'Blog' ) {
					$blog_page_id = $existing_page->ID;
				}
			}
		}
		
		// Set front page and posts page
		if ( $home_page_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_page_id );
		}
		if ( $blog_page_id ) {
			update_option( 'page_for_posts', $blog_page_id );
		}
		
		// Create navigation menu
		$menu_name = 'Primary Menu';
		$menu_exists = wp_get_nav_menu_object( $menu_name );
		
		if ( ! $menu_exists ) {
			$menu_id = wp_create_nav_menu( $menu_name );
			
			$position = 1;
			foreach ( $page_ids as $title => $page_id ) {
				wp_update_nav_menu_item( $menu_id, 0, array(
					'menu-item-title'      => $title,
					'menu-item-object-id'  => $page_id,
					'menu-item-object'     => 'page',
					'menu-item-type'       => 'post_type',
					'menu-item-status'     => 'publish',
					'menu-item-position'   => $position++,
				) );
			}
			
			// Set as primary menu location
			$locations = get_theme_mod( 'nav_menu_locations' );
			if ( ! is_array( $locations ) ) {
				$locations = array();
			}
			$locations['primary'] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		
		// Mark as complete for this specific theme
		update_option( 'geniewp_theme_setup_complete_' . $theme_slug, true );
	}
	
	/**
	 * Get home page content with hero
	 */
	private function get_home_content( $theme_data ) {
		$hero_image = 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=1920&h=600&fit=crop';
		
		$headline = isset( $theme_data['content']['hero']['headline'] ) ? $theme_data['content']['hero']['headline'] : 'Welcome to Our Website';
		$subheadline = isset( $theme_data['content']['hero']['subheadline'] ) ? $theme_data['content']['hero']['subheadline'] : 'Your trusted partner for professional services';
		$cta_text = isset( $theme_data['content']['hero']['cta_text'] ) ? $theme_data['content']['hero']['cta_text'] : 'Get Started';
		
		return '<!-- wp:cover {"url":"' . $hero_image . '","dimRatio":50,"overlayColor":"dark-gray","minHeight":600,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:600px"><span aria-hidden="true" class="wp-block-cover__background has-dark-gray-background-color has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $hero_image . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
<h1 class="has-text-align-center has-white-color has-text-color">' . esc_html( $headline ) . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"white","fontSize":"large"} -->
<p class="has-text-align-center has-white-color has-text-color has-large-font-size">' . esc_html( $subheadline ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"backgroundColor":"primary","textColor":"white","className":"is-style-fill"} -->
<div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-white-color has-primary-background-color has-text-color has-background wp-element-button">' . esc_html( $cta_text ) . '</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->';
	}
	
	/**
	 * Get about page content with hero
	 */
	private function get_about_content( $theme_data ) {
		$about_image = 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=1920&h=400&fit=crop';
		
		$site_name = isset( $theme_data['site_name'] ) ? $theme_data['site_name'] : get_bloginfo( 'name' );
		$about_content = isset( $theme_data['content']['about']['content'] ) ? $theme_data['content']['about']['content'] : 'We are a dedicated team committed to providing exceptional service.';
		$business_type = isset( $theme_data['business_type'] ) ? $theme_data['business_type'] : 'business';
		
		return '<!-- wp:cover {"url":"' . $about_image . '","dimRatio":30,"minHeight":400,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:400px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-30 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $about_image . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
<h1 class="has-text-align-center has-white-color has-text-color">About Us</h1>
<!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:spacer {"height":"60px"} -->
<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group">
<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
<!-- wp:heading -->
<h2>About ' . esc_html( $site_name ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html( $about_content ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"30px"} -->
<div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"level":3} -->
<h3>Our Mission</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We are dedicated to providing exceptional ' . strtolower( $business_type ) . ' services that exceed your expectations.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Why Choose Us</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>Professional expertise and experience</li>
<li>Customer-focused approach</li>
<li>Quality results guaranteed</li>
<li>Competitive pricing</li>
<li>Dedicated support team</li>
</ul>
<!-- /wp:list -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%">
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=600&h=800&fit=crop" alt="Team"/></figure>
<!-- /wp:image -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->';
	}
	
	/**
	 * Get services page content with hero
	 */
	private function get_services_content( $theme_data ) {
		$services_image = 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1920&h=400&fit=crop';
		$business_type = isset( $theme_data['business_type'] ) ? $theme_data['business_type'] : 'business';
		$services = isset( $theme_data['content']['services'] ) ? $theme_data['content']['services'] : array();
		
		$services_html = '<!-- wp:cover {"url":"' . $services_image . '","dimRatio":30,"minHeight":400,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:400px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-30 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $services_image . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
<h1 class="has-text-align-center has-white-color has-text-color">Our Services</h1>
<!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:spacer {"height":"60px"} -->
<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">We offer a comprehensive range of ' . strtolower( $business_type ) . ' services to meet your needs.</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:columns -->
<div class="wp-block-columns">';
		
		$service_images = array(
			'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=400&h=300&fit=crop',
			'https://images.unsplash.com/photo-1551434678-e076c223a692?w=400&h=300&fit=crop',
			'https://images.unsplash.com/photo-1552664730-d307ca884978?w=400&h=300&fit=crop',
			'https://images.unsplash.com/photo-1553877522-43269d4ea984?w=400&h=300&fit=crop',
		);
		
		$i = 0;
		foreach ( $services as $service ) {
			$service_title = isset( $service['title'] ) ? $service['title'] : 'Service ' . ($i + 1);
			$service_desc = isset( $service['description'] ) ? $service['description'] : 'Professional service description.';
			
			$services_html .= '
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:image {"sizeSlug":"large","style":{"border":{"radius":"8px"}}} -->
		<figure class="wp-block-image size-large has-custom-border"><img src="' . $service_images[$i % 4] . '" alt="" style="border-radius:8px"/></figure>
		<!-- /wp:image -->
		
		<!-- wp:heading {"level":3,"textAlign":"center"} -->
		<h3 class="has-text-align-center">' . esc_html( $service_title ) . '</h3>
		<!-- /wp:heading -->
		
		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center">' . esc_html( $service_desc ) . '</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:column -->';
			$i++;
		}
		
		$services_html .= '
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->';
		
		return $services_html;
	}
	
	/**
	 * Get blog page content
	 */
	private function get_blog_content( $theme_data ) {
		$blog_image = 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=1920&h=400&fit=crop';
		
		return '<!-- wp:cover {"url":"' . $blog_image . '","dimRatio":30,"minHeight":400,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:400px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-30 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $blog_image . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
<h1 class="has-text-align-center has-white-color has-text-color">Blog</h1>
<!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:spacer {"height":"60px"} -->
<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group">
<!-- wp:query {"queryId":1,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query">
	<!-- wp:post-template {"layout":{"type":"grid","columnCount":2}} -->
		<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"},"margin":{"bottom":"var:preset|spacing|40"}},"border":{"radius":"8px"}},"backgroundColor":"light-gray"} -->
		<div class="wp-block-group has-light-gray-background-color has-background" style="border-radius:8px;margin-bottom:var(--wp--preset--spacing--40);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
			<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
			<!-- wp:post-title {"level":2,"isLink":true} /-->
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
</div>
<!-- /wp:group -->';
	}
	
	/**
	 * Get contact page content with hero
	 */
	private function get_contact_content( $theme_data ) {
		$contact_image = 'https://images.unsplash.com/photo-1423666639041-f56000c27a9a?w=1920&h=400&fit=crop';
		$site_name = isset( $theme_data['site_name'] ) ? $theme_data['site_name'] : get_bloginfo( 'name' );
		
		return '<!-- wp:cover {"url":"' . $contact_image . '","dimRatio":30,"minHeight":400,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:400px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-30 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $contact_image . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:heading {"textAlign":"center","level":1,"textColor":"white"} -->
<h1 class="has-text-align-center has-white-color has-text-color">Contact Us</h1>
<!-- /wp:heading -->
</div></div>
<!-- /wp:cover -->

<!-- wp:spacer {"height":"60px"} -->
<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">Get in touch with us today. We\'d love to hear from you!</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:columns -->
<div class="wp-block-columns">
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"8px"}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
		<div class="wp-block-group has-light-gray-background-color has-background" style="border-radius:8px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
			<!-- wp:heading {"level":3,"textAlign":"center"} -->
			<h3 class="has-text-align-center">📧 Email</h3>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph {"align":"center"} -->
			<p class="has-text-align-center">info@' . sanitize_title( $site_name ) . '.com</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->
	
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"8px"}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
		<div class="wp-block-group has-light-gray-background-color has-background" style="border-radius:8px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
			<!-- wp:heading {"level":3,"textAlign":"center"} -->
			<h3 class="has-text-align-center">📱 Phone</h3>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph {"align":"center"} -->
			<p class="has-text-align-center">(555) 123-4567</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->
	
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"8px"}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
		<div class="wp-block-group has-light-gray-background-color has-background" style="border-radius:8px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
			<!-- wp:heading {"level":3,"textAlign":"center"} -->
			<h3 class="has-text-align-center">📍 Address</h3>
			<!-- /wp:heading -->
			
			<!-- wp:paragraph {"align":"center"} -->
			<p class="has-text-align-center">123 Main Street<br>City, State 12345</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->';
	}
}
