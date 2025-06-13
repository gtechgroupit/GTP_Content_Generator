/**
 * GPT Content Generator Pro - Admin JavaScript
 * 
 * @package GPT_Content_Generator_Pro
 */

(function($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {
        
        // Initialize components
        initializeApiTesting();
        initializeCacheManagement();
        initializeRangeInputs();
        initializeTooltips();
        initializePromptPreview();
        
        // Handle settings form submission
        $('#gcg-settings-form').on('submit', function(e) {
            // Add loading state
            const $submitButton = $(this).find('input[type="submit"]');
            const originalText = $submitButton.val();
            $submitButton.val('Saving...').prop('disabled', true);
            
            // Form will submit normally, just adding UX enhancement
            setTimeout(() => {
                $submitButton.val(originalText).prop('disabled', false);
            }, 1000);
        });
    });

    /**
     * Initialize API Testing
     */
    function initializeApiTesting() {
        $('.gcg-test-api').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // Update button state
            $button.text('Testing...').prop('disabled', true);
            
            // Make AJAX request
            $.ajax({
                url: gcg.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gcg_test_api',
                    nonce: gcg.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(gcg.strings.api_test_success, 'success');
                        
                        // Show the response
                        const $result = $('<div class="gcg-api-test-result notice notice-success"><p><strong>Response:</strong> ' + escapeHtml(response.data) + '</p></div>');
                        $button.after($result);
                        
                        setTimeout(() => {
                            $result.fadeOut(() => $result.remove());
                        }, 5000);
                    } else {
                        showNotification(gcg.strings.api_test_error + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showNotification(gcg.strings.api_test_error + error, 'error');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
    }

    /**
     * Initialize Cache Management
     */
    function initializeCacheManagement() {
        $('.gcg-clear-cache').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(gcg.strings.confirm_clear_cache)) {
                return;
            }
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.text('Clearing...').prop('disabled', true);
            
            $.ajax({
                url: gcg.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gcg_clear_cache',
                    nonce: gcg.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data, 'success');
                    } else {
                        showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('Failed to clear cache', 'error');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
    }

    /**
     * Initialize Range Input Display
     */
    function initializeRangeInputs() {
        $('input[type="number"][step="0.1"]').on('input', function() {
            const $input = $(this);
            const $display = $input.siblings('.gcg-range-value');
            if ($display.length) {
                $display.text($input.val());
            }
        });
    }

    /**
     * Initialize Tooltips
     */
    function initializeTooltips() {
        // Add tooltips to description text
        $('.description').each(function() {
            const $this = $(this);
            $this.attr('title', $this.text());
        });
        
        // Initialize any tooltip library if available
        if (typeof $.fn.tooltip === 'function') {
            $('.description').tooltip();
        }
    }

    /**
     * Initialize Prompt Preview
     */
    function initializePromptPreview() {
        const $promptTemplate = $('#' + 'gcg_prompt_template');
        
        if ($promptTemplate.length) {
            // Add preview button
            const $previewButton = $('<button type="button" class="button gcg-preview-prompt">Preview with Sample Content</button>');
            $promptTemplate.after($previewButton);
            
            $previewButton.on('click', function(e) {
                e.preventDefault();
                
                const template = $promptTemplate.val();
                const sampleContent = 'This is sample content from your post. It demonstrates how your prompt template will look with actual content.';
                const preview = template.replace('{content}', sampleContent);
                
                // Show preview in modal
                showPromptPreview(preview);
            });
        }
    }

    /**
     * Show Prompt Preview Modal
     */
    function showPromptPreview(prompt) {
        const modalHtml = `
            <div class="gcg-modal-overlay">
                <div class="gcg-modal">
                    <div class="gcg-modal-header">
                        <h3>Prompt Preview</h3>
                        <button class="gcg-modal-close">&times;</button>
                    </div>
                    <div class="gcg-modal-content">
                        <div class="gcg-prompt-preview">${escapeHtml(prompt)}</div>
                    </div>
                    <div class="gcg-modal-footer">
                        <button class="button gcg-modal-close">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        const $modal = $(modalHtml);
        $('body').append($modal);
        
        // Close handlers
        $modal.find('.gcg-modal-close, .gcg-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                $modal.fadeOut(() => $modal.remove());
            }
        });
        
        // Show modal
        $modal.fadeIn();
    }

    /**
     * Show Notification
     */
    function showNotification(message, type = 'info') {
        const typeClass = type === 'error' ? 'notice-error' : type === 'success' ? 'notice-success' : 'notice-info';
        
        const $notification = $(`
            <div class="notice ${typeClass} is-dismissible gcg-notification">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        // Add to page
        $('.wrap h1').after($notification);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $notification.fadeOut(() => $notification.remove());
        }, 5000);
        
        // Handle dismiss button
        $notification.find('.notice-dismiss').on('click', function() {
            $notification.fadeOut(() => $notification.remove());
        });
    }

    /**
     * Escape HTML
     */
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

    /**
     * Handle dynamic form elements
     */
    $(document).on('change', 'input[name="gcg_use_custom_prompt"]', function() {
        const $textarea = $('#gcg_custom_prompt');
        if ($(this).is(':checked')) {
            $textarea.prop('disabled', false).focus();
        } else {
            $textarea.prop('disabled', true);
        }
    });

    /**
     * Usage stats auto-refresh (for logs page)
     */
    if ($('.gcg-usage-stats').length) {
        // Refresh stats every 30 seconds
        setInterval(function() {
            $.ajax({
                url: gcg.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gcg_get_stats',
                    nonce: gcg.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateStats(response.data);
                    }
                }
            });
        }, 30000);
    }

    /**
     * Update usage statistics
     */
    function updateStats(data) {
        Object.keys(data).forEach(key => {
            const $stat = $(`.gcg-stat-${key}`);
            if ($stat.length) {
                const oldValue = parseInt($stat.text());
                const newValue = parseInt(data[key]);
                
                if (oldValue !== newValue) {
                    $stat.fadeOut(200, function() {
                        $(this).text(newValue).fadeIn(200);
                    });
                }
            }
        });
    }

    /**
     * Export functionality for logs
     */
    $('.gcg-export-logs').on('click', function(e) {
        e.preventDefault();
        
        const format = $(this).data('format') || 'csv';
        const url = gcg.ajaxurl + '?action=gcg_export_logs&format=' + format + '&nonce=' + gcg.nonce;
        
        window.location.href = url;
    });

})(jQuery);

// Add CSS for modal and other UI elements
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .gcg-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100000;
            display: none;
        }
        
        .gcg-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
        }
        
        .gcg-modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .gcg-modal-header h3 {
            margin: 0;
        }
        
        .gcg-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .gcg-modal-close:hover {
            color: #000;
        }
        
        .gcg-modal-content {
            padding: 20px;
            overflow-y: auto;
            max-height: 60vh;
        }
        
        .gcg-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            text-align: right;
        }
        
        .gcg-prompt-preview {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .gcg-api-test-result {
            margin-top: 10px;
        }
        
        .gcg-notification {
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .gcg-preview-prompt {
            margin-top: 5px;
        }
        
        .gcg-usage-stats {
            transition: all 0.3s ease;
        }
        
        input[type="number"][step="0.1"] + .gcg-range-value {
            display: inline-block;
            min-width: 30px;
            text-align: center;
            font-weight: 600;
            color: #0073aa;
        }
    `;
    document.head.appendChild(style);
})();
