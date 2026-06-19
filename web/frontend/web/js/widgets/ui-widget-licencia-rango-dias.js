/**
 * Widget UI JSON: leyenda de duración (días inclusive) entre fecha_inicio y fecha_fin.
 */
(function (global) {
    'use strict';

    global.BioenlaceUiWidgets = global.BioenlaceUiWidgets || {};

    function findForm(root) {
        var el = root;
        while (el && el !== document.body) {
            if (el.querySelector && el.querySelector('form[data-ui-json-form="1"]')) {
                return el.querySelector('form[data-ui-json-form="1"]');
            }
            el = el.parentElement;
        }
        return document.querySelector('form[data-ui-json-form="1"]');
    }

    function parseYmd(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || '').trim());
        if (!m) {
            return null;
        }
        return {
            y: parseInt(m[1], 10),
            mo: parseInt(m[2], 10),
            d: parseInt(m[3], 10)
        };
    }

    function countInclusiveCalendarDays(fi, ff) {
        var a = parseYmd(fi);
        var b = parseYmd(ff);
        if (!a || !b) {
            return null;
        }
        var t1 = Date.UTC(a.y, a.mo - 1, a.d);
        var t2 = Date.UTC(b.y, b.mo - 1, b.d);
        var diff = Math.round((t2 - t1) / 86400000);
        if (diff <= 0) {
            return null;
        }
        return diff + 1;
    }

    function leyendaFromDates(fi, ff) {
        var n = countInclusiveCalendarDays(fi, ff);
        if (n === null || n < 1) {
            return '';
        }
        return n === 1 ? '1 día' : n + ' días';
    }

    function hintFormulario(fi, ff) {
        var leyenda = leyendaFromDates(fi, ff);
        if (leyenda) {
            return 'Duración: ' + leyenda;
        }
        fi = String(fi || '').trim();
        ff = String(ff || '').trim();
        if (fi && !ff) {
            return 'Indicá la fecha de fin para ver la duración.';
        }
        if (!fi && ff) {
            return 'Indicá la fecha de inicio para ver la duración.';
        }
        return 'Seleccioná fecha de inicio y fin para ver la duración.';
    }

    function fieldValue(form, name) {
        if (!form || !name) {
            return '';
        }
        var sel = String(name).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        var input = form.querySelector('[name="' + sel + '"]');
        return input ? String(input.value || '').trim() : '';
    }

    function watchFields(fieldDef) {
        var wf = fieldDef && fieldDef.watch_fields;
        if (Array.isArray(wf) && wf.length >= 2) {
            return [String(wf[0]), String(wf[1])];
        }
        return ['fecha_inicio', 'fecha_fin'];
    }

    global.BioenlaceUiWidgets.licencia_rango_dias = {
        init: function (root, fieldDef) {
            var mount = root.querySelector('[data-weekly-scheduler-mount]');
            if (mount && mount.parentNode) {
                mount.parentNode.removeChild(mount);
            }
            root.insertAdjacentHTML(
                'beforeend',
                '<p class="text-muted small mb-0 spa-licencia-rango-dias-hint" data-licencia-rango-dias-hint="1" role="status"></p>'
            );
            var hintEl = root.querySelector('[data-licencia-rango-dias-hint="1"]');
            if (!hintEl) {
                return;
            }
            var names = watchFields(fieldDef);
            var form = findForm(root);

            function refresh() {
                var fi = fieldValue(form, names[0]);
                var ff = fieldValue(form, names[1]);
                hintEl.textContent = hintFormulario(fi, ff);
            }

            refresh();
            if (form) {
                form.addEventListener('change', refresh);
                form.addEventListener('input', refresh);
            }
        }
    };
})(typeof window !== 'undefined' ? window : this);
