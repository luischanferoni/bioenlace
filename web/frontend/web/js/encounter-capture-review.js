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
                if (item && item.id && !isAiSuggestion(item)) {
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

    function isAiSuggestion(item) {
        return !!item && item.source === 'ai';
    }

    /**
     * Ítems anclados en el texto del profesional: los sugeridos por IA vienen sin tildar
     * y confirmarlos es opcional, así que no pueden bloquear el guardado.
     */
    function hasClinicalItems(review) {
        if (!review || review.system_error) {
            return false;
        }
        return (review.categories || []).some(function (c) {
            return (c.items || []).some(function (item) {
                return !isAiSuggestion(item);
            });
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
        if (hasClinicalItems(review) && stagedIdSet.size === 0) {
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
        var baseClass = isAiSuggestion(item) ? 'btn-outline-info' : 'btn-outline-secondary';
        var btnClass = active
            ? 'btn btn-sm btn-outline-primary capture-review-item active me-1 mb-1'
            : 'btn btn-sm ' + baseClass + ' capture-review-item me-1 mb-1';
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
        if ((!stagedIds || !stagedIds.length) && hasClinicalItems(review)) {
            stagedIds = [];
            (review.categories || []).forEach(function (cat) {
                (cat.items || []).forEach(function (item) {
                    if (item && item.id && !isAiSuggestion(item)) {
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
                    var delTexto = cat.items.filter(function (item) {
                        return !isAiSuggestion(item);
                    });
                    var sugeridos = cat.items.filter(isAiSuggestion);

                    if (delTexto.length) {
                        parts.push('<div class="d-flex flex-wrap gap-1">');
                        delTexto.forEach(function (item) {
                            parts.push(renderItemChip(item, !!stagedSet[item.id]));
                        });
                        parts.push('</div>');
                    }
                    if (sugeridos.length) {
                        parts.push('<div class="d-flex flex-wrap gap-2 mt-1">');
                        sugeridos.forEach(function (item) {
                            parts.push('<div class="d-flex flex-column">');
                            parts.push(renderItemChip(item, !!stagedSet[item.id]));
                            parts.push('<span class="text-info small">Sugerido por IA</span>');
                            parts.push('</div>');
                        });
                        parts.push('</div>');
                    }
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

        var issues = Array.isArray(review.issues) ? review.issues : [];
        if (issues.length) {
            parts.push('<div class="capture-review-issues mb-3">');
            parts.push('<div class="fw-semibold mb-2">Completar datos</div>');
            issues.forEach(function (issue) {
                if (!issue || !issue.id) {
                    return;
                }
                parts.push('<div class="mb-3" data-capture-issue-id="' + escapeHtml(issue.id) + '">');
                parts.push('<div class="small">' + escapeHtml(issue.message || '') + '</div>');
                if (issue.field) {
                    parts.push(
                        '<div class="text-muted small mb-1">' + escapeHtml(issue.field) + '</div>'
                    );
                }
                var options = Array.isArray(issue.options) ? issue.options : [];
                if (options.length) {
                    parts.push('<div class="d-flex flex-wrap gap-1 mb-1">');
                    options.forEach(function (opt) {
                        if (!opt || typeof opt.value === 'undefined') {
                            return;
                        }
                        parts.push(
                            '<button type="button" class="btn btn-sm btn-outline-secondary capture-issue-option me-1 mb-1" ' +
                                'data-issue-id="' +
                                escapeHtml(issue.id) +
                                '" data-issue-value="' +
                                escapeHtml(String(opt.value)) +
                                '" aria-pressed="false">' +
                                escapeHtml(opt.label || String(opt.value)) +
                                '</button>'
                        );
                    });
                    parts.push('</div>');
                }
                if (issue.allow_custom) {
                    parts.push(
                        '<input type="text" class="form-control form-control-sm capture-issue-custom" ' +
                            'data-issue-id="' +
                            escapeHtml(issue.id) +
                            '" placeholder="' +
                            escapeHtml(options.length ? 'Otra opción…' : 'Completá el valor') +
                            '">'
                    );
                }
                parts.push('</div>');
            });
            parts.push(
                '<button type="button" class="btn btn-sm btn-outline-primary capture-apply-resolutions">' +
                    'Aplicar respuestas</button>'
            );
            parts.push('</div>');
        }

        parts.push('</div>');

        return {
            html: parts.join(''),
            stagedIds: stagedIds,
        };
    }

    function collectResolutions(root) {
        var out = {};
        if (!root) {
            return out;
        }
        root.querySelectorAll('[data-capture-issue-id]').forEach(function (block) {
            var issueId = block.getAttribute('data-capture-issue-id');
            if (!issueId) {
                return;
            }
            var active = block.querySelector('.capture-issue-option.active');
            if (active) {
                out[issueId] = active.getAttribute('data-issue-value');
                return;
            }
            var custom = block.querySelector('.capture-issue-custom');
            if (custom && String(custom.value || '').trim() !== '') {
                out[issueId] = String(custom.value).trim();
            }
        });
        return out;
    }

    function bindIssueResolutions(root, onApply) {
        if (!root) {
            return;
        }
        root.querySelectorAll('.capture-issue-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var block = btn.closest('[data-capture-issue-id]');
                if (!block) {
                    return;
                }
                var willActivate = !btn.classList.contains('active');
                block.querySelectorAll('.capture-issue-option').forEach(function (other) {
                    other.classList.remove('active', 'btn-outline-primary');
                    other.classList.add('btn-outline-secondary');
                    other.setAttribute('aria-pressed', 'false');
                });
                if (willActivate) {
                    btn.classList.add('active', 'btn-outline-primary');
                    btn.classList.remove('btn-outline-secondary');
                    btn.setAttribute('aria-pressed', 'true');
                    var custom = block.querySelector('.capture-issue-custom');
                    if (custom) {
                        custom.value = '';
                    }
                }
            });
        });
        root.querySelectorAll('.capture-issue-custom').forEach(function (input) {
            input.addEventListener('input', function () {
                var block = input.closest('[data-capture-issue-id]');
                if (!block || String(input.value || '').trim() === '') {
                    return;
                }
                block.querySelectorAll('.capture-issue-option').forEach(function (other) {
                    other.classList.remove('active', 'btn-outline-primary');
                    other.classList.add('btn-outline-secondary');
                    other.setAttribute('aria-pressed', 'false');
                });
            });
        });
        var applyBtn = root.querySelector('.capture-apply-resolutions');
        if (applyBtn && typeof onApply === 'function') {
            applyBtn.addEventListener('click', function () {
                onApply(collectResolutions(root));
            });
        }
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
        bindIssueResolutions: bindIssueResolutions,
        collectStagedIds: collectStagedIds,
        collectResolutions: collectResolutions,
        buildDatosExtraidos: buildDatosExtraidos,
        buildFullAnalisisExtraidos: buildFullAnalisisExtraidos,
        canConfirm: canConfirm,
        defaultStagedIds: defaultStagedIds,
        hasExtractedContent: hasExtractedContent,
    };
})(window);
