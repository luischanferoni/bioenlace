/**
 * Solo lectura de consulta documentada (staff).
 * Config: #ver-consulta-root[data-api][data-url-inicio]
 */
(function () {
    'use strict';

    var root = document.getElementById('ver-consulta-root');
    if (!root) return;
    var api = root.getAttribute('data-api') || '';
    var urlInicio = root.getAttribute('data-url-inicio') || '/';
    var loading = document.getElementById('vc-loading');
    var errEl = document.getElementById('vc-error');
    var content = document.getElementById('vc-content');

    function bioHeaders() {
        if (typeof window.getBioenlaceApiClientHeaders === 'function') {
            return window.getBioenlaceApiClientHeaders();
        }
        return {};
    }
    function tpl(id) {
        var t = document.getElementById(id);
        if (!t || !t.content) return null;
        return document.importNode(t.content, true);
    }
    function clear(el) {
        if (el) el.replaceChildren();
    }
    function showError(msg) {
        if (loading) loading.classList.add('d-none');
        if (content) content.classList.add('d-none');
        if (errEl) {
            errEl.textContent = msg || 'No se pudo cargar la consulta.';
            errEl.classList.remove('d-none');
        }
    }
    var btnVolver = document.getElementById('vc-btn-volver');
    if (btnVolver) {
        btnVolver.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
                return;
            }
            window.location.href = urlInicio;
        });
    }
    if (!api) {
        showError('Falta turno_id o encounter_id para ver la consulta.');
        return;
    }
    fetch(api, { headers: bioHeaders(), credentials: 'same-origin' })
        .then(function (r) {
            return r.json().then(function (body) {
                return { ok: r.ok, body: body };
            });
        })
        .then(function (res) {
            if (!res.ok || !res.body || res.body.success !== true || !res.body.data) {
                throw new Error((res.body && res.body.message) ? res.body.message : 'Error al cargar la consulta');
            }
            var d = res.body.data;
            var p = d.persona || {};
            var t = d.turno || {};
            var nombreEl = document.getElementById('vc-persona-nombre');
            if (nombreEl) nombreEl.textContent = p.nombre_completo || 'Paciente';
            var turnoEl = document.getElementById('vc-turno-meta');
            if (turnoEl) {
                var tb = [];
                if (t.fecha) tb.push(t.fecha);
                if (t.hora) tb.push(t.hora);
                if (t.estado_label || t.estado) tb.push(t.estado_label || t.estado);
                turnoEl.textContent = tb.length ? ('Turno: ' + tb.join(' · ')) : '';
            }
            var mp = d.motivos_consulta_paciente || {};
            var resumen = (mp.resumen || mp.resumen_ia || '').toString().trim();
            var motivosWrap = document.getElementById('vc-motivos-wrap');
            var motivosEl = document.getElementById('vc-motivos');
            if (resumen && motivosWrap && motivosEl) {
                motivosEl.textContent = resumen;
                motivosWrap.style.display = '';
            }
            var care = d.care_pack_cohorte;
            var careWrap = document.getElementById('vc-care-pack-wrap');
            var careEl = document.getElementById('vc-care-pack');
            if (care && care.assistance && careWrap && careEl) {
                var a = care.assistance;
                clear(careEl);
                var hasCare = false;
                (a.answers || []).forEach(function (ans) {
                    var frag = tpl('tpl-vc-care-answer');
                    if (!frag) return;
                    var q = frag.querySelector('[data-field="question"]');
                    if (q) q.textContent = ans.question || '';
                    var an = frag.querySelector('[data-field="answer"]');
                    if (an) an.textContent = ans.answer || '';
                    careEl.appendChild(frag);
                    hasCare = true;
                });
                if (a.notes_for_staff) {
                    var notes = tpl('tpl-vc-care-notes');
                    if (notes) {
                        var n = notes.querySelector('[data-field="notes"]');
                        if (n) n.textContent = a.notes_for_staff;
                        careEl.appendChild(notes);
                        hasCare = true;
                    }
                }
                if (hasCare) careWrap.style.display = '';
            }
            var doc = d.documentacion_medico || {};
            var docWrap = document.getElementById('vc-doc-wrap');
            var docEl = document.getElementById('vc-doc');
            var emptyEl = document.getElementById('vc-empty');
            var hasDoc = !!(doc.tiene_datos && doc.secciones && doc.secciones.length);
            if (hasDoc && docWrap && docEl) {
                clear(docEl);
                doc.secciones.forEach(function (sec) {
                    var secFrag = tpl('tpl-vc-doc-section');
                    if (!secFrag) return;
                    var tit = secFrag.querySelector('[data-field="titulo"]');
                    if (tit) tit.textContent = sec.titulo || '';
                    var items = secFrag.querySelector('[data-slot="items"]');
                    (sec.items || []).forEach(function (item) {
                        var li = tpl('tpl-vc-doc-item');
                        if (!li) return;
                        var tx = li.querySelector('[data-field="text"]');
                        if (tx) tx.textContent = item;
                        if (items) items.appendChild(li);
                    });
                    docEl.appendChild(secFrag);
                });
                docWrap.style.display = '';
            } else if (emptyEl && !resumen) {
                emptyEl.style.display = '';
            }
            if (loading) loading.classList.add('d-none');
            if (content) content.classList.remove('d-none');
        })
        .catch(function (e) {
            showError(e && e.message ? e.message : 'No se pudo cargar la consulta.');
        });
})();
