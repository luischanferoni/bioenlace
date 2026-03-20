<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use common\models\Cirugia;

/** @var yii\web\View $this */
/** @var int $idEfector */

$this->title = 'Nueva cirugía';
$this->params['breadcrumbs'][] = ['label' => 'Agenda quirúrgica', 'url' => ['index', 'id_efector' => $idEfector]];
$this->params['breadcrumbs'][] = $this->title;

$base = rtrim(Url::to(['/'], true), '/');
$idEfectorJson = Json::encode($idEfector);
$apiSalas = $base . '/api/v1/quirofano/salas?id_efector=' . (int) $idEfector;
$apiIntake = $base . '/api/v1/quirofano/cirugias/intake';
$apiCirugias = $base . '/api/v1/quirofano/cirugias';
$indexUrlJson = Json::encode(Url::to(['index', 'id_efector' => $idEfector]));
?>
<div class="quirofano-create-cirugia">
    <h1><?= Html::encode($this->title) ?></h1>

    <div id="qc-msg" class="alert d-none" role="alert"></div>

    <div class="panel panel-default">
        <div class="panel-heading">Texto libre (opcional)</div>
        <div class="panel-body">
            <textarea id="qc-texto" class="form-control" rows="3" placeholder="Dictado o nota breve…"></textarea>
            <p class="help-block">Genera un borrador con IA; revisá y completá los campos antes de guardar.</p>
            <button type="button" id="qc-intake" class="btn btn-default">Interpretar texto</button>
        </div>
    </div>

    <div class="form-group">
        <label for="qc-sala">Sala</label>
        <select id="qc-sala" class="form-control"><option value="">— Cargando… —</option></select>
    </div>
    <div class="form-group">
        <label for="qc-persona">ID paciente (id_persona)</label>
        <input type="number" id="qc-persona" class="form-control" />
    </div>
    <div class="form-group">
        <label for="qc-internacion">ID internación (opcional)</label>
        <input type="number" id="qc-internacion" class="form-control" />
    </div>
    <div class="form-group">
        <label for="qc-practica">ID práctica (opcional)</label>
        <input type="number" id="qc-practica" class="form-control" />
    </div>
    <div class="form-group">
        <label for="qc-proc">Procedimiento</label>
        <textarea id="qc-proc" class="form-control" rows="2"></textarea>
    </div>
    <div class="form-group">
        <label for="qc-obs">Observaciones</label>
        <textarea id="qc-obs" class="form-control" rows="2"></textarea>
    </div>
    <div class="form-group">
        <label for="qc-estado">Estado (opcional; por defecto lista de espera)</label>
        <select id="qc-estado" class="form-control">
            <option value="">— Por defecto —</option>
            <?php foreach (Cirugia::ESTADOS as $k => $label): ?>
                <option value="<?= Html::encode($k) ?>"><?= Html::encode($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="qc-ini">Inicio</label>
        <input type="text" id="qc-ini" class="form-control" placeholder="YYYY-MM-DD HH:MM:SS" />
    </div>
    <div class="form-group">
        <label for="qc-fin">Fin estimado</label>
        <input type="text" id="qc-fin" class="form-control" placeholder="YYYY-MM-DD HH:MM:SS" />
    </div>

    <div class="form-group">
        <button type="button" id="qc-guardar" class="btn btn-success">Guardar</button>
        <?= Html::a('Volver', ['index', 'id_efector' => $idEfector], ['class' => 'btn btn-default']) ?>
    </div>
</div>

<?php
$apiSalasJson = Json::encode($apiSalas);
$apiIntakeJson = Json::encode($apiIntake);
$apiCirugiasJson = Json::encode($apiCirugias);
$js = <<<JS
(function () {
    var idEfector = {$idEfectorJson};
    var apiSalas = {$apiSalasJson};
    var apiIntake = {$apiIntakeJson};
    var apiPost = {$apiCirugiasJson};
    var indexUrl = {$indexUrlJson};

    function msg(html, kind) {
        var el = document.getElementById('qc-msg');
        el.className = 'alert ' + (kind || 'alert-info');
        el.innerHTML = html;
        el.classList.remove('d-none');
    }

    function headers(json) {
        var h = { 'Accept': 'application/json' };
        if (json) {
            h['Content-Type'] = 'application/json';
        }
        if (typeof window.getBioenlaceApiClientHeaders === 'function') {
            h = Object.assign({}, window.getBioenlaceApiClientHeaders(h));
        }
        if (window.apiAuthToken) {
            h['Authorization'] = 'Bearer ' + window.apiAuthToken;
        }
        return h;
    }

    function loadSalas() {
        return fetch(apiSalas, { credentials: 'same-origin', headers: headers(false) })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) {
                var sel = document.getElementById('qc-sala');
                sel.innerHTML = '<option value=\"\">— Sala —</option>';
                if (!x.ok || !x.j || !x.j.success || !Array.isArray(x.j.data)) {
                    msg('No se pudieron cargar las salas.', 'alert-danger');
                    return;
                }
                x.j.data.forEach(function (s) {
                    var o = document.createElement('option');
                    o.value = String(s.id);
                    o.textContent = s.nombre + (s.codigo ? ' (' + s.codigo + ')' : '');
                    sel.appendChild(o);
                });
            });
    }

    loadSalas().catch(function () { msg('Error de red al cargar salas.', 'alert-danger'); });

    document.getElementById('qc-intake').addEventListener('click', function () {
        var texto = (document.getElementById('qc-texto').value || '').trim();
        msg('Analizando…', 'alert-info');
        fetch(apiIntake, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers(true),
            body: JSON.stringify({ id_efector: idEfector, texto: texto })
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
            if (!x.ok || !x.j || !x.j.success || !x.j.data) {
                msg((x.j && x.j.message) ? x.j.message : 'No se pudo interpretar el texto.', 'alert-danger');
                return;
            }
            var d = x.j.data;
            var draft = d.draft || {};
            if (draft.id_quirofano_sala) {
                document.getElementById('qc-sala').value = String(draft.id_quirofano_sala);
            }
            if (draft.id_persona) {
                document.getElementById('qc-persona').value = String(draft.id_persona);
            }
            if (draft.id_seg_nivel_internacion) {
                document.getElementById('qc-internacion').value = String(draft.id_seg_nivel_internacion);
            }
            if (draft.id_practica) {
                document.getElementById('qc-practica').value = String(draft.id_practica);
            }
            if (draft.procedimiento_descripcion) {
                document.getElementById('qc-proc').value = draft.procedimiento_descripcion;
            }
            if (draft.observaciones) {
                document.getElementById('qc-obs').value = draft.observaciones;
            }
            if (draft.estado) {
                document.getElementById('qc-estado').value = draft.estado;
            }
            if (draft.fecha_hora_inicio) {
                document.getElementById('qc-ini').value = draft.fecha_hora_inicio;
            }
            if (draft.fecha_hora_fin_estimada) {
                document.getElementById('qc-fin').value = draft.fecha_hora_fin_estimada;
            }
            var miss = d.missing_fields || [];
            var hints = d.hints || {};
            var parts = [];
            if (miss.length) {
                parts.push('Faltan: <strong>' + miss.join(', ') + '</strong>');
            }
            if (hints.documento_paciente) {
                parts.push('DNI sugerido: ' + hints.documento_paciente + ' (buscar id_persona en el sistema)');
            }
            if (hints.sala_nombre || hints.sala_codigo) {
                parts.push('Sala no resuelta (nombre/código en texto: '
                    + (hints.sala_nombre || '') + ' / ' + (hints.sala_codigo || '') + ')');
            }
            msg(parts.length ? parts.join('<br/>') : 'Borrador aplicado. Revisá y guardá.', miss.length ? 'alert-warning' : 'alert-success');
        }).catch(function () {
            msg('Error de red en intake.', 'alert-danger');
        });
    });

    document.getElementById('qc-guardar').addEventListener('click', function () {
        var sala = parseInt(document.getElementById('qc-sala').value, 10);
        var persona = parseInt(document.getElementById('qc-persona').value, 10);
        var ini = (document.getElementById('qc-ini').value || '').trim();
        var fin = (document.getElementById('qc-fin').value || '').trim();
        if (!sala || !persona || !ini || !fin) {
            msg('Sala, paciente, inicio y fin estimado son obligatorios.', 'alert-warning');
            return;
        }
        var body = {
            id_quirofano_sala: sala,
            id_persona: persona,
            fecha_hora_inicio: ini,
            fecha_hora_fin_estimada: fin
        };
        var intern = document.getElementById('qc-internacion').value;
        if (intern !== '') {
            body.id_seg_nivel_internacion = parseInt(intern, 10);
        }
        var pr = document.getElementById('qc-practica').value;
        if (pr !== '') {
            body.id_practica = parseInt(pr, 10);
        }
        var proc = (document.getElementById('qc-proc').value || '').trim();
        if (proc) {
            body.procedimiento_descripcion = proc;
        }
        var obs = (document.getElementById('qc-obs').value || '').trim();
        if (obs) {
            body.observaciones = obs;
        }
        var est = (document.getElementById('qc-estado').value || '').trim();
        if (est) {
            body.estado = est;
        }
        fetch(apiPost, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers(true),
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
            if (!x.ok || !x.j || !x.j.success) {
                var err = (x.j && x.j.message) ? x.j.message : 'No se pudo crear.';
                if (x.j && x.j.errors) {
                    err += ' ' + JSON.stringify(x.j.errors);
                }
                msg(err, 'alert-danger');
                return;
            }
            window.location.href = indexUrl;
        }).catch(function () {
            msg('Error de red al guardar.', 'alert-danger');
        });
    });
})();
JS;
$this->registerJs($js, \yii\web\View::POS_READY);
