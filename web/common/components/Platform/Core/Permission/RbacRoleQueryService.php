<?php

namespace common\components\Platform\Core\Permission;

use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\rbac\AuthRole;
use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\rbac\Role;

/**
 * Consultas de roles RBAC sin webvimark {@see \webvimark\modules\UserManagement\models\rbacDB\Role}.
 */
final class RbacRoleQueryService
{
    /**
     * @return array<string, Role>
     */
    public static function getUserRoles(int $userId): array
    {
        if ($userId <= 0 || !Yii::$app->has('authManager')) {
            return [];
        }

        return Yii::$app->authManager->getRolesByUser($userId);
    }

    /**
     * Roles PES / efector disponibles para asignar.
     *
     * @return list<AuthRole>
     */
    public static function getAvailableRoles(bool $showAll = false, bool $asArray = false)
    {
        $auth = Yii::$app->authManager;
        $prefixes = [];
        if (isset($auth->rolesEspeciales) && is_array($auth->rolesEspeciales)) {
            $prefixes = $auth->rolesEspeciales;
        }
        if ($prefixes === []) {
            return $asArray ? [] : [];
        }

        $query = AuthRole::find();
        if (Yii::$app->user->isSuperadmin || $showAll) {
            $or = ['or'];
            foreach ($prefixes as $prefix) {
                $or[] = ['like', 'name', (string) $prefix, false];
            }
            $query->andWhere($or);
        } else {
            $sessionRoles = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROLES, []);
            if (!is_array($sessionRoles) || $sessionRoles === []) {
                return $asArray ? [] : [];
            }
            $query->andWhere(['name' => $sessionRoles]);
        }

        $result = $query->all();

        return $asArray ? ArrayHelper::map($result, 'name', 'name') : $result;
    }

    /**
     * Roles clínicos por efector vía PES activos de la persona del usuario (solo lectura).
     * No usa el contexto de sesión del admin: lista **todos** los efectores del sujeto.
     *
     * @return list<array{
     *   id_pes: int,
     *   id_efector: int,
     *   efector_nombre: string,
     *   id_servicio: int,
     *   servicio_nombre: string,
     *   role_name: string
     * }>
     */
    public static function listPesRolesByEfectorForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $idPersona = (int) (new Query())
            ->select('id_persona')
            ->from(Persona::tableName())
            ->where(['id_user' => $userId])
            ->scalar();
        if ($idPersona <= 0) {
            return [];
        }

        $rows = (new Query())
            ->select([
                'id_pes' => 'pes.id',
                'id_efector' => 'pes.id_efector',
                'efector_nombre' => 'e.nombre',
                'id_servicio' => 'pes.id_servicio',
                'servicio_nombre' => 's.nombre',
                'role_name' => 's.item_name',
            ])
            ->from(['pes' => ProfesionalEfectorServicio::tableName()])
            ->innerJoin(['s' => '{{%servicios}}'], 's.id_servicio = pes.id_servicio')
            ->innerJoin(['e' => '{{%efectores}}'], 'e.id_efector = pes.id_efector')
            ->where(['pes.id_persona' => $idPersona])
            ->andWhere(['pes.deleted_at' => null])
            ->orderBy([
                'e.nombre' => SORT_ASC,
                's.nombre' => SORT_ASC,
            ])
            ->all();

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $roleName = trim((string) ($row['role_name'] ?? ''));
            $out[] = [
                'id_pes' => (int) ($row['id_pes'] ?? 0),
                'id_efector' => (int) ($row['id_efector'] ?? 0),
                'efector_nombre' => trim((string) ($row['efector_nombre'] ?? '')),
                'id_servicio' => (int) ($row['id_servicio'] ?? 0),
                'servicio_nombre' => trim((string) ($row['servicio_nombre'] ?? '')),
                'role_name' => $roleName !== '' ? $roleName : '(sin item_name)',
            ];
        }

        return $out;
    }

    /**
     * Todos los roles RBAC para filtro de listados admin (p. ej. usuarios).
     * No usar para asignación PES: ahí sigue {@see getAvailableRoles()}.
     *
     * @return array<string, string> name => etiqueta (description o name)
     */
    public static function getAllRolesForFilter(): array
    {
        $roles = AuthRole::find()->orderBy(['name' => SORT_ASC])->all();
        $map = [];
        foreach ($roles as $role) {
            $label = trim((string) ($role->description ?? ''));
            $map[$role->name] = $label !== '' ? $label : $role->name;
        }

        return $map;
    }
}
