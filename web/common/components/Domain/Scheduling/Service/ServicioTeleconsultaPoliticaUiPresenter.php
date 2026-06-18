<?php

namespace common\components\Domain\Scheduling\Service;

/**
 * Enriquece UI JSON de política de teleconsulta con opciones dinámicas y resumen.
 */
final class ServicioTeleconsultaPoliticaUiPresenter
{
    /**
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function apply(array $ui, array $params, int $idEfector): array
    {
        $svc = new ServicioTeleconsultaPoliticaService();
        $catalog = new ServicioTeleconsultaPoliticaCatalogService();
        $resumen = $svc->resumenEfector($idEfector);

        $resumenTexto = $this->buildResumenTexto($resumen, $catalog);
        $ui['values'] = is_array($ui['values'] ?? null) ? $ui['values'] : [];
        $ui['values']['resumen_texto'] = $resumenTexto;

        $servicioOptions = $svc->opcionesServiciosUi($idEfector);
        $politicaOptions = array_map(static function (array $row): array {
            return [
                'value' => $row['value'],
                'label' => $row['label'],
            ];
        }, $catalog->opcionesPolitica());

        $blocks = $ui['blocks'] ?? [];
        if (!is_array($blocks)) {
            return $ui;
        }

        foreach ($blocks as &$block) {
            if (!is_array($block) || ($block['kind'] ?? '') !== 'fields') {
                continue;
            }
            $fields = $block['fields'] ?? [];
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as &$field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = (string) ($field['name'] ?? '');
                if ($name === 'id_servicio') {
                    $field['options'] = $servicioOptions;
                }
                if ($name === 'teleconsulta_politica') {
                    $field['options'] = $politicaOptions;
                }
            }
            unset($field);
            $block['fields'] = $fields;
        }
        unset($block);

        $ui['blocks'] = $blocks;
        $ui['data'] = [
            'resumen_efector' => $resumen,
            'casos_opciones' => $svc->opcionesCasosTriageUi(),
        ];

        return $ui;
    }

    /**
     * @param array<string, mixed> $resumen
     */
    private function buildResumenTexto(array $resumen, ServicioTeleconsultaPoliticaCatalogService $catalog): string
    {
        $kpi = $catalog->kpiEfector();
        $parts = [];
        $sug = (int) ($resumen['presencial_insight_sugerido'] ?? 0);
        if ($sug > 0) {
            $line = $kpi['label_presencial_remoto'] . ': ' . $sug;
            $pct = $resumen['pct_sugerido'] ?? null;
            if ($pct !== null) {
                $line .= ' (' . $pct . $kpi['label_pct'] . ')';
            }
            $parts[] = $line;
        }
        $parts[] = $kpi['label_servicios_con_video'] . ': '
            . (int) ($resumen['servicios_con_teleconsulta'] ?? 0)
            . ' / ' . (int) ($resumen['servicios_total'] ?? 0);

        return implode("\n", $parts);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function valoresDesdeServicio(array $params, int $idServicio): array
    {
        if ($idServicio <= 0) {
            return $params;
        }
        $servicio = \common\models\Servicio::findOne($idServicio);
        if ($servicio === null) {
            return $params;
        }
        $params['id_servicio'] = (string) $idServicio;
        $params['teleconsulta_politica'] = (string) ($servicio->teleconsulta_politica
            ?: \common\models\Servicio::TELECONSULTA_POLITICA_NINGUNA);
        $codes = \common\models\ServicioTeleconsultaCaso::listCodigosPorServicio($idServicio);
        $params['caso_codigos_text'] = $codes !== [] ? implode("\n", $codes) : '';

        return $params;
    }
}
