(function() {
    tinymce.create('tinymce.plugins.GPTContentGenerator', {
        init: function(ed, url) {
            ed.addButton('gpt_content_generator', {
                title: 'Genera contenuto',
                image: gptContentGenerator.iconUrl,
                onclick: function() {
                    let form = document.createElement('form');
                    form.method = 'post';
                    form.action = gptContentGenerator.ajaxUrl;
                    
                    let actionField = document.createElement('input');
                    actionField.type = 'hidden';
                    actionField.name = 'action';
                    actionField.value = 'generate_content';
                    form.appendChild(actionField);
                    
                    let postIdField = document.createElement('input');
                    postIdField.type = 'hidden';
                    postIdField.name = 'post_id';
                    postIdField.value = gptContentGenerator.postId;
                    form.appendChild(postIdField);
                    
                    let securityField = document.createElement('input');
                    securityField.type = 'hidden';
                    securityField.name = 'security';
                    securityField.value = gptContentGenerator.nonce;
                    form.appendChild(securityField);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        },
        createControl: function(n, cm) {
            return null;
        },
        getInfo: function() {
            return {
                longname: 'Generatore di Contenuti GPT',
                author: 'Gianluca Gentile',
                version: '1'
            };
        }
    });

    tinymce.PluginManager.add('gpt_content_generator', tinymce.plugins.GPTContentGenerator);
})();
