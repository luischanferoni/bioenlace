/**
 * Fechas legibles en castellano (notificaciones, listados).
 * Expone window.BioenlaceFecha.
 */
(function (window) {
    'use strict';

    var NS = (window.BioenlaceFecha = window.BioenlaceFecha || {});

    var WEEKDAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function startOfDay(d) {
        var x = new Date(d.getTime());
        x.setHours(0, 0, 0, 0);
        return x;
    }

    /**
     * Parsea ISO, MySQL datetime (YYYY-MM-DD HH:MM:SS) u otros formatos del API.
     * @param {string|number|Date|null|undefined} value
     * @returns {Date|null}
     */
    function parseDateTime(value) {
        if (value == null || value === '') {
            return null;
        }
        if (value instanceof Date) {
            return isNaN(value.getTime()) ? null : value;
        }

        var s = String(value).trim();
        if (s === '') {
            return null;
        }

        var m = /^(\d{4})-(\d{2})-(\d{2})[\sT](\d{2}):(\d{2})(?::(\d{2}))?/.exec(s);
        if (m) {
            var d = new Date(
                parseInt(m[1], 10),
                parseInt(m[2], 10) - 1,
                parseInt(m[3], 10),
                parseInt(m[4], 10),
                parseInt(m[5], 10),
                parseInt(m[6] || '0', 10)
            );
            return isNaN(d.getTime()) ? null : d;
        }

        var onlyDate = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
        if (onlyDate) {
            var d2 = new Date(
                parseInt(onlyDate[1], 10),
                parseInt(onlyDate[2], 10) - 1,
                parseInt(onlyDate[3], 10)
            );
            return isNaN(d2.getTime()) ? null : d2;
        }

        var parsed = new Date(s);
        return isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatHoraCorta(d) {
        return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
    }

    /**
     * Fecha relativa para bandeja de notificaciones.
     * @param {string|number|Date|null|undefined} value
     * @returns {string}
     */
    function formatNotificacion(value) {
        var d = parseDateTime(value);
        if (!d) {
            return value != null ? String(value) : '';
        }

        var now = new Date();
        var diffMs = now.getTime() - d.getTime();
        if (diffMs < 0) {
            return formatDateTimeAmigable(d);
        }

        var diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) {
            return 'Ahora';
        }
        if (diffMin < 60) {
            return 'Hace ' + diffMin + (diffMin === 1 ? ' minuto' : ' minutos');
        }

        var hora = formatHoraCorta(d);
        var today = startOfDay(now);
        var thatDay = startOfDay(d);
        var diffDays = Math.round((thatDay.getTime() - today.getTime()) / 86400000);

        if (diffDays === 0) {
            return 'Hoy, ' + hora;
        }
        if (diffDays === -1) {
            return 'Ayer, ' + hora;
        }
        if (diffDays < 0 && diffDays >= -6) {
            return WEEKDAYS[d.getDay()] + ', ' + hora;
        }

        return formatDateTimeAmigable(d);
    }

    /**
     * @param {Date} d
     * @returns {string}
     */
    function formatDateTimeAmigable(d) {
        var hora = formatHoraCorta(d);
        var fecha = pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1);
        if (d.getFullYear() !== new Date().getFullYear()) {
            fecha += '/' + d.getFullYear();
        }
        return fecha + ', ' + hora;
    }

    NS.parseDateTime = parseDateTime;
    NS.formatNotificacion = formatNotificacion;
    NS.formatDateTimeAmigable = formatDateTimeAmigable;
})(window);
