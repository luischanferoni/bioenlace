<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Persona - Curvas Crecimiento';
$this->params['breadcrumbs'][] = $this->title;


?>
<div class="persona-curvas">
  <!--div class="card">
    <div class="card-header">
      <div class="header-title">
        <h4 class="card-title d-inline">Paciente</h4>
      </div>
    </div>
    <div class="card-body">
      <dl class="row">
        <dt class="col-sm-3 pe-0">Apellido:</dt>
        <dd class="col-sm-9 pe-0"><?= $persona->apellido ?></dd>
        <dt class="col-sm-3 pe-0">Nombre:</dt>
        <dd class="col-sm-9 pe-0"><?= $persona->nombre . ', ' . $persona->otro_nombre ?></dd>
        <dt class="col-sm-3 pe-0">Edad:</dt>
        <dd class="col-sm-9 pe-0"><?= $persona->edadCrecimiento ?></dd>
        <dt class="col-sm-3 pe-0">Sexo:</dt>
        <dd class="col-sm-9 pe-0"><?= $persona->sexoCrecimiento ?></dd>
      </dl>
    </div>
  </div-->
    
  <div class="card">
    <div class="card-header">
        Peso para la edad
    </div>
    <div class="card-body">
        <div id="peso_edad"></div>
    </div>
  </div>
    
  <div class="card">
    <div class="card-header">
        Talla para la edad
    </div>
    <div class="card-body">
        <div id="talla_edad"></div>
    </div>
  </div>
    
  <div class="card">
    <div class="card-header">
        Perímetro Cefálico para la edad
    </div>
    <div class="card-body">
        <div id="pcefalico_edad"></div>
    </div>
  </div>
  <?php if($persona->edadCrecimiento > 1) { ?>
  <div class="card">
    <div class="card-header">
        Indice de Masa Corporal para la edad
    </div>
    <div class="card-body">
        <div id="imc_edad"></div>
    </div>
  </div>
  <?php } ?>
</div>
<script>
    <?php $tlist = []?>
    <?php foreach([1, 2, 3, 4, 5, 6, 7] as $tnum): ?>
        <?php
        $tracename = 'peso_trace'.$tnum;
        $tlist[] = $tracename;
        ?>
        var <?= $tracename?> = {
            x: <?= $peso_pc_data['edad_y']?>,
            y: <?= $peso_pc_data['P'.$tnum] ?>,
            name: '<?= $peso_labels['P'.$tnum] ?>'
        }
    <?php endforeach; ?>;
    var tpersona_peso = {
        x: <?= $datos_crecimiento['edad_atencion_y']?>,
        y: <?= $datos_crecimiento['peso']?>,
        mode: 'lines+markers',
        name: 'Atenciones',
        line: {
            dash: 'dashdot',
            width: 0.5,
            color: 'black'
        }
    }
    var data_peso = [<?= implode(',', $tlist)?>, tpersona_peso];
    var layout_peso = {
        'title': 'Peso para la edad',
        displayModeBar: false,
        xaxis: {
            title: 'Edad'
        },
        yaxis: {
            title: 'Peso'
        },
        legend: {'traceorder':'reversed'}
    };
    Plotly.newPlot("peso_edad", data_peso, layout_peso)
</script>

<script>
    <?php $tlist = []?>
    <?php foreach([1, 2, 3, 4, 5, 6, 7] as $tnum): ?>
        <?php
        $tracename = 'talla_trace'.$tnum;
        $tlist[] = $tracename;
        ?>
        var <?= $tracename?> = {
            x: <?= $talla_pc_data['edad_y']?>,
            y: <?= $talla_pc_data['P'.$tnum] ?>,
            name: '<?= $talla_labels['P'.$tnum] ?>'
        }
    <?php endforeach; ?>;
    var tpersona_talla = {
        x: <?= $datos_crecimiento['edad_atencion_y']?>,
        y: <?= $datos_crecimiento['talla']?>,
        mode: 'lines+markers',
        name: 'Atenciones',
        line: {
            dash: 'dashdot',
            width: 0.5,
            color: 'black'
        }
    }
    var data_talla = [<?= implode(',', $tlist)?>, tpersona_talla];
    var layout_talla = {
        'title': 'Talla para la edad',
        displayModeBar: false,
        xaxis: {
            title: 'Edad'
        },
        yaxis: {
            title: 'Talla'
        },
        legend: {'traceorder':'reversed'}
    };
    Plotly.newPlot("talla_edad", data_talla, layout_talla)
</script>

<script>
    <?php $tlist = []?>
    <?php foreach([1, 2, 3, 4, 5, 6, 7] as $tnum): ?>
        <?php
        $tracename = 'pcef_trace'.$tnum;
        $tlist[] = $tracename;
        ?>
        var <?= $tracename?> = {
            x: <?= $pcef_pc_data['edad_y']?>,
            y: <?= $pcef_pc_data['P'.$tnum] ?>,
            name: '<?= $pcef_labels['P'.$tnum] ?>'
        }
    <?php endforeach; ?>;
    var tpersona_pcef = {
        x: <?= $datos_crecimiento['edad_atencion_y']?>,
        y: <?= $datos_crecimiento['perimetro_cefalico']?>,
        mode: 'lines+markers',
        name: 'Atenciones',
        line: {
            dash: 'dashdot',
            width: 0.5,
            color: 'black'
        }
    }
    var data_pcef = [<?= implode(',', $tlist)?>, tpersona_pcef];
    var layout_pcef = {
        'title': 'Perímetro cefalico para la edad',
        displayModeBar: false,
        xaxis: {
            title: 'Edad'
        },
        yaxis: {
            title: 'Perímetro'
        },
        legend: {'traceorder':'reversed'}
    };
    Plotly.newPlot("pcefalico_edad", data_pcef, layout_pcef)
</script>

<script>
    <?php $tlist = []?>
    <?php foreach([1, 2, 3, 4, 5, 6, 7] as $tnum): ?>
        <?php
        $tracename = 'imc_trace'.$tnum;
        $tlist[] = $tracename;
        ?>
        var <?= $tracename?> = {
            x: <?= $imc_pc_data['edad_y']?>,
            y: <?= $imc_pc_data['P'.$tnum] ?>,
            name: '<?= $imc_labels['P'.$tnum] ?>'
        }
    <?php endforeach; ?>;
    var tpersona_imc = {
        x: <?= $datos_crecimiento['edad_atencion_y']?>,
        y: <?= $datos_crecimiento['imc']?>,
        mode: 'lines+markers',
        name: 'Atenciones',
        line: {
            dash: 'dashdot',
            width: 0.5,
            color: 'black'
        }
    }
    var data_imc = [<?= implode(',', $tlist)?>, tpersona_imc];
    var layout_imc = {
        'title': 'IMC para la edad',
        displayModeBar: false,
        xaxis: {
            title: 'Edad'
        },
        yaxis: {
            title: 'IMC'
        },
        legend: {'traceorder':'reversed'}
    };
    Plotly.newPlot("imc_edad", data_imc, layout_imc)
</script>
