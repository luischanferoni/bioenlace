<div class="persona-index">
    <ul>
    <?php
    $rows = is_array($coberturas['data'] ?? null) ? $coberturas['data'] : [];
    if ($rows === []) {
        echo '<li>No se encontraron coberturas.</li>';
    } else {
        foreach ($rows as $cobertura) {
            if (!is_array($cobertura)) {
                continue;
            }
            $label = $cobertura['cobertura'] ?? null;
            echo '<li>' . \yii\helpers\Html::encode(
                ((int) ($coberturas['statusCode'] ?? 0) === 200 && is_string($label) && $label !== '')
                    ? $label
                    : 'No se encontraron coberturas.'
            ) . '</li>';
        }
    }
    ?>
    </ul>
</div>
