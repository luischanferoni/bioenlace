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

    function canConfirm(review, stagedIdSet, resolutions) {
        if (!review) {
            return false;
        }
        if (review.system_error) {
            return false;
        }
        var texto = (review.texto_original || '').trim();
        if (!texto) {
            return false;
        }
        if (hasClinicalItems(review) && stagedIdSet.size === 0) {
            return false;
        }
        var resMap = resolutions && typeof resolutions === 'object' ? resolutions : {};
        var issues = Array.isArray(review.issues) ? review.issues : [];
        for (var i = 0; i < issues.length; i++) {
            var issue = issues[i];
            if (!issue || !issue.id) {
                continue;
            }
            var m = String(issue.id).match(/^(.*)::(\d+):/);
            if (m) {
                var itemId = m[1] + '::' + m[2];
                if (stagedIdSet.size > 0 && !stagedIdSet.has(itemId)) {
                    continue;
                }
            }
            var val = resMap[issue.id];
            if (val === undefined || val === null || String(val).trim() === '') {
                return false;
            }
        }
        return true;
    }

    function isInternalTipoToken(value) {
        var folded = String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[-\s]+/g, '_');
        return (
            folded === 'follow_up' ||
            folded === 'followup' ||
            folded === 'counseling' ||
            folded === 'counselling' ||
            folded === 'conditional' ||
            folded === 'ordered' ||
            folded === 'mentioned' ||
            folded === 'order'
        );
    }

    function cleanSubtitle(subtitle) {
        if (!subtitle) {
            return '';
        }
        return String(subtitle)
            .split(/\s*·\s*/)
            .map(function (part) {
                return String(part || '').trim();
            })
            .filter(function (part) {
                return part !== '' && !isInternalTipoToken(part);
            })
            .join(' · ');
    }

    function incompleteItemId(inc) {
        return String(inc.category || '') + '::' + String(inc.index);
    }

    function indexIncompleteByItemId(review) {
        var map = {};
        var detalle = review && review.datos_faltantes_detalle;
        var items =
            detalle && Array.isArray(detalle.incomplete_items)
                ? detalle.incomplete_items
                : [];
        items.forEach(function (inc) {
            if (!inc) {
                return;
            }
            map[incompleteItemId(inc)] = inc;
        });
        return map;
    }

    function indexIssuesByItemId(issues) {
        var map = {};
        (Array.isArray(issues) ? issues : []).forEach(function (issue) {
            if (!issue || !issue.id) {
                return;
            }
            var m = String(issue.id).match(/^(.*)::(\d+):/);
            if (!m) {
                return;
            }
            var itemId = m[1] + '::' + m[2];
            if (!map[itemId]) {
                map[itemId] = [];
            }
            map[itemId].push(issue);
        });
        return map;
    }

    function incompleteItemMessage(inc) {
        var fields = (inc.missing_fields || []).join(', ');
        var label = String(inc.label || '').trim();
        var cat = String(inc.category || '');
        if (label !== '') {
            return 'En ' + cat + ' («' + label + '») faltan: ' + fields + '.';
        }
        return 'En ' + cat + ' faltan: ' + fields + '.';
    }

    function renderItemChip(item, isActive, isIncomplete) {
        var label = item.label || '';
        var subtitle = cleanSubtitle(item.subtitle);
        if (subtitle) {
            label += ' (' + subtitle + ')';
        }
        var active = isActive !== false;
        var btnClass;
        if (isIncomplete) {
            btnClass = 'btn btn-sm btn-danger capture-review-item me-1 mb-1';
            if (active) {
                btnClass += ' active';
            }
        } else {
            var baseClass = isAiSuggestion(item) ? 'btn-outline-info' : 'btn-outline-secondary';
            btnClass = active
                ? 'btn btn-sm btn-outline-primary capture-review-item active me-1 mb-1'
                : 'btn btn-sm ' + baseClass + ' capture-review-item me-1 mb-1';
        }
        var iconClass = active ? 'bi bi-check-circle me-1' : 'bi bi-plus-circle me-1';
        return (
            '<button type="button" class="' +
            btnClass +
            '" data-capture-item-id="' +
            escapeHtml(item.id) +
            '" data-incomplete="' +
            (isIncomplete ? '1' : '0') +
            '" data-ai-suggestion="' +
            (isAiSuggestion(item) ? '1' : '0') +
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

    function renderIssueBlock(issue) {
        if (!issue || !issue.id) {
            return '';
        }
        var parts = [];
        parts.push('<div class="mb-2" data-capture-issue-id="' + escapeHtml(issue.id) + '">');
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
        if (issue.allow_custom === true) {
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
        return parts.join('');
    }

    function renderItemBlock(item, isActive, incomplete, itemIssues) {
        var parts = [];
        var isIncomplete = !!incomplete;
        parts.push(
            '<div class="capture-review-item-block mb-2" data-capture-item-block="' +
                escapeHtml(item.id) +
                '">'
        );
        parts.push(renderItemChip(item, isActive, isIncomplete));
        if (incomplete) {
            parts.push(
                '<div class="text-danger small mt-1">' +
                    escapeHtml(incompleteItemMessage(incomplete)) +
                    '</div>'
            );
        }
        if (itemIssues && itemIssues.length) {
            parts.push('<div class="capture-review-item-issues mt-2">');
            parts.push('<div class="small fw-semibold text-danger mb-1">Completar datos</div>');
            itemIssues.forEach(function (issue) {
                parts.push(renderIssueBlock(issue));
            });
            parts.push('</div>');
        }
        parts.push('</div>');
        return parts.join('');
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
            var missingOnly = review.datos_faltantes_detalle &&
                Array.isArray(review.datos_faltantes_detalle.missing_categories)
                ? review.datos_faltantes_detalle.missing_categories
                : [];
            if (missingOnly.length) {
                parts.push(
                    '<div class="alert alert-warning" role="status">' +
                        escapeHtml(
                            'Faltan categorías obligatorias: ' + missingOnly.join(', ') + '.'
                        ) +
                        '</div>'
                );
            }
        } else {
            var incompleteById = indexIncompleteByItemId(review);
            var issuesByItemId = indexIssuesByItemId(review.issues);
            var renderedIssueIds = {};

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
                        delTexto.forEach(function (item) {
                            var itemIssues = issuesByItemId[item.id] || [];
                            itemIssues.forEach(function (iss) {
                                if (iss && iss.id) {
                                    renderedIssueIds[iss.id] = true;
                                }
                            });
                            parts.push(
                                renderItemBlock(
                                    item,
                                    !!stagedSet[item.id],
                                    incompleteById[item.id] || null,
                                    itemIssues
                                )
                            );
                        });
                    }
                    if (sugeridos.length) {
                        sugeridos.forEach(function (item) {
                            var itemIssues = issuesByItemId[item.id] || [];
                            itemIssues.forEach(function (iss) {
                                if (iss && iss.id) {
                                    renderedIssueIds[iss.id] = true;
                                }
                            });
                            parts.push('<div class="mb-2">');
                            parts.push(
                                renderItemBlock(
                                    item,
                                    !!stagedSet[item.id],
                                    incompleteById[item.id] || null,
                                    itemIssues
                                )
                            );
                            parts.push('<span class="text-info small">Sugerido por IA</span>');
                            parts.push('</div>');
                        });
                    }
                }
                parts.push('</div>');
            });

            var detalle = review.datos_faltantes_detalle || {};
            var missingCats = Array.isArray(detalle.missing_categories)
                ? detalle.missing_categories
                : [];
            if (missingCats.length) {
                parts.push(
                    '<div class="alert alert-warning" role="status">' +
                        escapeHtml(
                            'Faltan categorías obligatorias: ' + missingCats.join(', ') + '.'
                        ) +
                        '</div>'
                );
            }

            var orphanIssues = (Array.isArray(review.issues) ? review.issues : []).filter(
                function (issue) {
                    return issue && issue.id && !renderedIssueIds[issue.id];
                }
            );
            if (orphanIssues.length) {
                parts.push('<div class="capture-review-issues mb-3">');
                parts.push('<div class="fw-semibold mb-2 text-danger">Completar datos</div>');
                orphanIssues.forEach(function (issue) {
                    parts.push(renderIssueBlock(issue));
                });
                parts.push('</div>');
            }
        }

        parts.push(renderOpenProblems(review.open_problems));

        parts.push('</div>');

        return {
            html: parts.join(''),
            stagedIds: stagedIds,
        };
    }

    function renderOpenProblems(openProblems) {
        if (!openProblems || typeof openProblems !== 'object') {
            return '';
        }
        var conditions = Array.isArray(openProblems.conditions) ? openProblems.conditions : [];
        var carePlans = Array.isArray(openProblems.care_plans) ? openProblems.care_plans : [];
        if (!conditions.length && !carePlans.length) {
            return '';
        }
        var parts = [];
        parts.push('<div class="capture-open-problems mb-3">');
        parts.push('<div class="fw-semibold mb-2">Problemas y tratamientos abiertos</div>');
        parts.push(
            '<p class="small text-muted mb-2">Opcional: indicá el estado al cerrar. Si no elegís, se mantienen como están.</p>'
        );
        conditions.forEach(function (item) {
            parts.push(renderOpenProblemItem(item, 'condition', openProblems.condition_options));
        });
        carePlans.forEach(function (item) {
            parts.push(renderOpenProblemItem(item, 'care_plan', openProblems.care_plan_options));
        });
        parts.push('</div>');
        return parts.join('');
    }

    function renderOpenProblemItem(item, kind, sharedOptions) {
        if (!item || !item.id) {
            return '';
        }
        var parts = [];
        parts.push(
            '<div class="mb-3" data-open-problem-kind="' +
                escapeHtml(kind) +
                '" data-open-problem-id="' +
                escapeHtml(String(item.id)) +
                '">'
        );
        parts.push('<div class="small fw-semibold">' + escapeHtml(item.label || '') + '</div>');
        if (item.detail) {
            parts.push(
                '<div class="text-muted small">' + escapeHtml(String(item.detail)) + '</div>'
            );
        }
        if (item.status_label || item.status || item.clinical_status) {
            parts.push(
                '<div class="text-muted small mb-1">' +
                    escapeHtml(item.status_label || item.clinical_status || item.status || '') +
                    '</div>'
            );
        }
        var options = Array.isArray(item.options) && item.options.length
            ? item.options
            : Array.isArray(sharedOptions)
              ? sharedOptions
              : [];
        if (options.length) {
            parts.push('<div class="d-flex flex-wrap gap-1">');
            options.forEach(function (opt) {
                if (!opt || typeof opt.value === 'undefined') {
                    return;
                }
                parts.push(
                    '<button type="button" class="btn btn-sm btn-outline-secondary capture-open-problem-option me-1 mb-1" ' +
                        'data-problem-value="' +
                        escapeHtml(String(opt.value)) +
                        '" aria-pressed="false">' +
                        escapeHtml(opt.label || String(opt.value)) +
                        '</button>'
                );
            });
            parts.push('</div>');
        }
        parts.push('</div>');
        return parts.join('');
    }

    function collectOpenProblemResolutions(root) {
        var conditions = {};
        var carePlans = {};
        if (!root) {
            return { condition_resolutions: conditions, care_plan_resolutions: carePlans };
        }
        root.querySelectorAll('[data-open-problem-id]').forEach(function (block) {
            var id = block.getAttribute('data-open-problem-id');
            var kind = block.getAttribute('data-open-problem-kind');
            var active = block.querySelector('.capture-open-problem-option.active');
            if (!id || !active) {
                return;
            }
            var value = active.getAttribute('data-problem-value');
            if (value == null || value === '') {
                return;
            }
            if (kind === 'care_plan') {
                carePlans[id] = value;
            } else {
                conditions[id] = value;
            }
        });
        return { condition_resolutions: conditions, care_plan_resolutions: carePlans };
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

    function bindIssueResolutions(root, onChange) {
        if (!root) {
            return;
        }
        function notify() {
            if (typeof onChange === 'function') {
                onChange();
            }
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
                notify();
            });
        });
        root.querySelectorAll('.capture-issue-custom').forEach(function (input) {
            input.addEventListener('input', function () {
                var block = input.closest('[data-capture-issue-id]');
                if (!block) {
                    return;
                }
                if (String(input.value || '').trim() !== '') {
                    block.querySelectorAll('.capture-issue-option').forEach(function (other) {
                        other.classList.remove('active', 'btn-outline-primary');
                        other.classList.add('btn-outline-secondary');
                        other.setAttribute('aria-pressed', 'false');
                    });
                }
                notify();
            });
        });
        root.querySelectorAll('.capture-open-problem-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var block = btn.closest('[data-open-problem-id]');
                if (!block) {
                    return;
                }
                var willActivate = !btn.classList.contains('active');
                block.querySelectorAll('.capture-open-problem-option').forEach(function (other) {
                    other.classList.remove('active', 'btn-outline-primary');
                    other.classList.add('btn-outline-secondary');
                    other.setAttribute('aria-pressed', 'false');
                });
                if (willActivate) {
                    btn.classList.add('active', 'btn-outline-primary');
                    btn.classList.remove('btn-outline-secondary');
                    btn.setAttribute('aria-pressed', 'true');
                }
            });
        });
    }

    function bindItemToggles(root, onChange) {
        if (!root) {
            return;
        }
        root.querySelectorAll('.capture-review-item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var willActivate = !btn.classList.contains('active');
                var incomplete = btn.getAttribute('data-incomplete') === '1';
                var isAi = btn.getAttribute('data-ai-suggestion') === '1';
                btn.classList.toggle('active', willActivate);
                btn.classList.remove(
                    'btn-outline-primary',
                    'btn-outline-secondary',
                    'btn-outline-info',
                    'btn-outline-danger',
                    'btn-danger'
                );
                if (incomplete) {
                    btn.classList.add('btn-danger');
                } else if (willActivate) {
                    btn.classList.add('btn-outline-primary');
                } else if (isAi) {
                    btn.classList.add('btn-outline-info');
                } else {
                    btn.classList.add('btn-outline-secondary');
                }
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
        collectOpenProblemResolutions: collectOpenProblemResolutions,
        buildDatosExtraidos: buildDatosExtraidos,
        buildFullAnalisisExtraidos: buildFullAnalisisExtraidos,
        canConfirm: canConfirm,
        defaultStagedIds: defaultStagedIds,
        hasExtractedContent: hasExtractedContent,
    };
})(window);
