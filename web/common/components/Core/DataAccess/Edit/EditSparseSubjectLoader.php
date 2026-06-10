<?php

namespace common\components\Core\DataAccess\Edit;

use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\EditSurfaceAuthorizationService;
use common\components\Core\DataAccess\PermissionContext;
use common\components\Organization\Service\Efectores\OrganizationEfectorAccess;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;

/**
 * Resuelve sujeto de edición y snapshot de valores actuales (prefill).
 */
final class EditSparseSubjectLoader
{
    private AttributeGroupCatalog $catalog;
    private EditSurfaceAuthorizationService $authorization;

    public function __construct(
        ?AttributeGroupCatalog $catalog = null,
        ?EditSurfaceAuthorizationService $authorization = null
    ) {
        $this->catalog = $catalog ?? new AttributeGroupCatalog();
        $this->authorization = $authorization ?? new EditSurfaceAuthorizationService($this->catalog);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{
     *   context: array<string, int|string>,
     *   label: string,
     *   baseline: array<string, array<string, string>>
     * }
     */
    public function load(string $surfaceId, array $params, PermissionContext $ctx): array
    {
        $surface = $this->catalog->getEditSurface($surfaceId);
        if ($surface === null) {
            throw new \InvalidArgumentException('Superficie de edición desconocida.');
        }

        if (!$this->authorization->userCanAccessEditSurface($ctx, $surfaceId, $params)) {
            throw new \yii\web\ForbiddenHttpException('No tenés permiso para editar esa categoría de datos.');
        }

        $idEfector = OrganizationEfectorAccess::resolveIdEfector(
            isset($params['id_efector']) ? (int) $params['id_efector'] : null
        );
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('id_efector es requerido para editar.');
        }

        $resolver = $surface['subject_resolver'] ?? null;
        $needsSubject = is_array($resolver) && trim((string) ($resolver['metric_id'] ?? '')) !== '';

        $pes = null;
        $idPersona = (int) ($params['id_persona'] ?? 0);

        if ($needsSubject) {
            $pes = $this->resolvePes($params, $idEfector);
            if ($pes === null) {
                throw new \InvalidArgumentException(
                    'Elegí el registro a editar (id_profesional_efector_servicio o id_persona + id_servicio).'
                );
            }
            $idPersona = (int) $pes->id_persona;
        }

        $label = $this->buildSubjectLabel($idPersona, $pes);
        $baseline = $this->buildBaseline($surfaceId, $surface, $params, $ctx, $idPersona);

        $context = [
            'surface_id' => $surfaceId,
            'id_efector' => (string) $idEfector,
            'id_persona' => (string) $idPersona,
        ];
        if ($pes instanceof ProfesionalEfectorServicio) {
            $context['id_profesional_efector_servicio'] = (string) $pes->id;
            $context['id_servicio'] = (string) $pes->id_servicio;
        } elseif (isset($params['id_servicio']) && (int) $params['id_servicio'] > 0) {
            $context['id_servicio'] = (string) (int) $params['id_servicio'];
        }

        return [
            'context' => $context,
            'label' => $label,
            'baseline' => $baseline,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolvePes(array $params, int $idEfector): ?ProfesionalEfectorServicio
    {
        $pesId = (int) ($params['id_profesional_efector_servicio'] ?? 0);
        if ($pesId > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $pesId, 'deleted_at' => null]);
            if ($pes === null || (int) $pes->id_efector !== $idEfector) {
                throw new \InvalidArgumentException('Asignación profesional no encontrada en este efector.');
            }

            return $pes;
        }

        $idPersona = (int) ($params['id_persona'] ?? 0);
        $idServicio = (int) ($params['id_servicio'] ?? 0);
        if ($idPersona <= 0 || $idServicio <= 0) {
            return null;
        }

        $pes = ProfesionalEfectorServicio::findOne([
            'id_persona' => $idPersona,
            'id_servicio' => $idServicio,
            'id_efector' => $idEfector,
            'deleted_at' => null,
        ]);

        return $pes instanceof ProfesionalEfectorServicio ? $pes : null;
    }

    private function buildSubjectLabel(int $idPersona, ?ProfesionalEfectorServicio $pes): string
    {
        if ($idPersona <= 0) {
            return 'Registro';
        }

        $persona = Persona::findOne($idPersona);
        if ($persona === null) {
            return 'Persona #' . $idPersona;
        }

        $nombre = trim((string) $persona->nombre);
        $apellido = trim((string) $persona->apellido);
        $label = trim($apellido . ', ' . $nombre, ', ');

        return $label !== '' ? $label : ('Persona #' . $idPersona);
    }

    /**
     * @param array<string, mixed> $surface
     * @param array<string, mixed> $params
     * @return array<string, array<string, string>>
     */
    private function buildBaseline(
        string $surfaceId,
        array $surface,
        array $params,
        PermissionContext $ctx,
        int $idPersona
    ): array {
        $aspects = $surface['aspects'] ?? [];
        if (!is_array($aspects)) {
            return [];
        }

        $persona = $idPersona > 0 ? Persona::findOne($idPersona) : null;
        $baseline = [];

        foreach ($aspects as $aspectId => $def) {
            if (!is_string($aspectId) || !is_array($def)) {
                continue;
            }
            if (!$this->authorization->userCanAccessAspect($ctx, $surfaceId, $aspectId, $params)) {
                continue;
            }

            $kind = trim((string) ($def['kind'] ?? 'scalar_group'));
            if ($kind !== 'scalar_group') {
                continue;
            }

            $group = trim((string) ($def['attribute_group'] ?? ''));
            if ($group === 'Persona.identidad_basica' && $persona instanceof Persona) {
                $fields = $def['fields'] ?? [];
                if (!is_array($fields)) {
                    $fields = [];
                }
                $snapshot = [];
                foreach ($fields as $fieldName) {
                    $key = trim((string) $fieldName);
                    if ($key === '' || !$persona->hasAttribute($key)) {
                        continue;
                    }
                    $snapshot[$key] = trim((string) $persona->getAttribute($key));
                }
                if ($snapshot !== []) {
                    $baseline[$aspectId] = $snapshot;
                }
            }
        }

        return $baseline;
    }
}
