/**
 * Widget UI JSON: grilla semanal (jQuery scheduler) ↔ campos lunes_2..domingo_2 (CSV de índices de slot).
 * Requiere: jQuery y scheduler.js cargados antes (declarar en assets del campo custom_widget).
 */
(function (global) {
    'use strict';

    global.BioenlaceUiWidgets = global.BioenlaceUiWidgets || {};

    function parseSlotsCsv(str, accuracy) {
        var max = 24 * accuracy;
        if (!str || !String(str).trim()) {
            return [];
        }
        return String(str)
            .split(',')
            .map(function (s) {
                return s.trim();
            })
            .filter(Boolean)
            .map(function (token) {
                if (/^\d+$/.test(token)) {
                    var n = parseInt(token, 10);
                    return n >= 0 && n < max ? n : null;
                }
                var m = token.match(/^(\d{1,2}):(\d{2})$/);
                if (m) {
                    var h = parseInt(m[1], 10);
                    var min = parseInt(m[2], 10);
                    if (!isFinite(h) || !isFinite(min)) {
                        return null;
                    }
                    if (accuracy === 1) {
                        return h >= 0 && h < 24 ? h : null;
                    }
                    var slot = h * accuracy + Math.floor(min / (60 / accuracy));
                    return slot >= 0 && slot < max ? slot : null;
                }
                return null;
            })
            .filter(function (x) {
                return x !== null;
            });
    }

    function syncHiddenFromVal(root, fieldNames, valores) {
        for (var i = 1; i <= 7; i++) {
            var arr = valores[i] || [];
            var str = arr.join(',');
            var name = fieldNames[i - 1];
            if (!name || !root.querySelector) {
                continue;
            }
            var inp = root.querySelector('input[name="' + name + '"]');
            if (inp) {
                inp.value = str;
            }
        }
    }

    global.BioenlaceUiWidgets.weekly_scheduler = {
        /**
         * @param {HTMLElement} root .bio-ui-custom-widget
         * @param {Object} field definición del campo (value_fields, initial_values, props)
         */
        init: function (root, field) {
            var $ = global.jQuery;
            if (!$ || !$.fn.scheduler) {
                console.warn('[BioenlaceUiWidgets.weekly_scheduler] jQuery o scheduler.js no disponible');
                return;
            }

            var fieldNames = field.value_fields || [];
            var accuracy = field.props && field.props.accuracy != null ? parseInt(field.props.accuracy, 10) : 1;
            if (!isFinite(accuracy) || accuracy < 1) {
                accuracy = 1;
            }

            var data = {};
            for (var d = 1; d <= 7; d++) {
                var name = fieldNames[d - 1];
                var raw = '';
                if (name && root.querySelector) {
                    var inp = root.querySelector('input[name="' + name + '"]');
                    raw = inp ? inp.value : '';
                }
                if (!raw && field.initial_values && field.initial_values[name]) {
                    raw = String(field.initial_values[name]);
                }
                data[d] = parseSlotsCsv(raw, accuracy);
            }

            var $table = $(root).find('[data-weekly-scheduler-mount]');
            if (!$table.length) {
                return;
            }

            if ($table.data('scheduler')) {
                try {
                    $table.scheduler('destroy');
                } catch (e) {
                    $table.removeData('scheduler');
                }
            }

            $table.scheduler({
                accuracy: accuracy,
                data: data,
                onSelect: function () {
                    var valores = $table.scheduler('val');
                    syncHiddenFromVal(root, fieldNames, valores);
                }
            });
        }
    };
})(window);
