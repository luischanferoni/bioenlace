/**
 * Renderiza el bloque declarativo `capture_review` (API encounter/analizar) en el DOM.
 */
(function (window) {
    'use strict';

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function defaultStagedIds(review) {
        var ids = review && review.default_staged_item_ids;
        if (Array.isArray(ids) && ids.length) {
            return ids.slice();
        }
        var out = [];
        (review.categories || []).forEach(function (cat) {
            (cat.items || []).forEach(function (item) {
                if (item && item.id) {
                    out.push(item.id);
                }
            });
        });
        return out;
    }

    function hasExtractedContent(review) {
        if (!review || review.system_error) {
            return false;
        }
        return (review.categories || []).some(function (c) {
            return c.items && c.items.length;
        });
    }

    function buildDatosExtraidos(review, stagedIdSet) {
        var out = {};
        if (!review || !review.categories) {
            return out;
        }
        review.categories.forEach(function (cat) {
            var rows = [];
            (cat.items || []).forEach(function (item) {
                if (!item || !item.id) {
                    return;
                }
                // Incluir si está tildado. source=ai/clinical es solo señal UI.
                if (stagedIdSet.has(item.id)) {
                    rows.push(
                        item.payload && typeof item.payload === 'object'
                            ? item.payload
                            : { texto: item.label || '' }
                    );
                }
            });
            if (rows.length) {
                out[cat.title] = rows;
                if (cat.model && cat.model !== cat.title) {
                    out[cat.model] = rows;
                }
            }
        });
        return out;
    }

    function buildFullAnalisisExtraidos(review) {
        var all = new Set();
        if (!review || !review.categories) {
            return {};
        }
        review.categories.forEach(function (cat) {
            (cat.items || []).forEach(function (item) {
                if (item && item.id) {
                    all.add(item.id);
                }
            });
        });
        return buildDatosExtraidos(review, all);
    }

    function collectStagedIds(root) {
        var set = new Set();
        if (!root) {
            return set;
        }
        root.querySelectorAll('[data-capture-item-id].active').forEach(function (el) {
            var id = el.getAttribute('data-capture-item-id');
            if (id) {
                set.add(id);
            }
        });
        return set;
    }

    function canConfirm(review, stagedIdSet) {
        if (!review) {
            return false;
        }
        if (review.system_error) {
            return false;
        }
        if (review.puede_confirmar === false) {
            return false;
        }
        var texto = (review.texto_original || '').trim();
        if (!texto) {
            return false;
        }
        if (review.tiene_datos_faltantes) {
            return false;
        }
        if (hasExtractedContent(review) && stagedIdSet.size === 0) {
            return false;
        }
        return true;
    }

    function renderItemChip(item, isActive) {
        var label = item.label || '';
        if (item.subtitle) {
            label += ' (' + item.subtitle + ')';
        }
        var active = isActive !== false;
        var btnClass = active
            ? 'btn btn-sm btn-outline-primary capture-review-item active me-1 mb-1'
            : 'btn btn-sm btn-outline-secondary capture-review-item me-1 mb-1';
        var iconClass = active ? 'bi bi-check-circle me-1' : 'bi bi-plus-circle me-1';
        return (
            '<button type="button" class="' +
            btnClass +
            '" data-capture-item-id="' +
            escapeHtml(item.id) +
            '" aria-pressed="' +
            (active ? 'true' : 'false') +
            '">' +
            '<i class="' +
            iconClass +
            '"></i>' +
            escapeHtml(label) +
            '</button>'
        );
    }

    function render(review, options) {
        options = options || {};
        if (!review) {
            return { html: '', stagedIds: [] };
        }

        var stagedIds = defaultStagedIds(review);
        if ((!stagedIds || !stagedIds.length) && hasExtractedContent(review)) {
            stagedIds = [];
            (review.categories || []).forEach(function (cat) {
                (cat.items || []).forEach(function (item) {
                    if (item && item.id) {
                        stagedIds.push(item.id);
                    }
                });
            });
        }
        var stagedSet = {};
        stagedIds.forEach(function (id) {
            stagedSet[id] = true;
        });
        var parts = [];

        parts.push('<div class="capture-review-panel">');
        parts.push(
            '<div class="text-center mb-3">' +
                '<span class="fw-semibold text-decoration-underline">Nota de esta atención</span>' +
                '</div>'
        );

        parts.push('<div class="mb-3">');
        parts.push('<div class="fw-semibold mb-1">Texto registrado</div>');
        parts.push('<div>' + escapeHtml(review.texto_original || '') + '</div>');
        parts.push('</div>');

        if (options.textoFormateado) {
            parts.push('<div class="mb-3">');
            parts.push('<div class="fw-semibold mb-1">Texto formateado</div>');
            parts.push('<div class="texto-formateado">' + options.textoFormateado + '</div>');
            parts.push('</div>');
        } else if (
            review.texto_procesado &&
            review.texto_procesado.trim() &&
            review.texto_procesado.trim() !== (review.texto_original || '').trim()
        ) {
            parts.push('<div class="mb-3">');
            parts.push('<div class="fw-semibold mb-1">Texto procesado</div>');
            parts.push('<div class="small">' + escapeHtml(review.texto_procesado) + '</div>');
            parts.push('</div>');
        }

        if (review.system_error) {
            var err = review.system_error;
            parts.push('<div class="alert alert-danger" role="alert">');
            parts.push('<h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Error en el procesamiento</h6>');
            parts.push('<p class="mb-0">' + escapeHtml(err.texto || '') + '</p>');
            if (err.detalle) {
                parts.push('<hr><p class="mb-0"><strong>Recomendación:</strong> ' + escapeHtml(err.detalle) + '</p>');
            }
            parts.push('</div>');
        } else if (!hasExtractedContent(review)) {
            parts.push(
                '<div class="alert alert-info" role="status">La IA no extrajo datos estructurados. Podés confirmar igual con el texto registrado.</div>'
            );
        } else {
            parts.push('<div class="fw-semibold mb-2">Resultado del procesamiento</div>');
            review.categories.forEach(function (cat) {
                parts.push('<div class="mb-3">');
                parts.push('<div class="small fw-semibold mb-1">');
                parts.push(escapeHtml(cat.title || ''));
                if (cat.required) {
                    parts.push(' <span class="badge bg-danger">Requerido</span>');
                }
                parts.push('</div>');

                if (!cat.items || !cat.items.length) {
                    var emptyClass = cat.required ? 'text-danger fw-bolder small' : 'text-warning fw-bolder small ps-3';
                    var emptyMsg = cat.required
                        ? 'Falta información en esta categoría.'
                        : 'Sin datos en esta categoría.';
                    parts.push('<p class="' + emptyClass + '">' + escapeHtml(emptyMsg) + '</p>');
                } else {
                    parts.push('<div class="d-flex flex-wrap gap-1">');
                    cat.items.forEach(function (item) {
                        parts.push(renderItemChip(item, !!stagedSet[item.id]));
                    });
                    parts.push('</div>');
                }
                parts.push('</div>');
            });
        }

        if (review.tiene_datos_faltantes) {
            var faltantesMsg = '';
            if (review.datos_faltantes_detalle && review.datos_faltantes_detalle.message) {
                faltantesMsg = String(review.datos_faltantes_detalle.message).trim();
            }
            if (!faltantesMsg) {
                faltantesMsg =
                    'Faltan categorías o campos obligatorios. Completá el texto y volvé a analizar. No se puede confirmar hasta completarlos.';
            }
            parts.push(
                '<div class="alert alert-warning" role="status">' +
                    faltantesMsg.replace(/</g, '&lt;').replace(/>/g, '&gt;') +
                    '</div>'
            );
        }

        parts.push('</div>');

        return {
            html: parts.join(''),
            stagedIds: stagedIds,
        };
    }

    function bindItemToggles(root, onChange) {
        if (!root) {
            return;
        }
        root.querySelectorAll('.capture-review-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var willActivate = !btn.classList.contains('active');
                btn.classList.toggle('active', willActivate);
                btn.classList.toggle('btn-outline-primary', willActivate);
                btn.classList.toggle('btn-outline-secondary', !willActivate);
                btn.setAttribute('aria-pressed', willActivate ? 'true' : 'false');
                var icon = btn.querySelector('i');
                if (icon) {
                    icon.className = willActivate
                        ? 'bi bi-check-circle me-1'
                        : 'bi bi-plus-circle me-1';
                }
                if (typeof onChange === 'function') {
                    onChange(collectStagedIds(root));
                }
            });
        });
    }

    window.EncounterCaptureReview = {
        render: render,
        bindItemToggles: bindItemToggles,
        collectStagedIds: collectStagedIds,
        buildDatosExtraidos: buildDatosExtraidos,
        buildFullAnalisisExtraidos: buildFullAnalisisExtraidos,
        canConfirm: canConfirm,
        defaultStagedIds: defaultStagedIds,
        hasExtractedContent: hasExtractedContent,
    };
})(window);
