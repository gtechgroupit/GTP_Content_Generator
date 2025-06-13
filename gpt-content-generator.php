<?php
/*
Plugin Name: Generatore di Contenuti GPT Pro
Description: Genera contenuto automatico utilizzando le API di OpenAI con sicurezza avanzata.
Version: 2.0
Author: Gianluca Gentile
Author URI: https://gtechgroup.it
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: gpt-content-generator-pro
Domain Path: /languages
Requires at least: 6.2
Requires PHP: 8.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'GCG_VERSION', '2.0' );
define( 'GCG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GCG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GCG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader for classes
spl_autoload_register( function ( $class ) {
	$prefix = 'GCG\\';
	$base_dir = GCG_PLUGIN_DIR . 'includes/';
	
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}
	
	$relative_class = substr( $class, $len );
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
	
	if ( file_exists( $file ) ) {
		require $file;
	}
});

// Main plugin class
class GPTContentGeneratorPro {
	
	private static $instance = null;
	
	// Plugin options
	const OPTION_PREFIX = 'gcg_';
	const OPTION_API_KEY = 'gcg_openai_api_key';
	const OPTION_MODEL = 'gcg_openai_model';
	const OPTION_TOKEN_COUNT = 'gcg_token_count';
	const OPTION_PROMPT_TEMPLATE = 'gcg_prompt_template';
	const OPTION_TEMPERATURE = 'gcg_temperature';
	const OPTION_FREQUENCY_PENALTY = 'gcg_frequency_penalty';
	const OPTION_PRESENCE_PENALTY = 'gcg_presence_penalty';
	const OPTION_RATE_LIMIT = 'gcg_rate_limit';
	const OPTION_CACHE_DURATION = 'gcg_cache_duration';
	const OPTION_ALLOWED_POST_TYPES = 'gcg_allowed_post_types';
	const OPTION_ALLOWED_ROLES = 'gcg_allowed_roles';
	
	// API constants
	const API_URL = 'https://api.openai.com/v1/chat/completions';
	const TRANSIENT_PREFIX = 'gcg_cache_';
	const RATE_LIMIT_PREFIX = 'gcg_rate_';
	
	// Available models
	const AVAILABLE_MODELS = [
		'gpt-4' => 'GPT-4',
		'gpt-4-32k' => 'GPT-4 32K',
		'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
		'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K'
	];
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		$this->init_hooks();
	}
	
	private function init_hooks() {
		// Activation/Deactivation hooks
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
		register_uninstall_hook( __FILE__, [ __CLASS__, 'uninstall' ] );
		
		// Admin hooks
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'init_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		
		// AJAX hooks
		add_action( 'wp_ajax_gcg_generate_content', [ $this, 'ajax_generate_content' ] );
		add_action( 'wp_ajax_gcg_get_prompt_preview', [ $this, 'ajax_get_prompt_preview' ] );
		add_action( 'wp_ajax_gcg_clear_cache', [ $this, 'ajax_clear_cache' ] );
		add_action( 'wp_ajax_gcg_test_api', [ $this, 'ajax_test_api' ] );
		
		// TinyMCE hooks
		add_action( 'admin_init', [ $this, 'init_tinymce' ] );
		
		// Filter hooks
		add_filter( 'plugin_action_links_' . GCG_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );
		add_filter( 'http_request_timeout', [ $this, 'filter_request_timeout' ], 10, 2 );
		
		// Load textdomain
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		
		// Add meta box for custom prompts
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_post_meta' ] );
	}
	
	public function activate() {
		// Create database tables if needed
		$this->create_tables();
		
		// Set default options
		$this->set_default_options();
		
		// Schedule cron jobs
		if ( ! wp_next_scheduled( 'gcg_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'gcg_cleanup_logs' );
		}
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	public function deactivate() {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'gcg_cleanup_logs' );
		
		// Clear cache
		$this->clear_all_cache();
	}
	
	public static function uninstall() {
		// Remove all options
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gcg_%'" );
		
		// Drop custom tables
		$table_name = $wpdb->prefix . 'gcg_logs';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		
		// Clear all transients
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gcg_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_gcg_%'" );
	}
	
	private function create_tables() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'gcg_logs';
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			post_id bigint(20) DEFAULT NULL,
			prompt text NOT NULL,
			response text,
			tokens_used int(11) DEFAULT 0,
			model varchar(50) DEFAULT NULL,
			status varchar(20) DEFAULT 'success',
			error_message text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	private function set_default_options() {
		$defaults = [
			self::OPTION_MODEL => 'gpt-3.5-turbo',
			self::OPTION_TOKEN_COUNT => 500,
			self::OPTION_PROMPT_TEMPLATE => 'Scrivi un articolo SEO-friendly e informativo basato sul seguente contenuto: {content}',
			self::OPTION_TEMPERATURE => 0.7,
			self::OPTION_FREQUENCY_PENALTY => 0.0,
			self::OPTION_PRESENCE_PENALTY => 0.0,
			self::OPTION_RATE_LIMIT => 10, // requests per hour
			self::OPTION_CACHE_DURATION => 3600, // 1 hour
			self::OPTION_ALLOWED_POST_TYPES => ['post', 'page'],
			self::OPTION_ALLOWED_ROLES => ['administrator', 'editor']
		];
		
		foreach ( $defaults as $option => $value ) {
			if ( get_option( $option ) === false ) {
				update_option( $option, $value );
			}
		}
	}
	
	public function load_textdomain() {
		load_plugin_textdomain( 'gpt-content-generator-pro', false, dirname( GCG_PLUGIN_BASENAME ) . '/languages' );
	}
	
	public function add_admin_menu() {
		add_menu_page(
			__( 'GPT Content Generator', 'gpt-content-generator-pro' ),
			__( 'GPT Generator', 'gpt-content-generator-pro' ),
			'manage_options',
			'gpt-content-generator',
			[ $this, 'render_admin_page' ],
			'dashicons-edit-large',
			30
		);
		
		add_submenu_page(
			'gpt-content-generator',
			__( 'Settings', 'gpt-content-generator-pro' ),
			__( 'Settings', 'gpt-content-generator-pro' ),
			'manage_options',
			'gpt-content-generator',
			[ $this, 'render_admin_page' ]
		);
		
		add_submenu_page(
			'gpt-content-generator',
			__( 'Usage Logs', 'gpt-content-generator-pro' ),
			__( 'Usage Logs', 'gpt-content-generator-pro' ),
			'manage_options',
			'gcg-logs',
			[ $this, 'render_logs_page' ]
		);
		
		add_submenu_page(
			'gpt-content-generator',
			__( 'Prompt Templates', 'gpt-content-generator-pro' ),
			__( 'Templates', 'gpt-content-generator-pro' ),
			'manage_options',
			'gcg-templates',
			[ $this, 'render_templates_page' ]
		);
	}
	
	public function init_settings() {
		register_setting( 'gcg_settings', self::OPTION_API_KEY, [
			'sanitize_callback' => [ $this, 'sanitize_api_key' ]
		]);
		
		register_setting( 'gcg_settings', self::OPTION_MODEL );
		register_setting( 'gcg_settings', self::OPTION_TOKEN_COUNT, [
			'sanitize_callback' => 'absint'
		]);
		register_setting( 'gcg_settings', self::OPTION_PROMPT_TEMPLATE, [
			'sanitize_callback' => 'sanitize_textarea_field'
		]);
		register_setting( 'gcg_settings', self::OPTION_TEMPERATURE, [
			'sanitize_callback' => [ $this, 'sanitize_float' ]
		]);
		register_setting( 'gcg_settings', self::OPTION_FREQUENCY_PENALTY, [
			'sanitize_callback' => [ $this, 'sanitize_float' ]
		]);
		register_setting( 'gcg_settings', self::OPTION_PRESENCE_PENALTY, [
			'sanitize_callback' => [ $this, 'sanitize_float' ]
		]);
		register_setting( 'gcg_settings', self::OPTION_RATE_LIMIT, [
			'sanitize_callback' => 'absint'
		]);
		register_setting( 'gcg_settings', self::OPTION_CACHE_DURATION, [
			'sanitize_callback' => 'absint'
		]);
		register_setting( 'gcg_settings', self::OPTION_ALLOWED_POST_TYPES, [
			'sanitize_callback' => [ $this, 'sanitize_array' ]
		]);
		register_setting( 'gcg_settings', self::OPTION_ALLOWED_ROLES, [
			'sanitize_callback' => [ $this, 'sanitize_array' ]
		]);
	}
	
	public function sanitize_api_key( $key ) {
		$key = sanitize_text_field( $key );
		// Encrypt API key before storing
		if ( ! empty( $key ) && strpos( $key, 'sk-' ) === 0 ) {
			return $this->encrypt_data( $key );
		}
		return $key;
	}
	
	public function sanitize_float( $value ) {
		return floatval( $value );
	}
	
	public function sanitize_array( $value ) {
		if ( ! is_array( $value ) ) {
			return [];
		}
		return array_map( 'sanitize_text_field', $value );
	}
	
	private function encrypt_data( $data ) {
		$key = wp_salt( 'auth' );
		$cipher = 'AES-256-CBC';
		$ivlen = openssl_cipher_iv_length( $cipher );
		$iv = openssl_random_pseudo_bytes( $ivlen );
		$ciphertext = openssl_encrypt( $data, $cipher, $key, 0, $iv );
		return base64_encode( $iv . $ciphertext );
	}
	
	private function decrypt_data( $data ) {
		$key = wp_salt( 'auth' );
		$cipher = 'AES-256-CBC';
		$data = base64_decode( $data );
		$ivlen = openssl_cipher_iv_length( $cipher );
		$iv = substr( $data, 0, $ivlen );
		$ciphertext = substr( $data, $ivlen );
		return openssl_decrypt( $ciphertext, $cipher, $key, 0, $iv );
	}
	
	public function enqueue_admin_scripts( $hook ) {
		// Global admin styles
		wp_enqueue_style( 
			'gcg-admin', 
			GCG_PLUGIN_URL . 'assets/css/admin.css', 
			[], 
			GCG_VERSION 
		);
		
		// Plugin pages scripts
		if ( strpos( $hook, 'gpt-content-generator' ) !== false || strpos( $hook, 'gcg-' ) !== false ) {
			wp_enqueue_script( 
				'gcg-admin', 
				GCG_PLUGIN_URL . 'assets/js/admin.js', 
				['jquery', 'wp-i18n'], 
				GCG_VERSION, 
				true 
			);
			
			wp_localize_script( 'gcg-admin', 'gcg', [
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'gcg_ajax' ),
				'strings' => [
					'confirm_clear_cache' => __( 'Are you sure you want to clear the cache?', 'gpt-content-generator-pro' ),
					'api_test_success' => __( 'API connection successful!', 'gpt-content-generator-pro' ),
					'api_test_error' => __( 'API connection failed: ', 'gpt-content-generator-pro' ),
				]
			]);
		}
		
		// Post editor scripts
		if ( in_array( $hook, ['post.php', 'post-new.php'] ) ) {
			$post_type = get_post_type();
			$allowed_post_types = get_option( self::OPTION_ALLOWED_POST_TYPES, ['post', 'page'] );
			
			if ( in_array( $post_type, $allowed_post_types ) && $this->user_can_generate() ) {
				wp_enqueue_script( 
					'jquery-blockui', 
					GCG_PLUGIN_URL . 'assets/js/jquery.blockUI.min.js', 
					['jquery'], 
					'2.70', 
					true 
				);
			}
		}
	}
	
	public function init_tinymce() {
		if ( ! $this->user_can_generate() ) {
			return;
		}
		
		add_filter( 'mce_buttons', [ $this, 'register_tinymce_button' ] );
		add_filter( 'mce_external_plugins', [ $this, 'register_tinymce_plugin' ] );
		add_action( 'admin_head', [ $this, 'tinymce_variables' ] );
	}
	
	public function register_tinymce_button( $buttons ) {
		array_push( $buttons, 'separator', 'gcg_generate' );
		return $buttons;
	}
	
	public function register_tinymce_plugin( $plugins ) {
		$plugins['gcg_generate'] = GCG_PLUGIN_URL . 'assets/js/tinymce-plugin.js';
		return $plugins;
	}
	
	public function tinymce_variables() {
		?>
		<script type="text/javascript">
		var gcg_tinymce = {
			ajaxurl: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
			nonce: '<?php echo wp_create_nonce( 'gcg_ajax' ); ?>',
			post_id: <?php echo get_the_ID(); ?>,
			icon_url: '<?php echo GCG_PLUGIN_URL . 'assets/images/icon.png'; ?>',
			strings: {
				button_title: '<?php _e( 'Generate Content with AI', 'gpt-content-generator-pro' ); ?>',
				generating: '<?php _e( 'Generating content...', 'gpt-content-generator-pro' ); ?>',
				error_empty: '<?php _e( 'Please write some content first.', 'gpt-content-generator-pro' ); ?>',
				error_generic: '<?php _e( 'An error occurred while generating content.', 'gpt-content-generator-pro' ); ?>'
			}
		};
		</script>
		<?php
	}
	
	public function add_meta_boxes() {
		$allowed_post_types = get_option( self::OPTION_ALLOWED_POST_TYPES, ['post', 'page'] );
		
		foreach ( $allowed_post_types as $post_type ) {
			add_meta_box(
				'gcg_custom_prompt',
				__( 'GPT Content Generator', 'gpt-content-generator-pro' ),
				[ $this, 'render_meta_box' ],
				$post_type,
				'side',
				'default'
			);
		}
	}
	
	public function render_meta_box( $post ) {
		wp_nonce_field( 'gcg_save_meta', 'gcg_meta_nonce' );
		
		$custom_prompt = get_post_meta( $post->ID, '_gcg_custom_prompt', true );
		$use_custom = get_post_meta( $post->ID, '_gcg_use_custom_prompt', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="gcg_use_custom_prompt" value="1" <?php checked( $use_custom, '1' ); ?>>
				<?php _e( 'Use custom prompt for this post', 'gpt-content-generator-pro' ); ?>
			</label>
		</p>
		<p>
			<label for="gcg_custom_prompt"><?php _e( 'Custom Prompt:', 'gpt-content-generator-pro' ); ?></label>
			<textarea id="gcg_custom_prompt" name="gcg_custom_prompt" rows="4" style="width:100%;"><?php echo esc_textarea( $custom_prompt ); ?></textarea>
		</p>
		<p class="description">
			<?php _e( 'Use {content} as placeholder for post content.', 'gpt-content-generator-pro' ); ?>
		</p>
		<?php
	}
	
	public function save_post_meta( $post_id ) {
		if ( ! isset( $_POST['gcg_meta_nonce'] ) || ! wp_verify_nonce( $_POST['gcg_meta_nonce'], 'gcg_save_meta' ) ) {
			return;
		}
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		$use_custom = isset( $_POST['gcg_use_custom_prompt'] ) ? '1' : '0';
		update_post_meta( $post_id, '_gcg_use_custom_prompt', $use_custom );
		
		if ( isset( $_POST['gcg_custom_prompt'] ) ) {
			update_post_meta( $post_id, '_gcg_custom_prompt', sanitize_textarea_field( $_POST['gcg_custom_prompt'] ) );
		}
	}
	
	public function ajax_generate_content() {
		check_ajax_referer( 'gcg_ajax', 'nonce' );
		
		if ( ! $this->user_can_generate() ) {
			wp_send_json_error( __( 'You do not have permission to generate content.', 'gpt-content-generator-pro' ) );
		}
		
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Invalid post ID or insufficient permissions.', 'gpt-content-generator-pro' ) );
		}
		
		// Check rate limit
		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error( __( 'Rate limit exceeded. Please try again later.', 'gpt-content-generator-pro' ) );
		}
		
		$post = get_post( $post_id );
		$content = $post->post_content;
		
		if ( empty( trim( $content ) ) ) {
			wp_send_json_error( __( 'Post content is empty.', 'gpt-content-generator-pro' ) );
		}
		
		// Get prompt
		$prompt = $this->get_prompt_for_post( $post_id, $content );
		
		// Check cache first
		$cache_key = $this->get_cache_key( $prompt );
		$cached_response = get_transient( $cache_key );
		
		if ( $cached_response !== false ) {
			$this->log_usage( $post_id, $prompt, $cached_response, 0, 'cache' );
			wp_send_json_success( [
				'content' => $cached_response,
				'from_cache' => true
			] );
		}
		
		// Make API request
		$response = $this->make_api_request( $prompt );
		
		if ( is_wp_error( $response ) ) {
			$this->log_usage( $post_id, $prompt, '', 0, 'error', $response->get_error_message() );
			wp_send_json_error( $response->get_error_message() );
		}
		
		// Cache the response
		$cache_duration = get_option( self::OPTION_CACHE_DURATION, 3600 );
		if ( $cache_duration > 0 ) {
			set_transient( $cache_key, $response['content'], $cache_duration );
		}
		
		// Update rate limit
		$this->update_rate_limit();
		
		// Log usage
		$this->log_usage( $post_id, $prompt, $response['content'], $response['tokens'], 'success' );
		
		wp_send_json_success( [
			'content' => $response['content'],
			'tokens_used' => $response['tokens'],
			'from_cache' => false
		] );
	}
	
	private function user_can_generate() {
		$allowed_roles = get_option( self::OPTION_ALLOWED_ROLES, ['administrator', 'editor'] );
		$user = wp_get_current_user();
		
		if ( empty( $user->roles ) ) {
			return false;
		}
		
		return ! empty( array_intersect( $allowed_roles, $user->roles ) );
	}
	
	private function check_rate_limit() {
		$user_id = get_current_user_id();
		$rate_limit = get_option( self::OPTION_RATE_LIMIT, 10 );
		
		if ( $rate_limit <= 0 ) {
			return true; // No rate limit
		}
		
		$transient_key = self::RATE_LIMIT_PREFIX . $user_id;
		$requests = get_transient( $transient_key );
		
		if ( $requests === false ) {
			return true;
		}
		
		return $requests < $rate_limit;
	}
	
	private function update_rate_limit() {
		$user_id = get_current_user_id();
		$transient_key = self::RATE_LIMIT_PREFIX . $user_id;
		$requests = get_transient( $transient_key );
		
		if ( $requests === false ) {
			set_transient( $transient_key, 1, HOUR_IN_SECONDS );
		} else {
			set_transient( $transient_key, $requests + 1, HOUR_IN_SECONDS );
		}
	}
	
	private function get_prompt_for_post( $post_id, $content ) {
		$use_custom = get_post_meta( $post_id, '_gcg_use_custom_prompt', true );
		
		if ( $use_custom ) {
			$template = get_post_meta( $post_id, '_gcg_custom_prompt', true );
		} else {
			$template = get_option( self::OPTION_PROMPT_TEMPLATE );
		}
		
		return str_replace( '{content}', $content, $template );
	}
	
	private function get_cache_key( $prompt ) {
		return self::TRANSIENT_PREFIX . md5( $prompt );
	}
	
	private function make_api_request( $prompt ) {
		$api_key = get_option( self::OPTION_API_KEY );
		
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'API key not configured.', 'gpt-content-generator-pro' ) );
		}
		
		// Decrypt API key
		$api_key = $this->decrypt_data( $api_key );
		
		$model = get_option( self::OPTION_MODEL, 'gpt-3.5-turbo' );
		$max_tokens = get_option( self::OPTION_TOKEN_COUNT, 500 );
		$temperature = floatval( get_option( self::OPTION_TEMPERATURE, 0.7 ) );
		$frequency_penalty = floatval( get_option( self::OPTION_FREQUENCY_PENALTY, 0 ) );
		$presence_penalty = floatval( get_option( self::OPTION_PRESENCE_PENALTY, 0 ) );
		
		$args = [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			],
			'body' => json_encode( [
				'model' => $model,
				'messages' => [
					[
						'role' => 'system',
						'content' => 'You are a professional content writer who creates high-quality, SEO-friendly content.'
					],
					[
						'role' => 'user',
						'content' => $prompt
					]
				],
				'max_tokens' => $max_tokens,
				'temperature' => $temperature,
				'frequency_penalty' => $frequency_penalty,
				'presence_penalty' => $presence_penalty,
			] ),
			'timeout' => 120,
			'sslverify' => true,
		];
		
		$response = wp_remote_post( self::API_URL, $args );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? __( 'Unknown API error', 'gpt-content-generator-pro' ) );
		}
		
		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid API response', 'gpt-content-generator-pro' ) );
		}
		
		return [
			'content' => trim( $body['choices'][0]['message']['content'] ),
			'tokens' => $body['usage']['total_tokens'] ?? 0
		];
	}
	
	private function log_usage( $post_id, $prompt, $response, $tokens, $status, $error = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'gcg_logs';
		
		$wpdb->insert(
			$table_name,
			[
				'user_id' => get_current_user_id(),
				'post_id' => $post_id,
				'prompt' => $prompt,
				'response' => $response,
				'tokens_used' => $tokens,
				'model' => get_option( self::OPTION_MODEL, 'gpt-3.5-turbo' ),
				'status' => $status,
				'error_message' => $error,
			],
			['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
		);
	}
	
	public function filter_request_timeout( $timeout, $url ) {
		if ( strpos( $url, 'openai.com' ) !== false ) {
			return 120;
		}
		return $timeout;
	}
	
	public function add_action_links( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=gpt-content-generator' ) . '">' . __( 'Settings', 'gpt-content-generator-pro' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
	
	public function ajax_test_api() {
		check_ajax_referer( 'gcg_ajax', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'gpt-content-generator-pro' ) );
		}
		
		$response = $this->make_api_request( 'Test connection. Reply with: "Connection successful!"' );
		
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}
		
		wp_send_json_success( $response['content'] );
	}
	
	public function ajax_clear_cache() {
		check_ajax_referer( 'gcg_ajax', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'gpt-content-generator-pro' ) );
		}
		
		$this->clear_all_cache();
		wp_send_json_success( __( 'Cache cleared successfully.', 'gpt-content-generator-pro' ) );
	}
	
	private function clear_all_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_" . self::TRANSIENT_PREFIX . "%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_" . self::TRANSIENT_PREFIX . "%'" );
	}
	
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Save settings
		if ( isset( $_POST['submit'] ) ) {
			check_admin_referer( 'gcg_settings' );
			// Settings are saved automatically via register_setting
			echo '<div class="notice notice-success"><p>' . __( 'Settings saved.', 'gpt-content-generator-pro' ) . '</p></div>';
		}
		
		include GCG_PLUGIN_DIR . 'templates/admin-settings.php';
	}
	
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		include GCG_PLUGIN_DIR . 'templates/admin-logs.php';
	}
	
	public function render_templates_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		include GCG_PLUGIN_DIR . 'templates/admin-templates.php';
	}
}

// Initialize the plugin
GPTContentGeneratorPro::get_instance();
