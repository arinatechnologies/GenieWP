<?php
/**
 * API class.
 * 
 * @package Codeinwp/QuickWP
 */

namespace ThemeIsle\QuickWP;

/**
 * API class.
 */
class API {
	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'quickwp';

	/**
	 * API version.
	 *
	 * @var string
	 */
	private $version = 'v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Only register routes if requirements are met
		if ($this->is_ready()) {
			$this->register_route();
		}
		
		// Add admin notice for missing API key
		add_action('admin_init', [$this, 'check_api_key']);
	}
	
	/**
	 * Check if requirements are met
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
	 * Check for API key and show admin notice if missing
	 *
	 * @return void
	 */
	public function check_api_key() {
		$api_key = get_option('open_ai_api_key');
		if (empty($api_key)) {
			add_action('admin_notices', [$this, 'api_key_notice']);
		}
	}
	
	/**
	 * Display API key notice
	 *
	 * @return void
	 */
	public function api_key_notice() {
		if (!current_user_can('manage_options')) {
			return;
		}
		
		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p>' . esc_html__('GenieWP: Add your OpenAI API key in Settings → General to enable AI generation. You can still generate a minimal theme without AI.', 'quickwp') . '</p>';
		echo '</div>';
	}

	/**
	 * Get endpoint.
	 * 
	 * @return string
	 */
	public function get_endpoint() {
		return $this->namespace . '/' . $this->version;
	}

	/**
	 * Register hooks and actions.
	 * 
	 * @return void
	 */
	private function register_route() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API route
	 * 
	 * @return void
	 */
	public function register_routes() {
		$namespace = $this->namespace . '/' . $this->version;

		$routes = array(
			'send'      => array(
				'methods'  => \WP_REST_Server::CREATABLE,
				'args'     => array(
					'step'     => array(
						'required' => true,
						'type'     => 'string',
					),
					'message'  => array(
						'required' => false,
						'type'     => 'string',
					),
					'template' => array(
						'required' => false,
						'type'     => 'string',
					),
				),
				'callback' => array( $this, 'send' ),
			),
			'status'    => array(
				'methods'  => \WP_REST_Server::READABLE,
				'args'     => array(
					'thread_id' => array(
						'required' => true,
						'type'     => 'string',
					),
					'run_id'    => array(
						'required' => true,
						'type'     => 'string',
					),
				),
				'callback' => array( $this, 'status' ),
			),
			'get'       => array(
				'methods'  => \WP_REST_Server::READABLE,
				'args'     => array(
					'thread_id' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
				'callback' => array( $this, 'get' ),
			),
			'templates' => array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'templates' ),
			),
			'export'    => array(
				'methods'  => \WP_REST_Server::CREATABLE,
				'args'     => array(
					'title'       => array(
						'required' => true,
						'type'     => 'string',
					),
					'description' => array(
						'required' => false,
						'type'     => 'string',
					),
					'images'      => array(
						'required' => false,
						'type'     => 'array',
					),
					'slug'        => array(
						'required' => false,
						'type'     => 'string',
					),
				),
				'callback' => array( $this, 'export' ),
			),
		);

		foreach ( $routes as $route => $args ) {
			register_rest_route(
				$namespace,
				'/' . $route,
				array(
					'methods'             => $args['methods'],
					'callback'            => $args['callback'],
					'args'                => $args['args'] ?? array(),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * Send message.
	 * 
	 * @param \WP_REST_Request $request Request object.
	 * 
	 * @return \WP_REST_Response
	 */
	public function send( $request ) {
		$api_key = get_option( 'open_ai_api_key' );
		$step    = $request->get_param( 'step' );
		$message = $request->get_param( 'message' );
		$template = $request->get_param( 'template' );

		if ( empty( $api_key ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => __( 'API key is missing.', 'quickwp' ),
					),
				)
			);
		}

		$thread_id = get_option( 'quickwp_thread_id' );

		if ( empty( $thread_id ) ) {
			$thread = $this->create_thread( $api_key, $message, $template );

			if ( is_wp_error( $thread ) ) {
				return rest_ensure_response(
					array(
						'success' => false,
						'data'    => array(
							'message' => $thread->get_error_message(),
						),
					)
				);
			}

			$thread_id = $thread['id'];

			update_option( 'quickwp_thread_id', $thread_id );
		} else {
			$message_response = $this->add_message_to_thread( $api_key, $thread_id, $message, $step );

			if ( is_wp_error( $message_response ) ) {
				return rest_ensure_response(
					array(
						'success' => false,
						'data'    => array(
							'message' => $message_response->get_error_message(),
						),
					)
				);
			}
		}

		$run = $this->run_thread( $api_key, $thread_id, $step );

		if ( is_wp_error( $run ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => $run->get_error_message(),
					),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'thread_id' => $thread_id,
					'run_id'    => $run['id'],
				),
			)
		);
	}

	/**
	 * Create thread.
	 * 
	 * @param string $api_key API key.
	 * @param string $message Message.
	 * @param string $template Template.
	 * 
	 * @return array|\WP_Error
	 */
	private function create_thread( $api_key, $message, $template = null ) {
		$url = 'https://api.openai.com/v1/threads';

		$body = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $message,
				),
			),
		);

		if ( ! empty( $template ) ) {
			$body['metadata'] = array(
				'template' => $template,
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'OpenAI-Beta'   => 'assistants=v2',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error( 'api_error', $body['error']['message'] );
		}

		return $body;
	}

	/**
	 * Add message to thread.
	 * 
	 * @param string $api_key API key.
	 * @param string $thread_id Thread ID.
	 * @param string $message Message.
	 * @param string $step Step.
	 * 
	 * @return array|\WP_Error
	 */
	private function add_message_to_thread( $api_key, $thread_id, $message, $step ) {
		$url = 'https://api.openai.com/v1/threads/' . $thread_id . '/messages';

		$body = array(
			'role'    => 'user',
			'content' => $message,
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error( 'api_error', $body['error']['message'] );
		}

		return $body;
	}

	/**
	 * Run thread.
	 * 
	 * @param string $api_key API key.
	 * @param string $thread_id Thread ID.
	 * @param string $step Step.
	 * 
	 * @return array|\WP_Error
	 */
	private function run_thread( $api_key, $thread_id, $step ) {
		$url = 'https://api.openai.com/v1/threads/' . $thread_id . '/runs';

		$body = array(
			'assistant_id' => $this->get_assistant_id( $step ),
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'OpenAI-Beta'   => 'assistants=v2',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error( 'api_error', $body['error']['message'] );
		}

		return $body;
	}

	/**
	 * Get assistant ID.
	 * 
	 * @param string $step Step.
	 * 
	 * @return string
	 */
	private function get_assistant_id( $step ) {
		$assistants = array(
			'welcome'         => 'asst_gZK2vTq5EIN2LJOKI6DlG33S',
			'site-description' => 'asst_gZK2vTq5EIN2LJOKI6DlG33S',
			'site-topic'      => 'asst_gZK2vTq5EIN2LJOKI6DlG33S',
			'color-palette'   => 'asst_13H3CB33PlF99C3KOX3z9D4x',
			'template'        => 'asst_gZK2vTq5EIN2LJOKI6DlG33S',
			'image'           => 'asst_5p0q4VWVbJKG0X1zH23Zk33S',
			'view-site'       => 'asst_gZK2vTq5EIN2LJOKI6DlG33S',
		);

		return $assistants[ $step ] ?? 'asst_gZK2vTq5EIN2LJOKI6DlG33S';
	}

	/**
	 * Get status.
	 * 
	 * @param \WP_REST_Request $request Request object.
	 * 
	 * @return \WP_REST_Response
	 */
	public function status( $request ) {
		$api_key   = get_option( 'open_ai_api_key' );
		$thread_id = $request->get_param( 'thread_id' );
		$run_id    = $request->get_param( 'run_id' );

		if ( empty( $api_key ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => __( 'API key is missing.', 'quickwp' ),
					),
				)
			);
		}

		$url      = 'https://api.openai.com/v1/threads/' . $thread_id . '/runs/' . $run_id;
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'OpenAI-Beta'   => 'assistants=v2',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => $response->get_error_message(),
					),
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => $body['error']['message'],
					),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $body,
			)
		);
	}

	/**
	 * Get thread.
	 * 
	 * @param \WP_REST_Request $request Request object.
	 * 
	 * @return \WP_REST_Response
	 */
	public function get( $request ) {
		$api_key   = get_option( 'open_ai_api_key' );
		$thread_id = $request->get_param( 'thread_id' );

		if ( empty( $api_key ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => __( 'API key is missing.', 'quickwp' ),
					),
				)
			);
		}

		$url      = 'https://api.openai.com/v1/threads/' . $thread_id . '/messages';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'OpenAI-Beta'   => 'assistants=v2',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => $response->get_error_message(),
					),
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => $body['error']['message'],
					),
				)
			);
		}

		$data = self::process_json_from_response( $body['data'] );

		if ( false !== $data ) {
			self::extract_data( $data );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $body,
			)
		);
	}

	/**
	 * Get templates.
	 * 
	 * @param \WP_REST_Request $request Request object.
	 * 
	 * @return \WP_REST_Response
	 */
	public function templates( $request ) { // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable
		$templates = array();

		$directory = new \DirectoryIterator( QUICKWP_APP_PATH . '/templates' );

		foreach ( $directory as $file ) {
			if ( $file->isDot() || $file->isDir() ) {
				continue;
			}

			$extension = pathinfo( $file->getFilename(), PATHINFO_EXTENSION );

			if ( 'json' !== $extension ) {
				continue;
			}

			$content = file_get_contents( $file->getPathname() );

			if ( empty( $content ) ) {
				continue;
			}

			$template = json_decode( $content, true );

			if ( empty( $template ) ) {
				continue;
			}

			$templates[] = $template;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $templates,
			)
		);
	}

	/**
	 * Export.
	 * 
	 * @param \WP_REST_Request $request Request object.
	 * 
	 * @return \WP_REST_Response
	 */
	public function export( $request ) {
		$api_key = get_option( 'open_ai_api_key' );

		if ( empty( $api_key ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => __( 'API key is missing.', 'quickwp' ),
					),
				)
			);
		}

		$title       = $request->get_param( 'title' );
		$description = $request->get_param( 'description' );
		$images      = $request->get_param( 'images' );
		$slug        = $request->get_param( 'slug' );

		if ( empty( $slug ) ) {
			$slug = sanitize_title( $title );
		}

		$thread = $this->create_thread(
			$api_key,
			sprintf(
				'Create a WordPress theme named %s with the description %s and the images %s.',
				$title,
				$description,
				wp_json_encode( $images )
			)
		);

		if ( is_wp_error( $thread ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => $thread->get_error_message(),
					),
				)
			);
		}

		$thread_id = $thread['id'];

		$run = $this->run_thread( $api_key, $thread_id, 'export' );

		if ( is_wp_error( $run ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array(
						'message' => $run->get_error_message(),
					),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'thread_id' => $thread_id,
					'run_id'    => $run['id'],
				),
			)
		);
	}

	/**
	 * Process JSON from response.
	 *
	 * @param array<object> $data Response.
	 * 
	 * @throws \Exception Exception in case of invalid JSON.
	 * 
	 * @return array<object>|false
	 */
	private static function process_json_from_response( $data ) {
		// Find the target item.
		$target = current( $data );

		if ( false === $target || ! isset( $target->content ) ) {
			return false;
		}
	
		// Extract the JSON string.
		$json_string = $target->content[0]->text->value;

		try {
			$json_object = json_decode( $json_string, true );

			if ( is_array( $json_object ) ) {
				return $json_object;
			}

			throw new \Exception( 'Invalid JSON' );
		} catch ( \Exception $e ) {
			if ( substr( $json_string, 0, 7 ) === '```json' && substr( trim( $json_string ), -3 ) === '```' ) {
				$cleaned_json = trim( str_replace( array( '```json', '```' ), '', $json_string ) );
				$json_object  = json_decode( $cleaned_json, true );

				if ( is_array( $json_object ) ) {
					return $json_object;
				}
			}
		}

		return false;
	}

	/**
	 * Extract Data.
	 * 
	 * @param array<object> $items Items.
	 * 
	 * @return void
	 */
	private static function extract_data( $items ) {
		foreach ( $items as $item ) {
			if ( ! isset( $item['slug'] ) || ! isset( $item['order'] ) || ! isset( $item['strings'] ) ) {
				continue;
			}

			$strings = $item['strings'];

			foreach ( $strings as $string ) {
				add_filter(
					'quickwp/' . $string['slug'],
					function () use( $string ) {
						return esc_html( $string['value'] );
					} 
				);
			}

			if ( isset( $item['images'] ) ) {
				$images = $item['images'];

				foreach ( $images as $image ) {
					add_filter(
						'quickwp/' . $image['slug'],
						function ( $value ) use( $image ) {
							if ( filter_var( $image['src'], FILTER_VALIDATE_URL ) && ( strpos( $image['src'], 'pexels.com' ) !== false ) ) {
								return esc_url( $image['src'] );
							}

							return $value;
						} 
					);
				}
			}
		}
	}
}