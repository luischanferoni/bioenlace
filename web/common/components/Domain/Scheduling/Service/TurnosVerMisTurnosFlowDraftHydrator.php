<?php

namespace common\components\Domain\Scheduling\Service;

use Yii;

/**
 * Resumen textual de turnos del paciente para el asistente (todos los canales).
 * Declared as draft_hydrator.handler: scheduling.turnos_listar_como_paciente
 *
 * Options YAML: `alcance` (pendientes|pasados), `limit` (1–20; pasados tope 10),
 * `solo_ultimo`, `filtro_oferta_desde_mensaje` (cruza la mención con `servicios`, no con una profesión).
 */
final class TurnosVerMisTurnosFlowDraftHydrator
{
    /** @var list<string> */
    private const OFFER_STOPWORDS = [
        'ultima', 'último', 'ultimo', 'vez', 'que', 'fui', 'fue', 'al', 'a', 'la', 'el', 'los', 'las',
        'cuando', 'cuándo', 'cita', 'citas', 'turno', 'turnos', 'mi', 'mis', 'en', 'de', 'del',
        'me', 'decime', 'mostrar', 'mostrame', 'ver', 'para', 'con', 'un', 'una',
    ];

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $alcance = trim((string) ($options['alcance'] ?? 'pendientes'));
        if (!in_array($alcance, ['pendientes', 'pasados'], true)) {
            $alcance = 'pendientes';
        }
        $limit = isset($options['limit']) ? (int) $options['limit'] : ($alcance === 'pasados' ? 5 : 10);
        $maxLimit = $alcance === 'pasados' ? 10 : 20;
        $limit = max(1, min($maxLimit, $limit));
        $filtroOferta = !empty($options['filtro_oferta_desde_mensaje']);
        $soloUltimo = !empty($options['solo_ultimo']);

        $params = [
            'alcance' => $alcance,
            'limit' => $limit,
            'offset' => 0,
        ];

        try {
            $data = (new TurnoPacienteListadoService())->list($params);
        } catch (\Throwable $e) {
            Yii::warning(
                'TurnosVerMisTurnosFlowDraftHydrator: ' . $e->getMessage(),
                'asistente'
            );
            $draft['assistant_text'] = 'No pude consultar tus turnos ahora. Probá de nuevo en unos minutos.';
            $body['draft'] = $draft;

            return;
        }

        $turnos = isset($data['turnos']) && is_array($data['turnos']) ? $data['turnos'] : [];
        $offerLabel = '';
        $offerMatched = false;
        if ($filtroOferta) {
            $content = isset($body['content']) ? trim((string) $body['content']) : '';
            $offerId = self::resolveOfertaIdFromContent($content, $draft);
            if ($offerId > 0) {
                $filtered = self::filterByServicioId($turnos, $offerId);
                $draft['id_servicio_asignado'] = (string) $offerId;
                $offerMatched = true;
                $turnos = $filtered;
                if ($filtered !== []) {
                    $offerLabel = trim((string) ($filtered[0]['servicio'] ?? ''));
                }
            }
        }
        if ($soloUltimo && $turnos !== []) {
            $turnos = [reset($turnos)];
        }

        $lines = [];
        foreach ($turnos as $t) {
            if (!is_array($t)) {
                continue;
            }
            $label = self::formatTurnoLine($t);
            if ($label !== '') {
                $lines[] = '• ' . $label;
            }
        }

        $esPasados = $alcance === 'pasados';
        if ($filtroOferta || $soloUltimo) {
            $draft['assistant_text'] = self::headerUltimoEnOferta(
                $offerMatched,
                $offerLabel,
                $lines
            );
        } elseif ($lines === []) {
            $draft['assistant_text'] = $esPasados
                ? 'No tenés turnos anteriores para mostrar.'
                : 'No tenés turnos pendientes por ahora.';
        } else {
            $total = isset($data['total']) ? (int) $data['total'] : count($lines);
            if ($esPasados) {
                $header = $total > count($lines)
                    ? 'Tus turnos anteriores (últimos ' . count($lines) . ' de ' . $total . '):'
                    : 'Tus turnos anteriores:';
            } else {
                $header = $total > count($lines)
                    ? 'Tus próximos turnos (mostrando ' . count($lines) . ' de ' . $total . '):'
                    : 'Tus próximos turnos:';
            }
            $draft['assistant_text'] = $header . "\n" . implode("\n", $lines);
        }

        $body['draft'] = $draft;
    }

    /**
     * @param list<array<string, mixed>> $turnos
     * @return list<array<string, mixed>>
     */
    public static function filterByServicioId(array $turnos, int $idServicio): array
    {
        if ($idServicio <= 0) {
            return [];
        }
        $out = [];
        foreach ($turnos as $t) {
            if (!is_array($t)) {
                continue;
            }
            if ((int) ($t['id_servicio_asignado'] ?? 0) === $idServicio) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function contentOfferTerms(string $content): array
    {
        $folded = mb_strtolower(trim($content), 'UTF-8');
        $folded = strtr($folded, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        if ($folded === '') {
            return [];
        }
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $folded) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if (mb_strlen($p, 'UTF-8') < 4) {
                continue;
            }
            if (in_array($p, self::OFFER_STOPWORDS, true)) {
                continue;
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<string> $lines
     */
    public static function headerUltimoEnOferta(bool $offerMatched, string $offerLabel, array $lines): string
    {
        if ($lines === []) {
            if ($offerMatched && $offerLabel !== '') {
                return 'No encuentro un turno anterior en ' . $offerLabel . '.';
            }

            return 'No encuentro un turno anterior para esa mención. Si no aparece en el listado, puede que no haya quedado registrado en la app.';
        }
        $body = implode("\n", $lines);
        if ($offerMatched) {
            $donde = $offerLabel !== '' ? $offerLabel : 'esa oferta';

            return 'La última vez en ' . $donde . ":\n" . $body;
        }

        return "No pude cruzar esa mención con una oferta del centro. Tus últimos turnos:\n" . $body;
    }

    /**
     * @param array<string, mixed> $draft
     */
    private static function resolveOfertaIdFromContent(string $content, array $draft): int
    {
        $fromDraft = (int) ($draft['id_servicio_asignado'] ?? 0);
        if ($fromDraft > 0) {
            return $fromDraft;
        }
        $terms = self::contentOfferTerms($content);
        if ($terms === []) {
            return 0;
        }
        try {
            $rows = \common\models\Servicio::find()->orderBy(['nombre' => SORT_ASC])->all();
        } catch (\Throwable $e) {
            return 0;
        }
        $candidates = [];
        foreach ($rows as $s) {
            $nombre = trim((string) $s->nombre);
            if ($nombre === '') {
                continue;
            }
            $candidates[] = [
                'id' => (string) (int) $s->id_servicio,
                'nombre' => $nombre,
            ];
            foreach (\common\models\Servicio::getSearchTermsForNombre($nombre) as $term) {
                $candidates[] = [
                    'id' => (string) (int) $s->id_servicio,
                    'nombre' => $term,
                ];
            }
        }
        $match = \common\components\Platform\Assistant\Service\HintEntityMatcher::match(
            $terms,
            $candidates,
            'nombre'
        );
        if ($match === null) {
            return 0;
        }

        return (int) $match['id'];
    }

    /**
     * @param array<string, mixed> $t
     */
    private static function formatTurnoLine(array $t): string
    {
        $fecha = isset($t['fecha']) ? (string) $t['fecha'] : '';
        $hora = isset($t['hora']) ? (string) $t['hora'] : '';
        $svc = isset($t['servicio']) ? trim((string) $t['servicio']) : '';
        $prof = isset($t['profesional']) ? trim((string) $t['profesional']) : '';

        $fechaAmigable = $fecha !== '' ? TurnoSlotOfferUiPresenter::friendlyDayHeading($fecha) : '';
        $horaCorta = self::formatHoraCorta($hora);
        $cuando = trim($fechaAmigable . ($horaCorta !== '' ? ' · ' . $horaCorta : ''));

        $parts = array_values(array_filter([$cuando, $svc, $prof], static fn ($p) => $p !== ''));
        $label = implode(' · ', $parts);
        if ($label === '') {
            $id = isset($t['id']) ? (int) $t['id'] : 0;

            return $id > 0 ? 'Turno #' . $id : '';
        }
        if (!empty($t['en_resolucion'])) {
            $label = 'En reubicación: ' . $label;
        }

        return $label;
    }

    private static function formatHoraCorta(string $hora): string
    {
        $hora = trim($hora);
        if ($hora === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}:\d{2})/', $hora, $m)) {
            return $m[1];
        }

        return $hora;
    }
}
