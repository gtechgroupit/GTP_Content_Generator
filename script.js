(function () {
    //console.log( tinymce );
    //console.log( gptContentGenerator );

    /*if (typeof tinymce === 'undefined' || typeof gptContentGenerator === 'undefined') {
        console.error('tinymce or gptContentGenerator is not defined');
        return;
    }

    const { iconUrl, ajaxUrl, postId, nonce } = gptContentGenerator;

    if (![iconUrl, ajaxUrl, postId, nonce].every(Boolean)) {
        console.error('Required fields are missing in gptContentGenerator');
        return;
    }*/

    tinymce.create('tinymce.plugins.GPTContentGenerator', {
        init: function (ed) {
            const { iconUrl, ajaxUrl, postId, nonce } = gptContentGenerator;

            ed.addButton('gpt_content_generator', {
                title: 'Genera contenuto',
                image: iconUrl,
                onclick: async function () {
                    console.log('Button clicked');

                    jQuery('#wp-content-wrap').block({
                        message: '<h1>Sto generando il testo</h1>',
                        css: { border: '3px solid #a00' }
                    });

                    try {
                        const response = await fetch(ajaxUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'generate_content', post_id: postId, security: nonce })
                        });

                        jQuery('#wp-content-wrap').unblock();

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const data = await response.json();

                        console.log(data);

                        if (data.success) {
                            ed.execCommand('mceInsertContent', false, data.data.content);
                            //ed.setContent(data.content);
                        } else {
                            alert(data.data.error);
                            //throw new Error(data.error || 'Unknown error');
                        }
                    } catch (error) {
                        jQuery('#wp-content-wrap').unblock();
                        alert(error);
                        //console.error('Error:', error);
                    }
                }
            });
        },
        getInfo: function () {
            return {
                longname: 'Generatore di Contenuti GPT',
                author: 'Gianluca Gentile',
                version: '1'
            };
        }
    });

    tinymce.PluginManager.add('gpt_content_generator', tinymce.plugins.GPTContentGenerator);

    // Chat con GPT
    //jQuery(document).ready(function($) {
    /*
    var chatInput = $('#gpt-chat-input'); // sostituisci con il tuo selettore di input della chat
    var chatDisplay = $('#gpt-chat-display'); // sostituisci con il tuo selettore di display della chat

    chatInput.keydown(function(e) {
        if (e.keyCode == 13) {
            e.preventDefault();
            var message = $(this).val();
            $.ajax({
                url: gptContentGenerator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chat_with_gpt',
                    security: gptContentGenerator.nonce,
                    message: message,
                },
                success: function(response) {
                    if (response.success) {
                        chatDisplay.append('<div>Tu: ' + message + '</div>'); // aggiungi il tuo messaggio al display
                        chatDisplay.append('<div>GPT: ' + response.data + '</div>'); // aggiungi la risposta di GPT al display
                        chatInput.val(''); // cancella l'input
                    } else {
                        alert('Si è verificato un errore: ' + response.data);
                    }
                }
            });
        }
    });*/
    //   });

})();
