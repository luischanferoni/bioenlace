/**
 * Renderiza el bloque declarativo `capture_review` (API encounter/analizar) en el DOM.
 * Camino A: estructura en <template> PHP; JS solo clona/rellena.
 */
(function (window) {
    'use strict';

    function tpl(id) {
        var t = document.getElementById(id);
        if (!t || !t.content) return null;
        return document.importNode(t.content, true);
    }

    function setText(root, sel, text) {
        var el = root && root.querySelector ? root.querySelector(sel) : null;
        if (el) el.textContent = text == null ? '' : String(text);
    }

    function show(el, on) {
        if (!el) return;
        el.classList.toggle('d-none', !on);
    }

    function appendChildren(parent, frag) {
        if (!parent || !frag) return;
        while (frag.firstChild) {
            parent.appendChild(frag.firstChild);
        }
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

    function appendAlert(parent, message, variant, role) {
        var frag = tpl('tpl-capture-alert');
        if (!frag) return;
        var root = frag.firstElementChild;
        root.classList.add('alert-' + (variant || 'info'));
        if (role) root.setAttribute('role', role);
        setText(frag, '[data-field="message"]', message);
        parent.appendChild(frag);
    }

    function appendTextBlock(parent, title, bodyText, asHtml) {
        var frag = tpl(asHtml ? 'tpl-capture-text-block-html' : 'tpl-capture-text-block');
        if (!frag) return;
        setText(frag, '[data-field="title"]', title);
        var body = frag.querySelector('[data-field="body"]');
        if (body) {
            if (asHtml) {
                body.innerHTML = bodyText || '';
            } else {
                body.textContent = bodyText == null ? '' : String(bodyText);
            }
        }
        parent.appendChild(frag);
    }

    function renderItemChip(item, isActive, isIncomplete) {
        var frag = tpl('tpl-capture-item-chip');
        if (!frag) return null;
        var btn = frag.querySelector('button') || frag.firstElementChild;
        var label = item.label || '';
        var subtitle = cleanSubtitle(item.subtitle);
        if (subtitle) {
            label += ' (' + subtitle + ')';
        }
        var active = isActive !== false;
        btn.setAttribute('data-capture-item-id', item.id || '');
        btn.setAttribute('data-incomplete', isIncomplete ? '1' : '0');
        btn.setAttribute('data-ai-suggestion', isAiSuggestion(item) ? '1' : '0');
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.className = 'btn btn-sm capture-review-item me-1 mb-1';
        if (isIncomplete) {
            btn.classList.add('btn-danger');
            if (active) btn.classList.add('active');
        } else if (active) {
            btn.classList.add('btn-outline-primary', 'active');
        } else if (isAiSuggestion(item)) {
            btn.classList.add('btn-outline-info');
        } else {
            btn.classList.add('btn-outline-secondary');
        }
        var icon = btn.querySelector('[data-field="icon"]');
        if (icon) {
            icon.className = active ? 'bi bi-check-circle me-1' : 'bi bi-plus-circle me-1';
        }
        setText(frag, '[data-field="label"]', label);
        return frag;
    }

    function renderIssueBlock(issue) {
        if (!issue || !issue.id) {
            return null;
        }
        var frag = tpl('tpl-capture-issue');
        if (!frag) return null;
        var root = frag.firstElementChild;
        root.setAttribute('data-capture-issue-id', issue.id);
        if (issue.field) {
            var fieldEl = frag.querySelector('[data-slot="field"]');
            show(fieldEl, true);
            setText(frag, '[data-field="field"]', issue.field);
        }
        var options = Array.isArray(issue.options) ? issue.options : [];
        var optsSlot = frag.querySelector('[data-slot="options"]');
        if (options.length && optsSlot) {
            show(optsSlot, true);
            optsSlot.classList.remove('d-none');
            options.forEach(function (opt) {
                if (!opt || typeof opt.value === 'undefined') return;
                var optFrag = tpl('tpl-capture-issue-option');
                if (!optFrag) return;
                var optBtn = optFrag.querySelector('button') || optFrag.firstElementChild;
                optBtn.setAttribute('data-issue-id', issue.id);
                optBtn.setAttribute('data-issue-value', String(opt.value));
                setText(optFrag, '[data-field="label"]', opt.label || String(opt.value));
                optsSlot.appendChild(optFrag);
            });
        }
        if (issue.allow_custom === true) {
            var custom = frag.querySelector('[data-field="custom"]');
            if (custom) {
                custom.classList.remove('d-none');
                custom.setAttribute('data-issue-id', issue.id);
                custom.placeholder = options.length ? 'Otra opción…' : 'Completá el valor';
            }
        }
        return frag;
    }

    function renderItemBlock(item, isActive, incomplete, itemIssues) {
        var frag = tpl('tpl-capture-item-block');
        if (!frag) return null;
        var root = frag.firstElementChild;
        root.setAttribute('data-capture-item-block', item.id || '');
        var chipSlot = frag.querySelector('[data-slot="chip"]');
        var chip = renderItemChip(item, isActive, !!incomplete);
        if (chipSlot && chip) appendChildren(chipSlot, chip);

        if (itemIssues && itemIssues.length) {
            var issuesWrap = frag.querySelector('[data-slot="issues"]');
            var issuesRow = frag.querySelector('[data-slot="issues-row"]');
            show(issuesWrap, true);
            itemIssues.forEach(function (issue) {
                var iss = renderIssueBlock(issue);
                if (iss && issuesRow) issuesRow.appendChild(iss);
            });
        } else if (incomplete) {
            var msgEl = frag.querySelector('[data-slot="incomplete-msg"]');
            show(msgEl, true);
            setText(frag, '[data-field="incomplete-msg"]', incompleteItemMessage(incomplete));
        }
        return frag;
    }

    function renderOpenProblemItem(item, kind, sharedOptions) {
        if (!item || !item.id) {
            return null;
        }
        var frag = tpl('tpl-capture-open-problem-item');
        if (!frag) return null;
        var root = frag.firstElementChild;
        root.setAttribute('data-open-problem-kind', kind);
        root.setAttribute('data-open-problem-id', String(item.id));
        setText(frag, '[data-field="label"]', item.label || '');
        if (item.detail) {
            var detailEl = frag.querySelector('[data-slot="detail"]');
            show(detailEl, true);
            setText(frag, '[data-field="detail"]', String(item.detail));
        }
        var statusText = item.status_label || item.clinical_status || item.status || '';
        if (statusText) {
            var statusEl = frag.querySelector('[data-slot="status"]');
            show(statusEl, true);
            setText(frag, '[data-field="status"]', statusText);
        }
        var options = Array.isArray(item.options) && item.options.length
            ? item.options
            : Array.isArray(sharedOptions)
              ? sharedOptions
              : [];
        var optsSlot = frag.querySelector('[data-slot="options"]');
        if (options.length && optsSlot) {
            show(optsSlot, true);
            options.forEach(function (opt) {
                if (!opt || typeof opt.value === 'undefined') return;
                var optFrag = tpl('tpl-capture-open-problem-option');
                if (!optFrag) return;
                var optBtn = optFrag.querySelector('button') || optFrag.firstElementChild;
                optBtn.setAttribute('data-problem-value', String(opt.value));
                setText(optFrag, '[data-field="label"]', opt.label || String(opt.value));
                optsSlot.appendChild(optFrag);
            });
        }
        return frag;
    }

    function renderOpenProblems(openProblems) {
        if (!openProblems || typeof openProblems !== 'object') {
            return null;
        }
        var conditions = Array.isArray(openProblems.conditions) ? openProblems.conditions : [];
        var carePlans = Array.isArray(openProblems.care_plans) ? openProblems.care_plans : [];
        if (!conditions.length && !carePlans.length) {
            return null;
        }
        var frag = tpl('tpl-capture-open-problems');
        if (!frag) return null;
        var slot = frag.querySelector('[data-slot="items"]');
        conditions.forEach(function (item) {
            var node = renderOpenProblemItem(item, 'condition', openProblems.condition_options);
            if (node && slot) slot.appendChild(node);
        });
        carePlans.forEach(function (item) {
            var node = renderOpenProblemItem(item, 'care_plan', openProblems.care_plan_options);
            if (node && slot) slot.appendChild(node);
        });
        return frag;
    }

    /**
     * @returns {{ node: DocumentFragment|null, stagedIds: string[] }}
     */
    function render(review, options) {
        options = options || {};
        if (!review) {
            return { node: null, stagedIds: [] };
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

        var panelFrag = tpl('tpl-capture-panel');
        if (!panelFrag) {
            return { node: null, stagedIds: stagedIds };
        }
        var body = panelFrag.querySelector('[data-slot="body"]');
        if (!body) {
            return { node: panelFrag, stagedIds: stagedIds };
        }

        appendTextBlock(body, 'Texto registrado', review.texto_original || '', false);

        if (options.textoFormateado) {
            appendTextBlock(body, 'Texto formateado', options.textoFormateado, true);
        } else if (
            review.texto_procesado &&
            review.texto_procesado.trim() &&
            review.texto_procesado.trim() !== (review.texto_original || '').trim()
        ) {
            var procFrag = tpl('tpl-capture-text-block');
            if (procFrag) {
                setText(procFrag, '[data-field="title"]', 'Texto procesado');
                var procBody = procFrag.querySelector('[data-field="body"]');
                if (procBody) {
                    procBody.classList.add('small');
                    procBody.textContent = review.texto_procesado;
                }
                body.appendChild(procFrag);
            }
        }

        if (review.system_error) {
            var err = review.system_error;
            var errFrag = tpl('tpl-capture-system-error');
            if (errFrag) {
                setText(errFrag, '[data-field="texto"]', err.texto || '');
                if (err.detalle) {
                    var det = errFrag.querySelector('[data-slot="detalle"]');
                    show(det, true);
                    setText(errFrag, '[data-field="detalle"]', err.detalle);
                }
                body.appendChild(errFrag);
            }
        } else if (!hasExtractedContent(review)) {
            appendAlert(
                body,
                'La IA no extrajo datos estructurados. Podés confirmar igual con el texto registrado.',
                'info',
                'status'
            );
            var missingOnly =
                review.datos_faltantes_detalle &&
                Array.isArray(review.datos_faltantes_detalle.missing_categories)
                    ? review.datos_faltantes_detalle.missing_categories
                    : [];
            if (missingOnly.length) {
                appendAlert(
                    body,
                    'Faltan categorías obligatorias: ' + missingOnly.join(', ') + '.',
                    'warning',
                    'status'
                );
            }
        } else {
            var incompleteById = indexIncompleteByItemId(review);
            var issuesByItemId = indexIssuesByItemId(review.issues);
            var renderedIssueIds = {};

            var titleFrag = tpl('tpl-capture-resultado-title');
            if (titleFrag) body.appendChild(titleFrag);

            (review.categories || []).forEach(function (cat) {
                var catFrag = tpl('tpl-capture-category');
                if (!catFrag) return;
                setText(catFrag, '[data-field="title"]', cat.title || '');
                show(catFrag.querySelector('[data-slot="required"]'), !!cat.required);
                var itemsSlot = catFrag.querySelector('[data-slot="items"]');

                if (!cat.items || !cat.items.length) {
                    var emptyFrag = tpl('tpl-capture-cat-empty');
                    if (emptyFrag) {
                        var emptyP = emptyFrag.firstElementChild;
                        emptyP.className = cat.required
                            ? 'text-danger fw-bolder small'
                            : 'text-warning fw-bolder small ps-3';
                        setText(emptyFrag, '[data-field="message"]', cat.required
                            ? 'Falta información en esta categoría.'
                            : 'Sin datos en esta categoría.');
                        if (itemsSlot) itemsSlot.appendChild(emptyFrag);
                    }
                } else {
                    var delTexto = cat.items.filter(function (item) {
                        return !isAiSuggestion(item);
                    });
                    var sugeridos = cat.items.filter(isAiSuggestion);

                    delTexto.forEach(function (item) {
                        var itemIssues = issuesByItemId[item.id] || [];
                        itemIssues.forEach(function (iss) {
                            if (iss && iss.id) renderedIssueIds[iss.id] = true;
                        });
                        var block = renderItemBlock(
                            item,
                            !!stagedSet[item.id],
                            incompleteById[item.id] || null,
                            itemIssues
                        );
                        if (block && itemsSlot) itemsSlot.appendChild(block);
                    });
                    sugeridos.forEach(function (item) {
                        var itemIssues = issuesByItemId[item.id] || [];
                        itemIssues.forEach(function (iss) {
                            if (iss && iss.id) renderedIssueIds[iss.id] = true;
                        });
                        var aiWrap = tpl('tpl-capture-ai-wrap');
                        var block = renderItemBlock(
                            item,
                            !!stagedSet[item.id],
                            incompleteById[item.id] || null,
                            itemIssues
                        );
                        if (aiWrap && block) {
                            var itemSlot = aiWrap.querySelector('[data-slot="item"]');
                            if (itemSlot) appendChildren(itemSlot, block);
                            if (itemsSlot) itemsSlot.appendChild(aiWrap);
                        } else if (block && itemsSlot) {
                            itemsSlot.appendChild(block);
                        }
                    });
                }
                body.appendChild(catFrag);
            });

            var detalle = review.datos_faltantes_detalle || {};
            var missingCats = Array.isArray(detalle.missing_categories)
                ? detalle.missing_categories
                : [];
            if (missingCats.length) {
                appendAlert(
                    body,
                    'Faltan categorías obligatorias: ' + missingCats.join(', ') + '.',
                    'warning',
                    'status'
                );
            }

            var orphanIssues = (Array.isArray(review.issues) ? review.issues : []).filter(
                function (issue) {
                    return issue && issue.id && !renderedIssueIds[issue.id];
                }
            );
            if (orphanIssues.length) {
                var issSec = tpl('tpl-capture-issues-section');
                if (issSec) {
                    var issSlot = issSec.querySelector('[data-slot="issues"]');
                    orphanIssues.forEach(function (issue) {
                        var iss = renderIssueBlock(issue);
                        if (iss && issSlot) issSlot.appendChild(iss);
                    });
                    body.appendChild(issSec);
                }
            }
        }

        var openProblems = renderOpenProblems(review.open_problems);
        if (openProblems) body.appendChild(openProblems);

        return {
            node: panelFrag,
            stagedIds: stagedIds,
        };
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
