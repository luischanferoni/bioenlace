<?php

namespace common\components\Platform\Core\DataAccess;

/**
 * Especificación tipada de una consulta staff (entrada al autorizador y servicios de dominio).
 *
 * @phpstan-type FilterMap array<string, mixed>
 */
final class QuerySpec
{
    public string $metricId;

    /** @var FilterMap */
    public array $filters = [];

    /** @var list<string> */
    public array $aggregations = [];

    public ?int $requestedIdEfector = null;

    public ?int $requestedIdPersona = null;

    public ?string $requestedDocumento = null;

    /** @var string|null null = usar default del plan YAML */
    public $outputMode = null;

    /** @var int|null */
    public $limit = null;

    /** @var string|null clave en aggregation.definitions */
    public $aggregationKey = null;

    /**
     * @param FilterMap $filters
     * @param list<string> $aggregations
     */
    public function __construct(
        string $metricId,
        array $filters = [],
        array $aggregations = [],
        ?int $requestedIdEfector = null,
        ?int $requestedIdPersona = null,
        ?string $requestedDocumento = null,
        ?string $outputMode = null,
        ?int $limit = null,
        ?string $aggregationKey = null
    ) {
        $this->metricId = trim($metricId);
        $this->filters = $filters;
        $this->aggregations = $aggregations;
        $this->requestedIdEfector = $requestedIdEfector;
        $this->requestedIdPersona = $requestedIdPersona;
        $this->requestedDocumento = $requestedDocumento !== null ? trim($requestedDocumento) : null;
        $this->outputMode = $outputMode !== null && $outputMode !== ''
            ? QueryOutputMode::normalize($outputMode)
            : null;
        $this->limit = $limit !== null && $limit > 0 ? $limit : null;
        $this->aggregationKey = $aggregationKey !== null && trim($aggregationKey) !== ''
            ? trim($aggregationKey)
            : null;
    }

    /**
     * @param array<string, mixed> $params query/post API, draft asistente, /api/info o /api/listar
     */
    public static function fromParams(string $metricId, array $params): self
    {
        $metricId = trim($metricId);
        $idEfector = isset($params['id_efector']) ? (int) $params['id_efector'] : 0;
        if ($idEfector <= 0 && isset($params['idEfector'])) {
            $idEfector = (int) $params['idEfector'];
        }

        $reserved = [
            'metric_id', 'metricId', 'id_efector', 'idEfector', 'action_id',
            'output_mode', 'limit', 'aggregation', 'aggregation_key',
        ];

        $filters = [];
        if (isset($params['filters']) && is_array($params['filters'])) {
            $filters = $params['filters'];
        } else {
            foreach ($params as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                if (in_array($key, $reserved, true)) {
                    continue;
                }
                if ($value === null || $value === '') {
                    continue;
                }
                $filters[$key] = $value;
            }
        }

        $aggregations = [];
        if (isset($params['aggregations']) && is_array($params['aggregations'])) {
            $aggregations = array_values(array_filter(array_map('strval', $params['aggregations'])));
        }

        $outputMode = isset($params['output_mode']) ? (string) $params['output_mode'] : null;
        $limit = isset($params['limit']) ? (int) $params['limit'] : null;
        $aggregationKey = trim((string) ($params['aggregation_key'] ?? $params['aggregation'] ?? ''));
        if ($aggregationKey === '' && $aggregations !== []) {
            $aggregationKey = (string) $aggregations[0];
        }

        return new self(
            $metricId,
            $filters,
            $aggregations,
            $idEfector > 0 ? $idEfector : null,
            isset($params['id_persona']) ? (int) $params['id_persona'] : null,
            isset($params['documento']) ? (string) $params['documento'] : null,
            $outputMode,
            $limit > 0 ? $limit : null,
            $aggregationKey !== '' ? $aggregationKey : null
        );
    }

    /**
     * @param array<string, mixed> $params query/post API o draft del asistente
     */
    public static function profesionalesConteoEfectorFromParams(array $params): self
    {
        return self::fromParams('profesionales_conteo_efector', $params);
    }
}
