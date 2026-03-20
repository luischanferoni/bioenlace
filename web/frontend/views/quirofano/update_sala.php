<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;

/** @var yii\web\View $this */
/** @var int $id */

$this->title = 'Editar sala';
$this->params['breadcrumbs'][] = ['label' => 'Salas', 'url' => ['salas', 'id_efector' => Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector())]];
$this->params['breadcrumbs'][] = $this->title;

$base = rtrim(Url::to(['/'], true), '/');
$apiOne = $base . '/api/v1/quirofano/salas/' . (int) $id;
$apiPatch = $apiOne;
$apiOneJson = Json::encode($apiOne);
$apiPatchJson = Json::encode($apiPatch);
$volverJson = Json::encode(Url::to(['salas', 'id_efector' => Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector())]));
?>
<div class="quirofano-update-sala">
    <h1><?= Html::encode($this->title) ?> #<?= (int) $id ?></h1>

    <div id="qf-msg" class="alert d-none" role="alert"></div>
    <p id="qf-loading" class="text-muted">Cargando…</p>

    <div id="qf-form" class="d-none">
        <div class="form-group">
            <label for="qf-nombre">Nombre</label>
            <input type="text" id="qf-nombre" class="form-control" maxlength="255" />
        </div>
        <div class="form-group">
            <label for="qf-codigo">Código</label>
            <input type="text" id="qf-codigo" class="form-control" maxlength="64" />
        </div>
        <div class="checkbox">
            <label><input type="checkbox" id="qf-activo" /> Activa</label>
        </div>
        <div class="form-group">
            <button type="button" id="qf-guardar" class="btn btn-success">Guardar</button>
            <?= Html::a('Volver', ['salas', 'id_efector' => (int) Yii::$app->request->get('id_efector', Yii::$app->user->getIdEfector())], ['class' => 'btn btn-default']) ?>
        </div>
    </div>
</div>

<?php
$js = <<<JS
(function () {
    var apiGet = {$apiOneJson};
    var apiPatch = {$apiPatchJson};
    var volver = {$volverJson};

    function msg(text, kind) {
        var el = document.getElementById('qf-msg');
        el.className = 'alert ' + (kind || 'alert-info');
        el.textContent = text;
        el.classList.remove('d-none');
    }

    function headers() {
        var h = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (typeof window.getBioenlaceApiClientHeaders === 'function') {
            h = Object.assign({}, window.getBioenlaceApiClientHeaders(h));
        }
        if (window.apiAuthToken) {
            h['Authorization'] = 'Bearer ' + window.apiAuthToken;
        }
        return h;
    }

    fetch(apiGet, { credentials: 'same-origin', headers: headers() })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
            document.getElementById('qf-loading').style.display = 'none';
            if (!x.ok || !x.j || !x.j.success || !x.j.data) {
                msg((x.j && x.j.message) ? x.j.message : 'No se pudo cargar la sala.', 'alert-danger');
                return;
            }
            var d = x.j.data;
            document.getElementById('qf-nombre').value = d.nombre || '';
            document.getElementById('qf-codigo').value = d.codigo || '';
            document.getElementById('qf-activo').checked = !!d.activo;
            document.getElementById('qf-form').classList.remove('d-none');
        })
        .catch(function () {
            document.getElementById('qf-loading').style.display = 'none';
            msg('Error de red al cargar.', 'alert-danger');
        });

    document.getElementById('qf-guardar').addEventListener('click', function () {
        var nombre = (document.getElementById('qf-nombre').value || '').trim();
        if (!nombre) {
            msg('Indique el nombre de la sala.', 'alert-warning');
            return;
        }
        var body = {
            nombre: nombre,
            codigo: (document.getElementById('qf-codigo').value || '').trim() || null,
            activo: document.getElementById('qf-activo').checked
        };
        fetch(apiPatch, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: headers(),
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
            if (!x.ok || !x.j || !x.j.success) {
                msg((x.j && x.j.message) ? x.j.message : 'No se pudo actualizar.', 'alert-danger');
                return;
            }
            window.location.href = volver;
        }).catch(function () {
            msg('Error de red al guardar.', 'alert-danger');
        });
    });
})();
JS;
$this->registerJs($js, \yii\web\View::POS_READY);
