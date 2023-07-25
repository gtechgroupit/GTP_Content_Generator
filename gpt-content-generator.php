<?php
/*
Plugin Name: Generatore di Contenuti GPT
Description: Genera contenuto automatico utilizzando le API di OpenAI.
Version: 0.8
Author: Gianluca Gentile
Author URI: https://gtechgroup.it
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: generatore-contenuti-gpt
Domain Path: /languages
Requires at least: 6.2
Requires PHP: 8.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GPTContentGenerator {
	// Definire i nomi delle opzioni e l'URL delle API come costanti
	const OPTION_NAME_API_KEY           = 'openai_api_key';
	const OPTION_NAME_TOKEN_COUNT       = 'openai_token_count';
	const OPTION_NAME_PROMPT            = 'openai_prompt';
	const OPTION_NAME_TEMPERATURE       = 'openai_temperature';
	const OPTION_NAME_FREQUENCY_PENALTY = 'openai_frequency_penalty';
	const OPTION_NAME_PRESENCE_PENALTY  = 'openai_presence_penalty';
	const API_URL                       = 'https://api.openai.com/v1/chat/completions';

	public function __construct() {
		// Aggiungere le azioni e i filtri necessari
		add_action( 'admin_menu', array( $this, 'aggiungi_pagina_plugin' ) );
		add_action( 'admin_init', array( $this, 'inizializza_impostazioni' ) );
		add_action( 'wp_ajax_generate_content', array( $this, 'ajax_genera_contenuto' ) );
		// add_action( 'admin_enqueue_scripts', array( $this, 'carica_script_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'carica_script_admin_localize' ), 15 );
		add_action( 'admin_menu', array( $this, 'aggiungi_pagina_plugin' ) );
		add_action( 'wp_ajax_chat_with_gpt', array( $this, 'ajax_chat_con_gpt' ) ); // aggiungi questa linea
		add_action( 'admin_init', array( $this, 'wp_tinymce_button' ) );
	}

	public function wp_tinymce_button() {
		add_filter( 'mce_buttons', array( $this, 'registra_pulsante' ), 20, 1 );
		add_filter( 'mce_external_plugins', array( $this, 'registra_plugin_tinymce' ), 20, 1 );
	}

	// Aggiungere la pagina del menu del plugin
	public function aggiungi_pagina_plugin() {
		add_menu_page(
			'Impostazioni Generatore di Contenuti GPT',
			'Generatore di Contenuti GPT',
			'manage_options',
			'gpt-content-generator',
			array( $this, 'crea_pagina_admin' )
		);

		add_submenu_page(
			'gpt-content-generator',
			'Log degli errori',
			'Error Log',
			'manage_options',
			'gpt-content-generator-error-log',
			array( $this, 'crea_pagina_log_errori' )
		);

		// Aggiungi una nuova pagina di sottomenu per la chat
		add_submenu_page(
			'gpt-content-generator',
			'Chat con GPT',
			'Chat con GPT',
			'manage_options',
			'gpt-content-generator-chat',
			array( $this, 'crea_pagina_chat' )
		);
	}

	// Creare la pagina di chat
	public function crea_pagina_chat() {
		?>
		<div class="wrap">
			<h2>Chat con GPT</h2>
			<!-- Qui dovresti inserire il tuo codice HTML per il campo di input della chat e l'area di visualizzazione -->
			<div id="gpt-chat-input"></div>
			<div id="gpt-chat-display"></div>
		</div>
		<?php
	}

	// Metodo AJAX per la chat con GPT
	public function ajax_chat_con_gpt() {
		check_ajax_referer( 'gpt_content_generator_nonce', 'security' );

		if ( ! isset( $_POST['message'] ) ) {
			wp_send_json_error( 'Manca il messaggio.' );
		}

		$message  = sanitize_text_field( $_POST['message'] );
		$response = $this->esegui_richiesta_api( $message, 100 ); // ad esempio, ho impostato un limite di 100 token

		if ( is_wp_error( $response ) ) {
			$this->registra_errore( $response->get_error_message() );
			wp_send_json_error( $response->get_error_message() );
		}

		// $this->log( wp_remote_retrieve_body( $response ) );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			$this->registra_errore( 'Le API di OpenAI non hanno restituito alcun contenuto.' );
			wp_send_json_error( 'Le API di OpenAI non hanno restituito alcun contenuto.' );
		}

		wp_send_json_success( trim( $body['choices'][0]['message']['content'] ) );
	}

	// Creare la pagina di amministrazione del plugin
	public function crea_pagina_admin() {
		?>
		<div class="wrap">
			<h2>Generatore di Contenuti GPT</h2>
			<form method="post" action="options.php">
			<?php
				settings_fields( 'gpt_content_generator_option_group' );
				do_settings_sections( 'gpt-content-generator' );
				submit_button();
			?>
			</form>
		</div>
		<?php
	}

	// Creare la pagina di log degli errori
	public function crea_pagina_log_errori() {
		$error_log = get_option( 'gpt_content_generator_error_log', array() );
		?>
		<div class="wrap">
			<h2>Log degli errori di Generatore di Contenuti GPT</h2>
			<?php if ( empty( $error_log ) ) : ?>
				<p>Nessun errore registrato.</p>
			<?php else : ?>
				<ul>
					<?php foreach ( $error_log as $error ) : ?>
						<li style="color: red;"><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	// Inizializzare le impostazioni
	public function inizializza_impostazioni() {
		register_setting( 'gpt_content_generator_option_group', self::OPTION_NAME_API_KEY, 'sanitize_text_field' );
		register_setting( 'gpt_content_generator_option_group', self::OPTION_NAME_TOKEN_COUNT, 'intval' );
		register_setting( 'gpt_content_generator_option_group', self::OPTION_NAME_PROMPT, 'sanitize_text_field' );
		register_setting( 'gpt_content_generator_option_group', self::OPTION_NAME_TEMPERATURE, 'floatval' );
		register_setting( 'gpt_content_generator_option_group', self::OPTION_NAME_FREQUENCY_PENALTY, 'floatval' );
		register_setting( 'gpt_content_generator_option_group', self::OPTION_NAME_PRESENCE_PENALTY, 'floatval' );
		register_setting( 'gpt_content_generator_option_group', 'gpt_content_generator_error_log' );

		add_settings_section(
			'setting_section_id',
			'Impostazioni',
			array( $this, 'stampa_informazioni_sezione' ),
			'gpt-content-generator'
		);

		add_settings_field(
			self::OPTION_NAME_API_KEY,
			'Chiave API di OpenAI',
			array( $this, 'callback_campo_impostazioni' ),
			'gpt-content-generator',
			'setting_section_id',
			array(
				'label_for'   => self::OPTION_NAME_API_KEY,
				'description' => 'Inserisci la chiave API fornita da OpenAI.',
			)
		);

		add_settings_field(
			self::OPTION_NAME_TOKEN_COUNT,
			'Numero di Token',
			array( $this, 'callback_campo_impostazioni' ),
			'gpt-content-generator',
			'setting_section_id',
			array(
				'label_for'   => self::OPTION_NAME_TOKEN_COUNT,
				'description' => 'Inserisci il numero massimo di token da generare per ogni richiesta.',
			)
		);

		add_settings_field(
			self::OPTION_NAME_PROMPT,
			'Prompt',
			array( $this, 'callback_campo_impostazioni' ),
			'gpt-content-generator',
			'setting_section_id',
			array(
				'label_for'   => self::OPTION_NAME_PROMPT,
				'description' => 'Inserisci il prompt da utilizzare per iniziare la generazione del testo.',
			)
		);

		add_settings_field(
			self::OPTION_NAME_TEMPERATURE,
			'Temperatura di OpenAI',
			array( $this, 'callback_campo_impostazioni' ),
			'gpt-content-generator',
			'setting_section_id',
			array(
				'label_for'   => self::OPTION_NAME_TEMPERATURE,
				'description' => 'Inserisci la "temperatura" desiderata per la generazione del contenuto da parte di OpenAI. Un valore più alto porta a risultati più casuali.',
			)
		);

		add_settings_field(
			self::OPTION_NAME_FREQUENCY_PENALTY,
			'Penalità di Frequenza OpenAI',
			array( $this, 'callback_campo_impostazioni' ),
			'gpt-content-generator',
			'setting_section_id',
			array(
				'label_for'   => self::OPTION_NAME_FREQUENCY_PENALTY,
				'description' => 'Inserisci la penalità di frequenza desiderata. Un valore più alto riduce la probabilità di parole ripetute.',
			)
		);

		add_settings_field(
			self::OPTION_NAME_PRESENCE_PENALTY,
			'Penalità di Presenza OpenAI',
			array( $this, 'callback_campo_impostazioni' ),
			'gpt-content-generator',
			'setting_section_id',
			array(
				'label_for'   => self::OPTION_NAME_PRESENCE_PENALTY,
				'description' => 'Inserisci la penalità di presenza desiderata. Un valore più alto rende meno probabile la comparsa di nuovi concetti.',
			)
		);
	}

	// Stampa le informazioni della sezione
	public function stampa_informazioni_sezione() {
		print 'Inserisci le tue impostazioni qui sotto:';
	}

	// Callback per i campi delle impostazioni
	public function callback_campo_impostazioni( $args ) {
		$option_name  = $args['label_for'];
		$option_value = get_option( $option_name );
		echo '<input id="' . $option_name . '" name="' . $option_name . '" type="text" value="' . $option_value . '">';

		if ( isset( $args['description'] ) ) {
			echo '<p class="description">' . $args['description'] . '</p>';
		}
	}

	// Metodo AJAX per generare contenuto
	public function ajax_genera_contenuto() {
		check_admin_referer( 'gpt_content_generator_nonce', 'security' );

		if ( ! isset( $_POST['post_id'] ) ) {
			wp_die( 'Manca l\'ID del post.' );
		}

		$post_id      = intval( $_POST['post_id'] );
		$post_content = get_post_field( 'post_content', $post_id );
		$prompt       = 'Scrivi un articolo informativo basato sul contenuto seguente: ' . $post_content;

		// sleep(10);
		// wp_send_json_success( array( 'content' => $post_content ) );

		$token_count = get_option( self::OPTION_NAME_TOKEN_COUNT );

		$response = $this->esegui_richiesta_api( $prompt, intval( $token_count ) );

		$this->log(
			array(
				'prompt' => $prompt,
				wp_remote_retrieve_body( $response ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->registra_errore( $response->get_error_message() );
			wp_send_json_error( array( 'error' => $response->get_error_message() ) );
			// wp_die( $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->log( $body );
		$this->log( $body['choices'][0]['message']['content'] );

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			$this->registra_errore( 'Le API di OpenAI non hanno restituito alcun contenuto.' );
			wp_send_json_error( array( 'error' => 'Le API di OpenAI non hanno restituito alcun contenuto.' ) );
			// wp_die( 'Le API di OpenAI non hanno restituito alcun contenuto.' );
		}

		// Aggiorna il contenuto del post e reindirizza all'editor del post
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $post_content . "\n\n" . trim( $body['choices'][0]['message']['content'] ),
			)
		);

		wp_send_json_success( array( 'content' => "\n\n" . trim( $body['choices'][0]['message']['content'] ) ) );
		// wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
		// exit;
	}

	// Eseguire una richiesta alle API
	public function esegui_richiesta_api( $prompt, $token_count ) {
		$api_key           = get_option( self::OPTION_NAME_API_KEY );
		$temperature       = (float) get_option( self::OPTION_NAME_TEMPERATURE );
		$frequency_penalty = (float) get_option( self::OPTION_NAME_FREQUENCY_PENALTY );
		$presence_penalty  = (float) get_option( self::OPTION_NAME_PRESENCE_PENALTY );

		if ( ! $api_key ) {
			return new WP_Error( 'openai_api_key_missing', 'Manca la chiave API di OpenAI nelle impostazioni del plugin.' );
		}

		$args = array(
			'headers'     => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'        => json_encode(
				array(
					'model'             => 'gpt-4',
					'messages'          => array(
						array(
							'role'    => 'assistant',
							'content' => $prompt,
						),
					),
					'max_tokens'        => $token_count,
					'temperature'       => $temperature,
					'frequency_penalty' => $frequency_penalty,
					'presence_penalty'  => $presence_penalty,
				)
				/*
				array(
				'model'             => 'text-davinci-003',
				'prompt'            => $prompt,
				'max_tokens'        => $token_count,
				'temperature'       => $temperature,
				'frequency_penalty' => $frequency_penalty,
				'presence_penalty'  => $presence_penalty,
				)*/
			),
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
		);

		/*$this->log(
			json_encode(
				array(
					'model'             => 'gpt-3.5-turbo',
					'messages'          => array(
						array(
							'role'    => 'assistant',
							'content' => $prompt,
						),
					),
					'max_tokens'        => $token_count,
					'temperature'       => $temperature,
					'frequency_penalty' => $frequency_penalty,
					'presence_penalty'  => $presence_penalty,
				)
			)
		);*/

		return wp_remote_post( self::API_URL, $args );
	}

	// Registrare un errore
	public function registra_errore( $message ) {
		$error_log = get_option( 'gpt_content_generator_error_log', array() );

		if ( ! is_array( $error_log ) ) {
			$error_log = array();
		}

		array_push( $error_log, $message );
		update_option( 'gpt_content_generator_error_log', $error_log );
	}

	// Registrare il pulsante
	public function registra_pulsante( $buttons ) {
		array_push( $buttons, 'separator', 'gpt_content_generator' );
		return $buttons;
	}

	// Registrare il plugin TinyMCE
	public function registra_plugin_tinymce( $plugin_array ) {
		$plugin_array['gpt_content_generator'] = plugins_url( 'script.js', __FILE__ );
		return $plugin_array;
	}

	// Caricare gli script dell'admin
	public function carica_script_admin( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$post_id = get_the_ID();
		$post    = get_post( $post_id );
		$content = apply_filters( 'the_content', $post->post_content );

		$script_data = array(
			'iconUrl'       => plugins_url( 'icona/gpt-icon.png', __FILE__ ),
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'gpt_content_generator_nonce' ),
			'postId'        => $post_id,
			'postContent'   => $content, // aggiungi il contenuto del post
			'defaultPrompt' => get_option( self::OPTION_NAME_PROMPT ), // aggiungi il prompt di default
		);

		wp_register_script( 'gpt-content-generator', plugins_url( 'script.js', __FILE__ ) );
		wp_localize_script( 'gpt-content-generator', 'gptContentGenerator', $script_data );
		wp_enqueue_script( 'gpt-content-generator' );
	}

	public function carica_script_admin_localize( $hook ) {
		if ( ! wp_scripts()->query( 'jquery-blockui', 'registered' ) ) {
			wp_register_script( 'jquery-blockui', plugins_url( 'jquery-blockui/jquery.blockUI.min.js', __FILE__ ), array( 'jquery' ), '2.70', true );
		}

		wp_enqueue_script( 'jquery-blockui' );

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$post_id = get_the_ID();
		$post    = get_post( $post_id );
		$content = apply_filters( 'the_content', $post->post_content );

		$script_data = array(
			'iconUrl'       => plugins_url( 'icona/gpt-icon.png', __FILE__ ),
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'gpt_content_generator_nonce' ),
			'postId'        => $post_id,
			'postContent'   => $content, // aggiungi il contenuto del post
			'defaultPrompt' => get_option( self::OPTION_NAME_PROMPT ), // aggiungi il prompt di default
		);

		?>
		<!-- TinyMCE Shortcode Plugin -->
		<script type='text/javascript'>
		var gptContentGenerator = <?php echo wp_json_encode( $script_data ); ?>;
		</script>
		<!-- TinyMCE Shortcode Plugin -->
		<?php
	}

	public function get_log_dir( string $handle ) {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/' . $handle . '-logs';
		wp_mkdir_p( $log_dir );
		return $log_dir;
	}

	public function get_log_file_name( string $handle ) {
		if ( function_exists( 'wp_hash' ) ) {
			$date_suffix = date( 'Y-m-d', time() );
			$hash_suffix = wp_hash( $handle );
			return $this->get_log_dir( $handle ) . '/' . sanitize_file_name( implode( '-', array( $handle, $date_suffix, $hash_suffix ) ) . '.log' );
		}

		return $this->get_log_dir( $handle ) . '/' . $handle . '-' . date( 'Y-m-d', time() ) . '.log';
	}

	public function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( print_r( $message, true ), array( 'source' => 'GPTContentGenerator' ) );
		} else {
			error_log( date( '[Y-m-d H:i:s e] ' ) . print_r( $message, true ) . PHP_EOL, 3, $this->get_log_file_name( 'GPTContentGenerator' ) );
		}
	}

}

new GPTContentGenerator();
