<?php
/**
 * Admin Settings Page Template
 * 
 * @package GPT_Content_Generator_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_key = get_option( GPTContentGeneratorPro::OPTION_API_KEY );
$has_api_key = ! empty( $api_key );
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="gcg-admin-container">
        <div class="gcg-main-content">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=gpt-content-generator' ) ); ?>">
                <?php wp_nonce_field( 'gcg_settings' ); ?>
                
                <!-- API Configuration -->
                <div class="gcg-card">
                    <h2><?php _e( 'API Configuration', 'gpt-content-generator-pro' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="<?php echo GPTContentGeneratorPro::OPTION_API_KEY; ?>">
                                    <?php _e( 'OpenAI API Key', 'gpt-content-generator-pro' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="<?php echo GPTContentGeneratorPro::OPTION_API_KEY; ?>" 
                                       name="<?php echo GPTContentGeneratorPro::OPTION_API_KEY; ?>" 
                                       value="<?php echo $has_api_key ? 'sk-**********************' : ''; ?>" 
                                       class="regular-text" 
                                       placeholder="sk-...">
                                <p class="description">
                                    <?php _e( 'Enter your OpenAI API key. Get one from', 'gpt-content-generator-pro' ); ?> 
                                    <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                                </p>
                                <?php if ( $has_api_key ) : ?>
                                    <button type="button" class="button gcg-test-api" style="margin-top: 10px;">
                                        <?php _e( 'Test API Connection', 'gpt-content-generator-pro' ); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="<?php echo GPTContentGeneratorPro::OPTION_MODEL; ?>">
                                    <?php _e( 'AI Model', 'gpt-content-generator-pro' ); ?>
                                </label>
                            </th>
                            <td>
                                <select id="<?php echo GPTContentGeneratorPro::OPTION_MODEL; ?>" 
                                        name="<?php echo GPTContentGeneratorPro::OPTION_MODEL; ?>">
                                    <?php
                                    $current_model = get_option( GPTContentGeneratorPro::OPTION_MODEL, 'gpt-3.5-turbo' );
                                    foreach ( GPTContentGeneratorPro::AVAILABLE_MODELS as $model => $label ) :
                                    ?>
                                        <option value="<?php echo esc_attr( $model ); ?>" <?php selected( $current_model, $model ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e( 'Select the OpenAI model to use for content generation.', 'gpt-content-generator-pro' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Generation Settings -->
                <div class="gcg-card">
                    <h2><?php _e( 'Generation Settings', 'gpt-content-generator-pro' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="<?php echo GPTContentGeneratorPro::OPTION_TOKEN_COUNT; ?>">
                                    <?php _e( 'Max Tokens', 'gpt-content-generator-pro' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="<?php echo GPTContentGeneratorPro::OPTION_TOKEN_COUNT; ?>" 
                                       name="<?php echo GPTContentGeneratorPro::OPTION_TOKEN_COUNT; ?>" 
                                       value="<?php echo esc_attr( get_option( GPTContentGeneratorPro::OPTION_TOKEN_COUNT, 500 ) ); ?>" 
                                       min="50" 
                                       max="4000" 
                                       step="50" 
                                       class="small-text">
                                <p class="description">
                                    <?php _e( 'Maximum number of tokens to generate (50-4000).', 'gpt-content-generator-pro' ); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="<?php echo GPTContentGeneratorPro::OPTION_PROMPT_TEMPLATE; ?>">
                                    <?php _e( 'Default Prompt Template', 'gpt-content-generator-pro' ); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="<?php echo GPTContentGeneratorPro::OPTION_PROMPT_TEMPLATE; ?>" 
                                          name="<?php echo GPTContentGeneratorPro::OPTION_PROMPT_TEMPLATE; ?>" 
                                          rows="4" 
                                          cols="50" 
                                          class="large-text"><?php echo esc_textarea( get_option( GPTContentGeneratorPro::OPTION_PROMPT_TEMPLATE ) ); ?></textarea>
                                <p class="description">
                                    <?php _e( 'Use {content} as a placeholder for the post content.', 'gpt-content-generator-pro' ); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="<?php echo GPTContentGeneratorPro::OPTION_TEMPERATURE; ?>">
                                    <?php _e( 'Temperature', 'gpt-content-generator-pro' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="<?php echo GPTContentGeneratorPro::OPTION_TEMPERATURE; ?>" 
                                       name="<?php echo GPTContentGeneratorPro::OPTION_TEMPERATURE; ?>" 
                                       value="<?php echo esc_attr( get_option( GPTContentGeneratorPro::OPTION_TEMPERATURE, 0.7 ) ); ?>" 
                                       min="0" 
                                       max="2" 
                                       step="0.1" 
                                       class="small-text">
                                <span class="gcg-range-value">
                                    <?php echo esc_html( get_option( GPTContentGeneratorPro::OPTION_TEMPERATURE, 0.7 ) ); ?>
                                </span>
                                <p class="description">
                                    <?php _e( 'Controls randomness: 0 = focused, 2 = very random.', 'gpt-content-generator-pro' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Access Control -->
                <div class="gcg-card">
                    <h2><?php _e( 'Access Control', 'gpt-content-generator-pro' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php _e( 'Allowed Post Types', 'gpt-content-generator-pro' ); ?>
                            </th>
                            <td>
                                <?php
                                $post_types = get_post_types( ['public' => true], 'objects' );
                                $allowed_post_types = get_option( GPTContentGeneratorPro::OPTION_ALLOWED_POST_TYPES, ['post', 'page'] );
                                
                                foreach ( $post_types as $post_type ) :
                                ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" 
                                               name="<?php echo GPTContentGeneratorPro::OPTION_ALLOWED_POST_TYPES; ?>[]" 
                                               value="<?php echo esc_attr( $post_type->name ); ?>" 
                                               <?php checked( in_array( $post_type->name, $allowed_post_types ) ); ?>>
                                        <?php echo esc_html( $post_type->label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php _e( 'Allowed User Roles', 'gpt-content-generator-pro' ); ?>
                            </th>
                            <td>
                                <?php
                                $roles = wp_roles()->roles;
                                $allowed_roles = get_option( GPTContentGeneratorPro::OPTION_ALLOWED_ROLES, ['administrator', 'editor'] );
                                
                                foreach ( $roles as $role_key => $role ) :
                                ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" 
                                               name="<?php echo GPTContentGeneratorPro::OPTION_ALLOWED_ROLES; ?>[]" 
                                               value="<?php echo esc_attr( $role_key ); ?>" 
                                               <?php checked( in_array( $role_key, $allowed_roles ) ); ?>>
                                        <?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Performance Settings -->
                <div class="gcg-card">
                    <h2><?php _e( 'Performance & Limits', 'gpt-content-generator-pro' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="<?php echo GPTContentGeneratorPro::OPTION_RATE_LIMIT; ?>">
                                    <?php _e( 'Rate Limit', 'gpt-content-generator-pro' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="<?php echo GPTContentGeneratorPro::OPTION_RATE_LIMIT; ?>" 
                                       name="<?php echo GPTContentGeneratorPro::OPTION_RATE_LIMIT; ?>" 
                                       value="<?php echo esc_attr( get_option( GPTContentGeneratorPro::OPTION_RATE_LIMIT, 10 ) ); ?>" 
                                       min="0" 
                                       max="100" 
                                       class="small-text">
                                <span><?php _e( 'requests per hour (0 = unlimited)', 'gpt-content-generator-pro' ); ?></span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="<?php echo GPTContentGeneratorPro::OPTION_CACHE_DURATION; ?>">
                                    <?php _e( 'Cache Duration', 'gpt-content-generator-pro' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="<?php echo GPTContentGeneratorPro::OPTION_CACHE_DURATION; ?>" 
                                       name="<?php echo GPTContentGeneratorPro::OPTION_CACHE_DURATION; ?>" 
                                       value="<?php echo esc_attr( get_option( GPTContentGeneratorPro::OPTION_CACHE_DURATION, 3600 ) ); ?>" 
                                       min="0" 
                                       max="86400" 
                                       step="300" 
                                       class="small-text">
                                <span><?php _e( 'seconds (0 = no cache)', 'gpt-content-generator-pro' ); ?></span>
                                <p class="description">
                                    <?php _e( 'Cache identical prompts to save API calls and improve performance.', 'gpt-content-generator-pro' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button( __( 'Save Settings', 'gpt-content-generator-pro' ) ); ?>
            </form>
        </div>
        
        <!-- Sidebar -->
        <div class="gcg-sidebar">
            <!-- Quick Stats -->
            <div class="gcg-card">
                <h3><?php _e( 'Quick Stats', 'gpt-content-generator-pro' ); ?></h3>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'gcg_logs';
                $today = date( 'Y-m-d' );
                $stats = $wpdb->get_row( $wpdb->prepare( 
                    "SELECT 
                        COUNT(*) as total_requests,
                        SUM(tokens_used) as total_tokens,
                        COUNT(DISTINCT user_id) as unique_users
                     FROM {$table_name}
                     WHERE DATE(created_at) = %s
                     AND status = 'success'",
                    $today
                ) );
                ?>
                <ul class="gcg-stats">
                    <li>
                        <strong><?php _e( 'Today\'s Requests:', 'gpt-content-generator-pro' ); ?></strong> 
                        <?php echo intval( $stats->total_requests ); ?>
                    </li>
                    <li>
                        <strong><?php _e( 'Tokens Used:', 'gpt-content-generator-pro' ); ?></strong> 
                        <?php echo intval( $stats->total_tokens ); ?>
                    </li>
                    <li>
                        <strong><?php _e( 'Active Users:', 'gpt-content-generator-pro' ); ?></strong> 
                        <?php echo intval( $stats->unique_users ); ?>
                    </li>
                </ul>
                
                <button type="button" class="button gcg-clear-cache" style="margin-top: 15px; width: 100%;">
                    <?php _e( 'Clear Cache', 'gpt-content-generator-pro' ); ?>
                </button>
            </div>
            
            <!-- Help -->
            <div class="gcg-card">
                <h3><?php _e( 'Need Help?', 'gpt-content-generator-pro' ); ?></h3>
                <p><?php _e( 'Check out our documentation and tutorials:', 'gpt-content-generator-pro' ); ?></p>
                <ul>
                    <li><a href="https://gtechgroup.it/docs/gpt-content-generator" target="_blank"><?php _e( 'Documentation', 'gpt-content-generator-pro' ); ?></a></li>
                    <li><a href="https://gtechgroup.it/support" target="_blank"><?php _e( 'Support Forum', 'gpt-content-generator-pro' ); ?></a></li>
                    <li><a href="https://platform.openai.com/docs" target="_blank"><?php _e( 'OpenAI API Docs', 'gpt-content-generator-pro' ); ?></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.gcg-admin-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.gcg-main-content {
    flex: 1;
}

.gcg-sidebar {
    width: 300px;
}

.gcg-card {
    background: white;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}

.gcg-card h2, .gcg-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #23282d;
}

.gcg-stats {
    list-style: none;
    padding: 0;
    margin: 0;
}

.gcg-stats li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.gcg-stats li:last-child {
    border-bottom: none;
}

@media screen and (max-width: 782px) {
    .gcg-admin-container {
        flex-direction: column;
    }
    
    .gcg-sidebar {
        width: 100%;
    }
}
</style>
