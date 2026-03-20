<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use common\models\Cirugia;

/** @var yii\web\View $this */
/** @var int $id */

$this->title = 'Cirugía #' . (int) $id;
$this->params['breadcrumbs'][] = ['label' => 'Agenda quirúrgica', 'url' => ['index', 'id_efector' => Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector())]];
$this->params['breadcrumbs'][] = $this->title;

$base = rtrim(Url::to(['/'], true), '/');
$apiCirugia = $base . '/api/v1/quirofano/cirugias/' . (int) $id;
$idJson = Json::encode((int) $id);
$apiCirugiaJson = Json::encode($apiCirugia);
$baseJson = Json::encode($base);
$efectorFallback = (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector());
$indexUrlJson = Json::encode(Url::to(['index', 'id_efector' => $efectorFallback]));
$urlHistoriaTpl = Url::to(['/paciente/historia']);
?>
<div class="quirofano-update-cirugia">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="alert alert-info">
        El <strong>informe clínico</strong> se carga en la <strong>historia clínica</strong> del paciente
        (menú Pacientes o enlace abajo). Esta pantalla es solo para <strong>agenda</strong> (sala, horarios, estado).
    </div>

    <div id="qu-msg" class="alert d-none" role="alert"></div>
    <p id="qu-loading" class="text-muted">Cargando…</p>

    <div id="qu-form" class="d-none">
        <p class="mb-2">
            <a id="qu-link-hc" class="btn btn-outline-primary btn-sm" href="#" target="_blank" rel="noopener">Abrir historia clínica (consulta)</a>
            <?= Html::a('Ir a Pacientes', ['/site/pacientes'], ['class' => 'btn btn-link btn-sm']) ?>
        </p>

        <div class="form-group">
            <label for="qu-sala">Sala</label>
            <select id="qu-sala" class="form-control"></select>
        </div>
        <div class="form-group">
            <label for="qu-persona">ID paciente</label>
            <input type="number" id="qu-persona" class="form-control" readonly />
        </div>
        <div class="form-group">
            <label for="qu-internacion">ID internación (opcional)</label>
            <input type="number" id="qu-internacion" class="form-control" />
        </div>
        <div class="form-group">
            <label for="qu-practica">ID práctica (opcional)</label>
            <input type="number" id="qu-practica" class="form-control" />
        </div>
        <div class="form-group">
            <label for="qu-estado">Estado</label>
            <select id="qu-estado" class="form-control">
                <?php foreach (Cirugia::ESTADOS as $k => $label): ?>
                    <option value="<?= Html::encode($k) ?>"><?= Html::encode($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="qu-ini">Inicio</label>
            <input type="text" id="qu-ini" class="form-control" />
        </div>
        <div class="form-group">
            <label for="qu-fin">Fin estimado</label>
            <input type="text" id="qu-fin" class="form-control" />
        </div>

        <div class="form-group">
            <button type="button" id="qu-guardar" class="btn btn-success">Guardar agenda</button>
            <?= Html::a('Volver', ['index', 'id_efector' => $efectorFallback], ['class' => 'btn btn-default']) ?>
        </div>
    </div>
</div>

<?php
$urlHistoriaBaseJson = Json::encode($urlHistoriaTpl);
$js = <<<JS
(function () {
    var cid = {$idJson};
    var apiGet = {$apiCirugiaJson};
    var apiPatch = {$apiCirugiaJson};
    var indexUrl = {$indexUrlJson};
    var urlHistoriaBase = {$urlHistoriaBaseJson};
    var idEfectorActual = null;

    function msg(html, kind) {
        var el = document.getElementById('qu-msg');
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

    var apiRoot = {$baseJson};

    function loadSalas(idEfector) {
        var u = apiRoot + '/api/v1/quirofano/salas?id_efector=' + idEfector;
        return fetch(u, { credentials: 'same-origin', headers: headers(false) })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) {
                var sel = document.getElementById('qu-sala');
                sel.innerHTML = '';
                if (!x.ok || !x.j || !x.j.success || !Array.isArray(x.j.data)) {
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

    function setHistoriaLink(personaId) {
        var a = document.getElementById('qu-link-hc');
        if (!a || !personaId) return;
        var sep = urlHistoriaBase.indexOf('?') >= 0 ? '&' : '?';
        a.href = urlHistoriaBase + sep + 'id=' + encodeURIComponent(personaId)
            + '&parent=CIRUGIA&parent_id=' + encodeURIComponent(cid);
    }

    function applyCirugia(d) {
        document.getElementById('qu-sala').value = String(d.id_quirofano_sala);
        document.getElementById('qu-persona').value = String(d.id_persona);
        document.getElementById('qu-internacion').value = d.id_seg_nivel_internacion != null ? String(d.id_seg_nivel_internacion) : '';
        document.getElementById('qu-practica').value = d.id_practica != null ? String(d.id_practica) : '';
        document.getElementById('qu-estado').value = d.estado || '';
        document.getElementById('qu-ini').value = d.fecha_hora_inicio || '';
        document.getElementById('qu-fin').value = d.fecha_hora_fin_estimada || '';
        setHistoriaLink(d.id_persona);
    }

    fetch(apiGet, { credentials: 'same-origin', headers: headers(false) })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
            document.getElementById('qu-loading').style.display = 'none';
            if (!x.ok || !x.j || !x.j.success || !x.j.data) {
                msg((x.j && x.j.message) ? x.j.message : 'No se pudo cargar la cirugía.', 'alert-danger');
                return;
            }
            var d = x.j.data;
            idEfectorActual = d.id_efector;
            if (!idEfectorActual) {
                msg('La cirugía no tiene efector asociado.', 'alert-danger');
                return;
            }
            loadSalas(idEfectorActual).then(function () {
                applyCirugia(d);
                document.getElementById('qu-form').classList.remove('d-none');
            });
        })
        .catch(function () {
            document.getElementById('qu-loading').style.display = 'none';
            msg('Error de red al cargar.', 'alert-danger');
        });

    document.getElementById('qu-guardar').addEventListener('click', function () {
        var body = {
            id_quirofano_sala: parseInt(document.getElementById('qu-sala').value, 10),
            id_persona: parseInt(document.getElementById('qu-persona').value, 10),
            fecha_hora_inicio: (document.getElementById('qu-ini').value || '').trim(),
            fecha_hora_fin_estimada: (document.getElementById('qu-fin').value || '').trim(),
            estado: (document.getElementById('qu-estado').value || '').trim()
        };
        var intern = document.getElementById('qu-internacion').value;
        body.id_seg_nivel_internacion = intern === '' ? null : parseInt(intern, 10);
        var pr = document.getElementById('qu-practica').value;
        body.id_practica = pr === '' ? null : parseInt(pr, 10);
        fetch(apiPatch, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: headers(true),
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
            if (!x.ok || !x.j || !x.j.success) {
                var err = (x.j && x.j.message) ? x.j.message : 'No se pudo guardar.';
                if (x.j && x.j.errors) {
                    err += ' ' + JSON.stringify(x.j.errors);
                }
                msg(err, 'alert-danger');
                return;
            }
            window.location.href = indexUrl;
        }).catch(function () { msg('Error de red.', 'alert-danger'); });
    });
})();
JS;
$this->registerJs($js, \yii\web\View::POS_READY);
