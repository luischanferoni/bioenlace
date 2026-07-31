<?php

namespace common\components\Domain\Clinical\Access;

use common\components\Domain\Terminology\Snomed\CodificadorSnomedIA;
use common\components\Domain\Terminology\Snomed\SnowstormClient;
use Yii;

/**
 * Codifica Acto display → SNOMED (caché local → CodificadorSnomedIA → Snowstorm profile).
 * Fail-soft: sin tumbar el pedido si terminología no responde.
 */
final class PedidoAtencionActoCodingService implements PedidoAtencionActoCoderInterface
{
    private ?CodificadorSnomedIA $codificador;
    private ?SnowstormClient $snowstorm;
    private LineaActoCatalogInterface $catalog;

    public function __construct(
        ?CodificadorSnomedIA $codificador = null,
        ?SnowstormClient $snowstorm = null,
        ?LineaActoCatalogInterface $catalog = null
    ) {
        $this->codificador = $codificador;
        $this->snowstorm = $snowstorm;
        $this->catalog = $catalog ?? new InMemoryLineaActoCatalog();
    }

    public static function defaultService(): self
    {
        return new self(null, null, CompositeLineaActoCatalog::defaultCatalog());
    }

    public function code(string $display, ?string $modo = null): array
    {
        $display = trim($display);
        if ($display === '') {
            return ['resolved' => null, 'candidates' => []];
        }

        $local = $this->matchLocalActos($display);
        if ($local['resolved'] !== null) {
            return $local;
        }
        if (count($local['candidates']) === 1) {
            $only = $local['candidates'][0];

            return [
                'resolved' => $only,
                'candidates' => [],
            ];
        }
        if (count($local['candidates']) > 1) {
            return ['resolved' => null, 'candidates' => $local['candidates']];
        }

        $cfg = PedidoAtencionMetadata::actoCodingConfig();
        try {
            $codificador = $this->codificador ?? $this->resolveCodificador();
            if ($codificador !== null) {
                $hit = $codificador->buscarCodigoSnomed($display, $cfg['snomed_category']);
                if (is_array($hit) && !empty($hit['conceptId'])) {
                    return [
                        'resolved' => [
                            'code' => (string) $hit['conceptId'],
                            'system' => CodingSystems::SNOMED,
                            'display' => trim((string) ($hit['term'] ?? $display)) ?: $display,
                        ],
                        'candidates' => [],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Yii::warning('PedidoAtencionActoCodingService codificador: ' . $e->getMessage(), __METHOD__);
        }

        $candidates = $this->searchSnowstormCandidates($display, $cfg['snowstorm_profile'], $cfg['candidate_limit']);
        if (count($candidates) === 1) {
            return ['resolved' => $candidates[0], 'candidates' => []];
        }

        return ['resolved' => null, 'candidates' => $candidates];
    }

    /**
     * @return array{resolved: array{code: string, system: string, display: string}|null, candidates: list<array{code: string, system: string, display: string}>}
     */
    private function matchLocalActos(string $display): array
    {
        $norm = mb_strtolower($display, 'UTF-8');
        $exact = [];
        $partial = [];
        foreach ($this->catalog->listActos() as $acto) {
            $code = trim((string) ($acto['code'] ?? ''));
            $system = trim((string) ($acto['system'] ?? ''));
            $label = trim((string) ($acto['display'] ?? ''));
            if ($code === '' || $system === '') {
                continue;
            }
            if (!CodingSystems::isAllowed($system, PedidoAtencionMetadata::allowedSystems())) {
                continue;
            }
            $row = [
                'code' => $code,
                'system' => $system,
                'display' => $label !== '' ? $label : $code,
            ];
            $labelNorm = mb_strtolower($label, 'UTF-8');
            if ($labelNorm === $norm || $code === $display) {
                $exact[] = $row;
            } elseif ($labelNorm !== '' && (str_contains($labelNorm, $norm) || str_contains($norm, $labelNorm))) {
                $partial[] = $row;
            }
        }
        if (count($exact) === 1) {
            return ['resolved' => $exact[0], 'candidates' => []];
        }
        if (count($exact) > 1) {
            return ['resolved' => null, 'candidates' => $exact];
        }
        if (count($partial) === 1) {
            return ['resolved' => $partial[0], 'candidates' => []];
        }
        if (count($partial) > 1) {
            return ['resolved' => null, 'candidates' => $partial];
        }

        return ['resolved' => null, 'candidates' => []];
    }

    /**
     * @return list<array{code: string, system: string, display: string}>
     */
    private function searchSnowstormCandidates(string $display, string $profile, int $limit): array
    {
        try {
            $client = $this->snowstorm ?? $this->resolveSnowstorm();
            if ($client === null) {
                return [];
            }
            $results = $client->searchByProfile($profile, $display, $limit);
            if (!is_array($results)) {
                return [];
            }
            $out = [];
            foreach ($results as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = trim((string) ($item['id'] ?? $item['conceptId'] ?? ''));
                $text = trim((string) ($item['text'] ?? $item['pt']['term'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $out[] = [
                    'code' => $id,
                    'system' => CodingSystems::SNOMED,
                    'display' => $text !== '' ? $text : $id,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Yii::warning('PedidoAtencionActoCodingService snowstorm: ' . $e->getMessage(), __METHOD__);

            return [];
        }
    }

    private function resolveCodificador(): ?CodificadorSnomedIA
    {
        try {
            return new CodificadorSnomedIA();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveSnowstorm(): ?SnowstormClient
    {
        try {
            if (Yii::$app !== null && Yii::$app->has('snowstorm')) {
                $c = Yii::$app->get('snowstorm');
                if ($c instanceof SnowstormClient) {
                    return $c;
                }
            }

            return new SnowstormClient();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
