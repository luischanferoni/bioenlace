<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;

/** @var yii\web\View $this */
/** @var int $idEfector */

$this->title = 'Nueva sala';
$this->params['breadcrumbs'][] = ['label' => 'Salas', 'url' => ['salas', 'id_efector' => $idEfector]];
$this->params['breadcrumbs'][] = $this->title;

$apiSalas = Url::to(['/api/v1/quirofano/salas'], true);
$idEfectorJson = Json::encode($idEfector);
$salasUrlJson = Json::encode(Url::to(['salas', 'id_efector' => $idEfector]));
$apiSalasJson = Json::encode($apiSalas);
?>
<div class="quirofano-create-sala">
    <h1><?= Html::encode($this->title) ?></h1>

    <div id="qf-msg" class="alert d-none" role="alert"></div>

    <div class="form-group">
        <label for="qf-nombre">Nombre</label>
        <input type="text" id="qf-nombre" class="form-control" maxlength="255" />
    </div>
    <div class="form-group">
        <label for="qf-codigo">Código</label>
        <input type="text" id="qf-codigo" class="form-control" maxlength="64" />
    </div>
    <div class="checkbox">
        <label><input type="checkbox" id="qf-activo" checked /> Activa</label>
    </div>

    <div class="form-group">
        <button type="button" id="qf-guardar" class="btn btn-success">Guardar</button>
        <?= Html::a('Volver', ['salas', 'id_efector' => $idEfector], ['class' => 'btn btn-default']) ?>
    </div>
</div>

<?php
$js = <<<JS
(function () {
    var idEfector = {$idEfectorJson};
    var apiUrl = {$apiSalasJson};

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

    document.getElementById('qf-guardar').addEventListener('click', function () {
        var nombre = (document.getElementById('qf-nombre').value || '').trim();
        if (!nombre) {
            msg('Indique el nombre de la sala.', 'alert-warning');
            return;
        }
        var body = {
            id_efector: idEfector,
            nombre: nombre,
            codigo: (document.getElementById('qf-codigo').value || '').trim() || null,
            activo: document.getElementById('qf-activo').checked
        };
        fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers(),
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (x) {
            if (!x.ok || !x.j || !x.j.success) {
                msg((x.j && x.j.message) ? x.j.message : 'No se pudo crear la sala.', 'alert-danger');
                return;
            }
            window.location.href = {$salasUrlJson};
        }).catch(function () {
            msg('Error de red al guardar.', 'alert-danger');
        });
    });
})();
JS;
$this->registerJs($js, \yii\web\View::POS_READY);

</think>


<｜tool▁calls▁begin｜><｜tool▁call▁begin｜>
StrReplace