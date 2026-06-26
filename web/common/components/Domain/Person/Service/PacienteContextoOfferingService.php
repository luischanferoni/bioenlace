<?php

namespace common\components\Domain\Person\Service;

use common\components\Platform\Core\Product\PacienteContextoOfferingMetadata;
use common\components\Platform\Core\Service\ClientContextService;
use common\models\Efector;
use common\models\Person\PersonaPacienteContexto;
use Yii;

/**
 * Encauza oferta (efectores, intents, home) según contexto paciente persistente.
 */
final class PacienteContextoOfferingService
{
    public function shouldApplyForCurrentRequest(): bool
    {
        if (ClientContextService::shouldOmitPacienteRole()) {
            return false;
        }
        $idPersona = (int) Yii::$app->user->getIdPersona();

        return $idPersona > 0;
    }

    public function getContextOrNull(): ?PersonaPacienteContexto
    {
        if (!$this->shouldApplyForCurrentRequest()) {
            return null;
        }

        return $this->resolver()->forCurrentActor();
    }

    private function resolver(): PacienteOperativeContextResolver
    {
        return new PacienteOperativeContextResolver();
    }

    private function contextoService(): PacienteContextoService
    {
        return new PacienteContextoService();
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertOperativeContextReady(): PersonaPacienteContexto
    {
        $ctx = $this->getContextOrNull();
        if ($ctx === null) {
            throw new \InvalidArgumentException('Contexto paciente no disponible.');
        }
        if (!$ctx->puedeOperarApp()) {
            $banner = $this->contextoService()->buildBanner($ctx);
            $message = is_array($banner) ? (string) ($banner['message'] ?? '') : '';
            if ($message === '') {
                $message = PacienteContextoService::MENSAJE_VERIFICANDO;
            }
            throw new \InvalidArgumentException($message);
        }

        return $ctx;
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, string>
     */
    public function mergeEfectorFilters(array $filters): array
    {
        $ctx = $this->getContextOrNull();
        if ($ctx === null || !$ctx->puedeOperarApp()) {
            return $filters;
        }
        if ($ctx->id_provincia_contexto) {
            $filters['id_provincia'] = (string) (int) $ctx->id_provincia_contexto;
        }
        $filters['sector_salud'] = strtolower((string) $ctx->sector_salud);

        return $filters;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertEfectorPermitido(int $idEfector): void
    {
        $ctx = $this->assertOperativeContextReady();
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Centro de salud inválido.');
        }
        $efector = Efector::findOne($idEfector);
        if ($efector === null) {
            throw new \InvalidArgumentException('Centro de salud inexistente.');
        }
        if (!$this->efectorMatchesContext($efector, $ctx)) {
            throw new \InvalidArgumentException(
                'El centro de salud seleccionado no está disponible para tu provincia o sector de salud.'
            );
        }
    }

    public function efectorMatchesContext(Efector $efector, PersonaPacienteContexto $ctx): bool
    {
        if ($ctx->id_provincia_contexto) {
            $idProvinciaEfector = $this->resolveEfectorProvinciaId($efector);
            if ($idProvinciaEfector !== null && $idProvinciaEfector !== (int) $ctx->id_provincia_contexto) {
                return false;
            }
        }

        return $this->origenFinanciamientoMatchesSector(
            (string) $efector->origen_financiamiento,
            (string) $ctx->sector_salud
        );
    }

    public function isIntentAllowed(string $intentId): bool
    {
        if (!$this->shouldApplyForCurrentRequest()) {
            return true;
        }

        $ctx = $this->getContextOrNull();
        if ($ctx === null) {
            return true;
        }

        if (in_array($intentId, PacienteContextoOfferingMetadata::intentIdsRequiringOperativeContext(), true)
            && !$ctx->puedeOperarApp()) {
            return false;
        }

        $denied = PacienteContextoOfferingMetadata::deniedIntentIdsForSector((string) $ctx->sector_salud);

        return !in_array($intentId, $denied, true);
    }

    /**
     * @param list<array{id: string, provider: string, kind: string}> $definitions
     * @return list<array{id: string, provider: string, kind: string}>
     */
    public function filterHomePanelSectionDefinitions(array $definitions): array
    {
        $ctx = $this->getContextOrNull();
        if ($ctx === null || $ctx->puedeOperarApp()) {
            return $definitions;
        }

        $requires = PacienteContextoOfferingMetadata::homePanelSectionsRequiringOperativeContext();
        if ($requires === []) {
            return $definitions;
        }

        $out = [];
        foreach ($definitions as $def) {
            $id = (string) ($def['id'] ?? '');
            if ($id !== '' && in_array($id, $requires, true)) {
                continue;
            }
            $out[] = $def;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $flow
     */
    public function isFlowAllowed(array $flow): bool
    {
        $intentId = trim((string) ($flow['action_id'] ?? ''));
        if ($intentId === '') {
            return true;
        }

        return $this->isIntentAllowed($intentId);
    }

    private function resolveEfectorProvinciaId(Efector $efector): ?int
    {
        $row = Efector::find()
            ->alias('e')
            ->innerJoin(['l' => 'localidades'], 'l.id_localidad = e.id_localidad')
            ->innerJoin(['d' => 'departamentos'], 'd.id_departamento = l.id_departamento')
            ->where(['e.id_efector' => (int) $efector->id_efector])
            ->select(['d.id_provincia'])
            ->asArray()
            ->one();

        if (!is_array($row) || empty($row['id_provincia'])) {
            return null;
        }

        return (int) $row['id_provincia'];
    }

    private function origenFinanciamientoMatchesSector(string $origen, string $sectorSalud): bool
    {
        $origen = trim($origen);
        if ($origen === '') {
            return true;
        }

        $rules = PacienteContextoOfferingMetadata::origenFinanciamientoRulesForSector($sectorSalud);
        $origenLower = mb_strtolower($origen);

        foreach ($rules['exclude'] as $needle) {
            if ($needle !== '' && str_contains($origenLower, mb_strtolower($needle))) {
                return false;
            }
        }

        if ($rules['include'] === []) {
            return true;
        }

        foreach ($rules['include'] as $needle) {
            if ($needle !== '' && str_contains($origenLower, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
