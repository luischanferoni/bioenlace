/**
 * Descarga PDF de receta electrónica (misma lógica que laboratorio).
 */
(function (global) {
    'use strict';

    global.BioenlaceUiWidgets = global.BioenlaceUiWidgets || {};

    global.BioenlaceUiWidgets.prescription_pdf_download = {
        init: function (el, field) {
            var initial = field.initial_values && typeof field.initial_values === 'object' ? field.initial_values : {};
            var pdfPath = initial.pdf_url ? String(initial.pdf_url) : '';
            if (!pdfPath) {
                var unavailable = document.createElement('p');
                unavailable.className = 'text-muted small';
                unavailable.textContent = global.BioenlaceApiClient.pdfDownloadUnavailableMessage();
                el.replaceChildren(unavailable);
                return;
            }
            el.replaceChildren();
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-primary';
            btn.textContent = global.BioenlaceApiClient.pdfDownloadButtonLabel();
            btn.addEventListener('click', function () {
                btn.disabled = true;
                var client = global.BioenlaceApiClient;
                var name = initial.filename ? String(initial.filename) : 'receta.pdf';
                Promise.resolve()
                    .then(function () {
                        var url = client.resolveApiUrl(pdfPath);
                        return client.downloadPdf(url, name);
                    })
                    .catch(function (err) {
                        console.error('[SPA] prescription_pdf_download', err);
                        alert(client.pdfDownloadErrorMessage());
                    })
                    .finally(function () {
                        btn.disabled = false;
                    });
            });
            el.appendChild(btn);
        }
    };
}(typeof window !== 'undefined' ? window : this));
