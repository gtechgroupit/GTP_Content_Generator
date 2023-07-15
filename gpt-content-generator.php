<?php
/*
Plugin Name: Generatore di Contenuti GPT
Description: Genera contenuto automatico utilizzando le API di OpenAI.
Version: 1.6
Author: Gianluca Gentile
*/

if (!defined('ABSPATH')) {
    exit;
}

class GPTContentGenerator {
    // Definire i nomi delle opzioni e l'URL delle API come costanti
    const OPTION_NAME_API_KEY = 'openai_api_key';
    const OPTION_NAME_TOKEN_COUNT = 'openai_token_count';
    const OPTION_NAME_PROMPT = 'openai_prompt';
    const API_URL = 'https://api.openai.com/v1/engines/davinci-codex/completions';

    public function __construct() {
        // Aggiungere le azioni e i filtri necessari
        add_action('admin_menu', array($this, 'aggiungi_pagina_plugin'));
        add_action('admin_init', array($this, 'inizializza_impostazioni'));
        add_action('wp_ajax_generate_content', array($this, 'ajax_genera_contenuto'));
        add_filter('mce_buttons', array($this, 'registra_pulsante'));
        add_filter('mce_external_plugins', array($this, 'registra_plugin_tinymce'));
        add_action('admin_enqueue_scripts', array($this, 'carica_script_admin'));
    }

    // Aggiungere la pagina del menu del plugin
    public function aggiungi_pagina_plugin() {
        add_menu_page(
            'Impostazioni Generatore di Contenuti GPT',
            'Generatore di Contenuti GPT',
            'manage_options',
            'gpt-content-generator',
            array($this, 'crea_pagina_admin')
        );

        add_submenu_page(
            'gpt-content-generator',
            'Log degli errori',
            'Error Log',
            'manage_options',
            'gpt-content-generator-error-log',
            array($this, 'crea_pagina_log_errori')
        );
    }

    // Creare la pagina di amministrazione del plugin
    public function crea_pagina_admin() {
        ?>
        <div class="wrap">
            <h2>Generatore di Contenuti GPT</h2>
            <form method="post" action="options.php">
            <?php
                settings_fields('gpt_content_generator_option_group');
                do_settings_sections('gpt-content-generator');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    // Creare la pagina di log degli errori
    public function crea_pagina_log_errori() {
        $error_log = get_option('gpt_content_generator_error_log', array());
        ?>
        <div class="wrap">
            <h2>Log degli errori di Generatore di Contenuti GPT</h2>
            <?php if (empty($error_log)) : ?>
                <p>Nessun errore registrato.</p>
            <?php else : ?>
                <ul>
                    <?php foreach ($error_log as $error) : ?>
                        <li style="color: red;"><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    // Inizializzare le impostazioni
    public function inizializza_impostazioni() {
        register_setting('gpt_content_generator_option_group', self::OPTION_NAME_API_KEY, 'sanitize_text_field');
        register_setting('gpt_content_generator_option_group', self::OPTION_NAME_TOKEN_COUNT, 'intval');
        register_setting('gpt_content_generator_option_group', self::OPTION_NAME_PROMPT, 'sanitize_text_field');
        register_setting('gpt_content_generator_option_group', 'gpt_content_generator_error_log');

        add_settings_section(
            'setting_section_id',
            'Impostazioni',
            array($this, 'stampa_informazioni_sezione'),
            'gpt-content-generator'
        );

        add_settings_field(
            self::OPTION_NAME_API_KEY,
            'Chiave API OpenAI',
            array($this, 'callback_campo_impostazioni'),
            'gpt-content-generator',
            'setting_section_id',
            array('label_for' => self::OPTION_NAME_API_KEY)
        );

        add_settings_field(
            self::OPTION_NAME_TOKEN_COUNT,
            'Numero di token OpenAI',
            array($this, 'callback_campo_impostazioni'),
            'gpt-content-generator',
            'setting_section_id',
            array('label_for' => self::OPTION_NAME_TOKEN_COUNT)
        );

        add_settings_field(
            self::OPTION_NAME_PROMPT,
            'Prompt di OpenAI',
            array($this, 'callback_campo_impostazioni'),
            'gpt-content-generator',
            'setting_section_id',
            array('label_for' => self::OPTION_NAME_PROMPT)
        );
    }

    // Stampa le informazioni della sezione
    public function stampa_informazioni_sezione() {
        print 'Inserisci le tue impostazioni qui sotto:';
    }

    // Callback per i campi delle impostazioni
    public function callback_campo_impostazioni($args) {
        $option_name = $args['label_for'];
        $option_value = get_option($option_name);
        echo '<input id="' . $option_name . '" name="' . $option_name . '" type="text" value="' . $option_value . '">';
    }

    // Metodo AJAX per generare contenuto
public function ajax_genera_contenuto() {
    check_admin_referer('gpt_content_generator_nonce', 'security');

    if (!isset($_POST['post_id'])) {
        wp_die('Manca l\'ID del post.');
    }

    $post_id = intval($_POST['post_id']);
    $post_content = get_post_field('post_content', $post_id);
    $prompt = "Scrivi un articolo informativo basato sul contenuto seguente: " . $post_content;

    $token_count = get_option(self::OPTION_NAME_TOKEN_COUNT);

    $response = $this->esegui_richiesta_api($prompt, $token_count);

    if (is_wp_error($response)) {
        $this->registra_errore($response->get_error_message());
        wp_die($response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['choices'][0]['text'])) {
        $this->registra_errore("Le API di OpenAI non hanno restituito alcun contenuto.");
        wp_die("Le API di OpenAI non hanno restituito alcun contenuto.");
    }

    // Aggiorna il contenuto del post e reindirizza all'editor del post
    wp_update_post(array(
        'ID' => $post_id,
        'post_content' => $post_content . "\n\n" . trim($body['choices'][0]['text'])
    ));
    wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
    exit;
}


    // Eseguire una richiesta alle API
    public function esegui_richiesta_api($prompt, $token_count) {
        $api_key = get_option(self::OPTION_NAME_API_KEY);
        
        if (!$api_key) {
            return new WP_Error('openai_api_key_missing', 'Manca la chiave API di OpenAI nelle impostazioni del plugin.');
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode(array(
                'prompt' => $prompt,
                'max_tokens' => $token_count,
            )),
            'method' => 'POST',
            'data_format' => 'body',
        );

        return wp_remote_post(self::API_URL, $args);
    }

    // Registrare un errore
public function registra_errore($message) {
    $error_log = get_option('gpt_content_generator_error_log', array());
    
    if (!is_array($error_log)) {
        $error_log = array();
    }
    
    array_push($error_log, $message);
    update_option('gpt_content_generator_error_log', $error_log);
}


    // Registrare il pulsante
    public function registra_pulsante($buttons) {
        array_push($buttons, 'separator', 'gpt_content_generator');
        return $buttons;
    }

    // Registrare il plugin TinyMCE
    public function registra_plugin_tinymce($plugin_array) {
        $plugin_array['gpt_content_generator'] = plugins_url('script.js', __FILE__);
        return $plugin_array;
    }

    // Caricare gli script dell'admin
    public function carica_script_admin($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        $script_data = array(
            'iconUrl' => plugins_url('icona/gpt-icon.png', __FILE__),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gpt_content_generator_nonce'),
            'postId' => get_the_ID(),
        );

        wp_register_script('gpt-content-generator', plugins_url('script.js', __FILE__));
        wp_localize_script('gpt-content-generator', 'gptContentGenerator', $script_data);
        wp_enqueue_script('gpt-content-generator');
    }
}

new GPTContentGenerator();
