/*
 * Ajax wrapper centralizado
 * Reemplaza llamadas a $.post / $.ajax POST para siempre enviar
 * los valores de `window.userPerTabConfig` junto con los datos.
 */
(function(window, document){
    'use strict';

    // Helper para combinar datos (obj o FormData)
    function mergeData(originalData) {
        var perTab = window.userPerTabConfig || {};

        // Si es FormData, añadimos los keys
        if (originalData instanceof FormData) {
            Object.keys(perTab).forEach(function(k){
                try {
                    originalData.append('userPerTabConfig['+k+']', JSON.stringify(perTab[k]));
                } catch(e) {
                    // ignore
                }
            });
            return originalData;
        }

        // Si es objeto literal o cadena
        var dataObj = {};
        if (!originalData) {
            dataObj = {};
        } else if (typeof originalData === 'string') {
            try {
                // intentar parsear querystring
                originalData.split('&').forEach(function(pair){
                    if (!pair) return;
                    var parts = pair.split('=');
                    dataObj[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1] || '');
                });
            } catch(e){
                dataObj = { payload: originalData };
            }
        } else if (typeof originalData === 'object') {
            dataObj = Object.assign({}, originalData);
        }

        dataObj.userPerTabConfig = perTab;
        return dataObj;
    }

    // Interceptar $.post
    if (window.jQuery) {
        (function($){
            var _oldPost = $.post;
            $.post = function(url, data, success, dataType) {
                var merged = mergeData(data);
                // Si merged es objeto, convertir a querystring si no es JSON
                if (!(merged instanceof FormData) && typeof merged === 'object') {
                    // convertir a objeto simple; jQuery hará la serialización
                }
                return _oldPost.call(this, url, merged, success, dataType);
            };

            var _oldAjax = $.ajax;
            $.ajax = function(options) {
                // support signature $.ajax(url, settings)
                if (typeof options === 'string') {
                    options = { url: options };
                }

                var method = (options.method || options.type || 'GET').toUpperCase();
                if (method === 'POST') {
                    options.data = mergeData(options.data);
                    // if contentType is application/json and data is object, stringify
                    var contentType = options.contentType;
                    if (!contentType && options.headers && options.headers['Content-Type']) {
                        contentType = options.headers['Content-Type'];
                    }
                    if (contentType && contentType.indexOf('application/json') !== -1 && !(options.data instanceof FormData)) {
                        try {
                            options.data = JSON.stringify(options.data);
                        } catch(e){}
                    }
                }
                return _oldAjax.call(this, options);
            };
        })(window.jQuery);
    }

    // Exponer helper para fetch/fetch wrapper
    function fetchPost(url, data, fetchOptions) {
        var merged = mergeData(data);
        var headers = (fetchOptions && fetchOptions.headers) || {};
        headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        var body;
        if (merged instanceof FormData) {
            body = merged;
        } else if (headers['Content-Type'] && headers['Content-Type'].indexOf('application/json') !== -1) {
            body = JSON.stringify(merged);
        } else {
            // default to JSON
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(merged);
        }

        return window.fetch(url, Object.assign({
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: body
        }, fetchOptions || {}));
    }

    window.VitaMindAjax = window.VitaMindAjax || {};
    window.VitaMindAjax.fetchPost = fetchPost;
    window.VitaMindAjax.mergeData = mergeData;

})(window, document);

// --- Inyección para formularios HTML/ActiveForm ---
(function(window, document){
    'use strict';

    function injectPerTabIntoForm(form) {
        if (!form || form.__vitamind_pertab_injected) return;
        var perTab = window.userPerTabConfig || {};
        try {
            var existing = form.querySelector('input[name="userPerTabConfig"]');
            if (!existing) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'userPerTabConfig';
                input.value = JSON.stringify(perTab);
                form.appendChild(input);
            } else {
                existing.value = JSON.stringify(perTab);
            }
        } catch(e) {
            // noop
        }
        // marcar para no duplicar en submit programáticos
        form.__vitamind_pertab_injected = true;
    }

    // Interceptar submit nativo
    document.addEventListener('submit', function(e){
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        try {
            if (form.method && form.method.toUpperCase() === 'POST') {
                injectPerTabIntoForm(form);
            }
        } catch(err) {}
    }, true);

    // Soporte para Yii2 ActiveForm: beforeSubmit
    if (window.jQuery) {
        (function($){
            $(document).on('beforeSubmit', 'form', function(e){
                try {
                    var form = this;
                    if (form.method && form.method.toUpperCase() === 'POST') {
                        injectPerTabIntoForm(form);
                    }
                } catch(err){}
                // permitir que continue el submit
                return true;
            });
        })(window.jQuery);
    }

    // Interceptar llamadas programáticas a form.submit()
    if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype) {
        var _oldFormSubmit = HTMLFormElement.prototype.submit;
        HTMLFormElement.prototype.submit = function(){
            try { injectPerTabIntoForm(this); } catch(e){}
            return _oldFormSubmit.apply(this, arguments);
        };
    }

})(window, document);



