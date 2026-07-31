<?php

namespace common\components\Domain\Clinical\Access;

use common\components\Domain\Terminology\Snomed\SnowstormClient;
use Yii;

/**
 * Membership acto ∈ ECL vía Snowstorm. Fail-soft → false.
 */
final class SnowstormActoEclMembership implements ActoEclMembershipInterface
{
    private ?SnowstormClient $client;

    public function __construct(?SnowstormClient $client = null)
    {
        $this->client = $client;
    }

    public function matches(string $code, string $system, string $ecl): bool
    {
        $code = trim($code);
        $system = trim($system);
        $ecl = trim($ecl);
        if ($code === '' || $ecl === '') {
            return false;
        }
        if ($system !== '' && $system !== CodingSystems::SNOMED) {
            // ECL SNOMED no aplica a LOINC / FHIR value sets en este slice.
            return false;
        }

        try {
            $client = $this->client ?? $this->resolveClient();
            if ($client === null) {
                return false;
            }
            // Intersección: el concepto y la capacidad ECL.
            $constrained = '(' . $code . ') AND (' . $ecl . ')';
            $result = $client->busquedaFiltradaEcl($code, $constrained, [], 1);
            $items = $result['items'] ?? [];
            if (!is_array($items) || $items === []) {
                return false;
            }
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = trim((string) ($item['conceptId'] ?? ''));
                if ($id === $code) {
                    return true;
                }
            }

            return true; // hubo hit bajo el ECL compuesto
        } catch (\Throwable $e) {
            Yii::warning(
                'SnowstormActoEclMembership: ' . $e->getMessage(),
                __METHOD__
            );

            return false;
        }
    }

    private function resolveClient(): ?SnowstormClient
    {
        if (Yii::$app === null) {
            return null;
        }
        try {
            if (Yii::$app->has('snowstorm')) {
                $c = Yii::$app->get('snowstorm');
                if ($c instanceof SnowstormClient) {
                    return $c;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        try {
            return new SnowstormClient();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
