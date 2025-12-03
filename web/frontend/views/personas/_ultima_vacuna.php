<?php

use yii\helpers\Html;

if ($vacuna) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-borderless">';
    echo '<tbody>';
    
    // Fila de títulos
    echo '<tr>';
    $titulos = [];
    if (isset($vacuna['nombreGeneralVacuna'])) {
        $titulos[] = 'Vacuna';
    }
    if (isset($vacuna['sniVacunaEsquemaNombre'])) {
        $titulos[] = 'Esquema';
    }
    if (isset($vacuna['sniDosisNombre'])) {
        $titulos[] = 'Dosis';
    }
    if (isset($vacuna['fechaAplicacion'])) {
        $titulos[] = 'Fecha';
    }
    if (isset($vacuna['origenNombre'])) {
        $titulos[] = 'Efector';
    }
    if (isset($vacuna['origenLocalidad']) || isset($vacuna['origenProvincia'])) {
        $titulos[] = 'Ubicación';
    }
    
    foreach ($titulos as $titulo) {
        echo '<td class="fw-bold text-muted text-start">' . $titulo . '</td>';
    }
    echo '</tr>';
    
    // Fila de valores
    echo '<tr>';
    if (isset($vacuna['nombreGeneralVacuna'])) {
        echo '<td class="text-start">' . htmlspecialchars($vacuna['nombreGeneralVacuna']) . '</td>';
    }
    if (isset($vacuna['sniVacunaEsquemaNombre'])) {
        echo '<td class="text-start">' . htmlspecialchars($vacuna['sniVacunaEsquemaNombre']) . '</td>';
    }
    if (isset($vacuna['sniDosisNombre'])) {
        echo '<td class="text-start">' . htmlspecialchars($vacuna['sniDosisNombre']) . '</td>';
    }
    if (isset($vacuna['fechaAplicacion'])) {
        echo '<td class="text-start">' . date('d/m/Y', strtotime($vacuna['fechaAplicacion'])) . '</td>';
    }
    if (isset($vacuna['origenNombre'])) {
        echo '<td class="text-start">' . htmlspecialchars($vacuna['origenNombre']) . '</td>';
    }
    if (isset($vacuna['origenLocalidad']) || isset($vacuna['origenProvincia'])) {
        $ubicacion = [];
        if (isset($vacuna['origenLocalidad'])) {
            $ubicacion[] = $vacuna['origenLocalidad'];
        }
        if (isset($vacuna['origenProvincia'])) {
            $ubicacion[] = $vacuna['origenProvincia'];
        }
        echo '<td class="text-start">' . htmlspecialchars(implode(', ', $ubicacion)) . '</td>';
    }
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo '<div class="text-muted">';
    echo '<i class="bi bi-info-circle"></i> No se encontraron vacunas registradas';
    echo '</div>';
}
?>
