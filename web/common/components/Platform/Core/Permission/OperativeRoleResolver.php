<?php

namespace common\components\Platform\Core\Permission;

use common\models\Organization\ProfesionalEfectorServicio;
use Yii;

/**
 * Rol operativo del personal de salud en la sesión actual.
 *
 * Manda el servicio elegido en la sesión (`servicios.item_name` del PES): el usuario lo elige al
 * iniciar sesión y lo cambia desde el top menu. Los roles RBAC sólo se usan como valor por defecto
 * cuando todavía no hay servicio elegido (wizard post-login), con el desempate de `precedence`.
 *
 * Normalización y desempate: {@see OperationalRoleManifest} (`permission/metadata/roles.yaml`).
 */
final class OperativeRoleResolver
{
    private OperationalRoleManifest $manifest;

    public function __construct(?OperationalRoleManifest $manifest = null)
    {
        $this->manifest = $manifest ?? new OperationalRoleManifest();
    }

    /**
     * Clave canónica del rol que opera la sesión, o null si no hay rol declarado.
     */
    public function resolve(): ?string
    {
        return $this->resolveFor($this->serviceItemNameInSession(), $this->rbacRoleNames());
    }

    /**
     * Todas las claves canónicas presentes, ordenadas por precedencia (diagnóstico y QA).
     *
     * @return list<string>
     */
    public function resolveAll(): array
    {
        return $this->manifest->selectAll($this->roleNamesInContext());
    }

    /**
     * Resolución pura: el servicio en sesión gana; sin servicio, se desempata por precedencia RBAC.
     *
     * @param list<string> $rbacRoleNames
     */
    public function resolveFor(?string $serviceItemName, array $rbacRoleNames): ?string
    {
        $canonical = $this->manifest->canonicalFor((string) $serviceItemName);
        if ($canonical !== null) {
            return $canonical;
        }

        return $this->manifest->select($rbacRoleNames);
    }

    /**
     * @return list<string>
     */
    private function roleNamesInContext(): array
    {
        $names = [];
        $serviceItemName = $this->serviceItemNameInSession();
        if ($serviceItemName !== null) {
            $names[] = $serviceItemName;
        }

        return array_merge($names, $this->rbacRoleNames());
    }

    /**
     * @return list<string>
     */
    private function rbacRoleNames(): array
    {
        if (!$this->hasAuthenticatedUser()) {
            return [];
        }

        $names = [];
        foreach (array_keys(RbacRoleQueryService::getUserRoles((int) Yii::$app->user->id)) as $roleName) {
            $names[] = (string) $roleName;
        }

        return $names;
    }

    private function hasAuthenticatedUser(): bool
    {
        if (!Yii::$app->has('user')) {
            return false;
        }

        return !Yii::$app->user->isGuest && (int) Yii::$app->user->id > 0;
    }

    private function serviceItemNameInSession(): ?string
    {
        if (!$this->hasAuthenticatedUser()) {
            return null;
        }

        $user = Yii::$app->user;
        if (
            !method_exists($user, 'getIdPersona')
            || !method_exists($user, 'getIdEfector')
            || !method_exists($user, 'getServicioActual')
        ) {
            return null;
        }

        $idPersona = (int) $user->getIdPersona();
        $idEfector = (int) $user->getIdEfector();
        $idServicio = (int) $user->getServicioActual();
        if ($idPersona <= 0 || $idEfector <= 0 || $idServicio <= 0) {
            return null;
        }

        $pes = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio($idPersona, $idEfector, $idServicio);
        $itemName = $pes !== null && $pes->servicio !== null ? trim((string) $pes->servicio->item_name) : '';

        return $itemName !== '' ? $itemName : null;
    }
}
