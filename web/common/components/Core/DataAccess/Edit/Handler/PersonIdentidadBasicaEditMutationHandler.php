<?php

namespace common\components\Core\DataAccess\Edit\Handler;

use common\components\Core\DataAccess\Edit\EditMutationHandlerInterface;
use common\components\Core\DataAccess\PermissionContext;
use common\components\Person\Service\PersonaIdentidadBasicaUpdateService;

final class PersonIdentidadBasicaEditMutationHandler implements EditMutationHandlerInterface
{
    private const GROUP = 'Persona.identidad_basica';

    private const FIELD_LABELS = [
        'nombre' => 'Nombre',
        'apellido' => 'Apellido',
        'otro_nombre' => 'Otro nombre',
        'otro_apellido' => 'Otro apellido',
    ];

    private PersonaIdentidadBasicaUpdateService $updateService;

    public function __construct(?PersonaIdentidadBasicaUpdateService $updateService = null)
    {
        $this->updateService = $updateService ?? new PersonaIdentidadBasicaUpdateService();
    }

    public function supports(string $attributeGroup): bool
    {
        return $attributeGroup === self::GROUP;
    }

    public function apply(
        string $attributeGroup,
        array $changes,
        array $subjectContext,
        PermissionContext $ctx
    ): array {
        $idPersona = (int) ($subjectContext['id_persona'] ?? 0);
        $result = $this->updateService->update($idPersona, $changes);

        $applied = [];
        foreach ($result['after'] as $field => $afterValue) {
            $applied[] = [
                'field' => $field,
                'label' => self::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field)),
                'before' => (string) ($result['before'][$field] ?? ''),
                'after' => $afterValue,
            ];
        }

        return $applied;
    }
}
