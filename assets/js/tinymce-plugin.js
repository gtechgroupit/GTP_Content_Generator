(function() {
    'use strict';

    // Check if TinyMCE is available
    if (typeof tinymce === 'undefined' || typeof gcg_tinymce === 'undefined') {
        console.error('TinyMCE or GPT Content Generator configuration not found');
        return;
    }

    tinymce.create('tinymce.plugins.GCGContentGenerator', {
        init: function(editor) {
            const { ajaxurl, nonce, post_id, icon_url, strings } = gcg_tinymce;

            // Validate required configuration
            if (!ajaxurl || !nonce || !post_id) {
                console.error('GPT Content Generator: Missing required configuration');
                return;
            }

            // Add button to toolbar
            editor.addButton('gcg_generate', {
                title: strings.button_title || 'Generate Content with AI',
                image: icon_url,
                onclick: function() {
                    handleGenerateContent(editor);
                }
            });

            // Add keyboard shortcut (Ctrl/Cmd + Alt + G)
            editor.addShortcut('ctrl+alt+g', strings.button_title, function() {
                handleGenerateContent(editor);
            });

            // Add context menu item
            editor.addMenuItem('gcg_generate', {
                text: strings.button_title,
                icon: 'edit',
                context: 'tools',
                onclick: function() {
                    handleGenerateContent(editor);
                }
            });
        },

        getInfo: function() {
            return {
                longname: 'GPT Content Generator Pro',
                author: 'Gianluca Gentile',
                version: '2.0'
            };
        }
    });

    // Main content generation handler
    async function handleGenerateContent(editor) {
        try {
            // Get current content
            const content = editor.getContent({ format: 'text' });
            
            // Validate content
            if (!content || content.trim() === '') {
                showNotification(editor, gcg_tinymce.strings.error_empty, 'error');
                return;
            }

            // Show loading state
            const loadingEl = showLoading(editor);

            // Make AJAX request
            const response = await makeRequest({
                action: 'gcg_generate_content',
                post_id: gcg_tinymce.post_id,
                nonce: gcg_tinymce.nonce
            });

            // Remove loading state
            removeLoading(loadingEl);

            if (response.success) {
                // Insert generated content
                const generatedContent = response.data.content;
                
                // Create a marker for the new content
                const marker = '<div class="gcg-generated-content">' + generatedContent + '</div>';
                
                // Insert at cursor position
                editor.execCommand('mceInsertContent', false, marker);
                
                // Show success notification
                const message = response.data.from_cache 
                    ? 'Content generated (from cache)' 
                    : `Content generated (${response.data.tokens_used} tokens used)`;
                
                showNotification(editor, message, 'success');
                
                // Trigger content change event
                editor.fire('change');
                
                // Log analytics
                logAnalytics('content_generated', {
                    post_id: gcg_tinymce.post_id,
                    tokens: response.data.tokens_used,
                    cached: response.data.from_cache
                });
            } else {
                showNotification(editor, response.data || gcg_tinymce.strings.error_generic, 'error');
            }

        } catch (error) {
            console.error('GPT Content Generator Error:', error);
            showNotification(editor, error.message || gcg_tinymce.strings.error_generic, 'error');
        }
    }

    // AJAX request handler with proper error handling
    function makeRequest(data) {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                url: gcg_tinymce.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: data,
                timeout: 120000, // 2 minutes timeout
                success: function(response) {
                    resolve(response);
                },
                error: function(xhr, status, error) {
                    let errorMessage = gcg_tinymce.strings.error_generic;
                    
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    } else if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network error. Please check your connection.';
                    }
                    
                    reject(new Error(errorMessage));
                }
            });
        });
    }

    // Show loading overlay
    function showLoading(editor) {
        const editorContainer = jQuery(editor.getContainer()).closest('.wp-editor-wrap');
        
        const loadingHtml = `
            <div class="gcg-loading-overlay">
                <div class="gcg-loading-content">
                    <div class="gcg-spinner"></div>
                    <h2>${gcg_tinymce.strings.generating}</h2>
                    <p>This may take a moment...</p>
                    <button class="button gcg-cancel-btn">Cancel</button>
                </div>
            </div>
        `;
        
        const loadingEl = jQuery(loadingHtml);
        editorContainer.append(loadingEl);
        
        // Add cancel functionality
        loadingEl.find('.gcg-cancel-btn').on('click', function() {
            // Abort the request if possible
            if (window.gcgActiveRequest) {
                window.gcgActiveRequest.abort();
            }
            removeLoading(loadingEl);
            showNotification(editor, 'Generation cancelled', 'warning');
        });
        
        // Add custom CSS if not already added
        if (!jQuery('#gcg-editor-styles').length) {
            const styles = `
                <style id="gcg-editor-styles">
                    .gcg-loading-overlay {
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(255, 255, 255, 0.95);
                        z-index: 100000;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .gcg-loading-content {
                        text-align: center;
                        padding: 40px;
                        background: white;
                        border-radius: 8px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                    }
                    .gcg-spinner {
                        border: 4px solid #f3f3f3;
                        border-top: 4px solid #3498db;
                        border-radius: 50%;
                        width: 50px;
                        height: 50px;
                        animation: gcg-spin 1s linear infinite;
                        margin: 0 auto 20px;
                    }
                    @keyframes gcg-spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    .gcg-generated-content {
                        border-left: 4px solid #3498db;
                        padding-left: 15px;
                        margin: 20px 0;
                    }
                    .gcg-notification {
                        position: fixed;
                        top: 50px;
                        right: 20px;
                        padding: 12px 20px;
                        border-radius: 4px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        z-index: 100001;
                        animation: gcg-slide-in 0.3s ease-out;
                    }
                    @keyframes gcg-slide-in {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    .gcg-notification.success {
                        background: #4caf50;
                        color: white;
                    }
                    .gcg-notification.error {
                        background: #f44336;
                        color: white;
                    }
                    .gcg-notification.warning {
                        background: #ff9800;
                        color: white;
                    }
                </style>
            `;
            jQuery('head').append(styles);
        }
        
        return loadingEl;
    }

    // Remove loading overlay
    function removeLoading(loadingEl) {
        if (loadingEl && loadingEl.length) {
            loadingEl.fadeOut(200, function() {
                loadingEl.remove();
            });
        }
    }

    // Show notification
    function showNotification(editor, message, type = 'info') {
        const notification = jQuery(`
            <div class="gcg-notification ${type}">
                ${escapeHtml(message)}
            </div>
        `);
        
        jQuery('body').append(notification);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.fadeOut(300, function() {
                notification.remove();
            });
        }, 5000);
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Analytics logging (optional)
    function logAnalytics(event, data) {
        if (typeof gtag !== 'undefined') {
            gtag('event', event, {
                'event_category': 'GPT Content Generator',
                ...data
            });
        }
    }

    // Register the plugin
    tinymce.PluginManager.add('gcg_generate', tinymce.plugins.GCGContentGenerator);

})();
