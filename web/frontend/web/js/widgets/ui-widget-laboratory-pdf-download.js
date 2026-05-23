/**
 * Botón de descarga PDF (informe de laboratorio) con auth API.
 */
(function (global) {
    'use strict';

    global.BioenlaceUiWidgets = global.BioenlaceUiWidgets || {};

    function resolveUrl(path) {
        if (typeof global.resolveSpaFetchUrl === 'function') {
            return global.resolveSpaFetchUrl(path);
        }
        if (path.indexOf('http') === 0) {
            return path;
        }
        if (global.BioenlaceApiClient && typeof global.BioenlaceApiClient.normalizeApiV1Path === 'function') {
            path = global.BioenlaceApiClient.normalizeApiV1Path(path);
        }
        if (path.indexOf('/api/') === 0) {
            return global.location.origin + path;
        }
        var base = (global.spaConfig && global.spaConfig.apiBase) ? String(global.spaConfig.apiBase) : '';
        if (base && base.slice(-1) === '/') {
            base = base.slice(0, -1);
        }
        return base + path;
    }

    function downloadBlob(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'informe-laboratorio.pdf';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 500);
    }

    global.BioenlaceUiWidgets.laboratory_pdf_download = {
        init: function (el, field) {
            var initial = field.initial_values && typeof field.initial_values === 'object' ? field.initial_values : {};
            var pdfPath = initial.pdf_url ? String(initial.pdf_url) : '';
            if (!pdfPath) {
                el.innerHTML = '<p class="text-muted small">PDF no disponible.</p>';
                return;
            }
            el.innerHTML = '';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-primary';
            btn.textContent = 'Descargar PDF';
            btn.addEventListener('click', function () {
                btn.disabled = true;
                var url = resolveUrl(pdfPath);
                var headers = global.BioenlaceApiClient && typeof global.BioenlaceApiClient.mergeHeaders === 'function'
                    ? global.BioenlaceApiClient.mergeHeaders({})
                    : {};
                fetch(url, { method: 'GET', headers: headers })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error('HTTP ' + res.status);
                        }
                        return res.blob();
                    })
                    .then(function (blob) {
                        var name = initial.filename ? String(initial.filename) : 'informe-laboratorio.pdf';
                        downloadBlob(blob, name);
                    })
                    .catch(function (err) {
                        console.error('[SPA] laboratory_pdf_download', err);
                        alert('No se pudo descargar el PDF. Intentá de nuevo.');
                    })
                    .finally(function () {
                        btn.disabled = false;
                    });
            });
            el.appendChild(btn);
        }
    };
}(typeof window !== 'undefined' ? window : this));
