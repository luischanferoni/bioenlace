<?php
namespace common\models;

use Yii;
use yii\db\Query;
use yii\rbac\Item;
use yii\rbac\DbManager;

class SisseDbManager extends DbManager
{
    public $rolesEspeciales;
    public $efectorAssignmentTable;

    /**
     * {@inheritdoc}
     */
    public function getPermissionsByUser($userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return [];
        }

        $directPermission = $this->getDirectPermissionsByUser($userId);

        $inheritedPermission = $this->getInheritedPermissionsByUser($userId);

        return array_merge($directPermission, $inheritedPermission);
    }

    /**
     * Returns all permissions that are directly assigned to user.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Permission[] all direct permissions that the user has. The array is indexed by the permission names.
     * @since 2.0.7
     */
    protected function getDirectPermissionsByUser($userId)
    {
        $permissions = [];

        // Sumamos los roles permisos recurso humano
        $id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        if ($id_rr_hh !== 0 || $id_rr_hh !== null) {
            $permissions = $this->getDirectPermissionsByRrhh();
        }

        // TODO: desde esta linea hasta el final en un futuro debería de quedar parent::getInheritedPermissionsByUser()
        if (!isset(Yii::$app->authManager->rolesEspeciales) || count(Yii::$app->authManager->rolesEspeciales) == 0) {
            return [];
        }
        foreach (Yii::$app->authManager->rolesEspeciales as $rolEspecial) {
            $rolesEspeciales[] = 'a.item_name LIKE "%'.$rolEspecial.'%"';
        }

        $query = (new Query())->select('b.*')
            ->from(['a' => $this->assignmentTable, 'b' => $this->itemTable])
            ->where('{{a}}.[[item_name]]={{b}}.[[name]]')
            ->andWhere(['a.user_id' => (string) $userId])
            ->andWhere(implode(' OR ', $rolesEspeciales))
            ->andWhere(['b.type' => Item::TYPE_PERMISSION]);

        foreach ($query->all($this->db) as $row) {
            $permissions[$row['name']] = $this->populateItem($row);
        }

        return $permissions;
    }

    protected function getDirectPermissionsByRrhh()
    {
        $query = (new Query())->select('b.*')
            ->from(['a' => $this->efectorAssignmentTable, 'b' => $this->itemTable, 'c' => 'servicios'])
            ->where('{{c}}.[[item_name]]={{b}}.[[name]]')
            ->andWhere('{{a}}.id_servicio={{c}}.[[id_servicio]]')
            ->andWhere(['{{a}}.id_rr_hh' => Yii::$app->user->getIdRecursoHumano()])
            ->andWhere(['{{b}}.type' => Item::TYPE_PERMISSION])
            ->andWhere('{{a}}.deleted_at IS NULL')
            ->groupBy(['{{c}}.[[item_name]]']);
       
        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            $permissions[$row['name']] = $this->populateItem($row);
        }

        return $permissions;
    }
    /**
     * Returns all permissions that the user inherits from the roles assigned to him.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return Permission[] all inherited permissions that the user has. The array is indexed by the permission names.
     * @since 2.0.7
     */
    protected function getInheritedPermissionsByUser($userId)
    {
        $permissions = [];

        // Sumamos los permisos por recurso humano
        $id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        if ($id_rr_hh !== 0 || $id_rr_hh !== null) {
            $permissions = $this->getInheritedPermissionsByRrhh();
        }

        // TODO: desde esta linea hasta el final en un futuro debería de quedar parent::getInheritedPermissionsByUser()
        if (!isset(Yii::$app->authManager->rolesEspeciales) || count(Yii::$app->authManager->rolesEspeciales) == 0) {
            return [];
        }
        foreach (Yii::$app->authManager->rolesEspeciales as $rolEspecial) {
            $rolesEspeciales[] = 'item_name LIKE "%'.$rolEspecial.'%"';
        }

        $query = (new Query())->select('item_name')
            ->from($this->assignmentTable)
            ->where(['user_id' => (string) $userId])
            ->andWhere(implode(' OR ', $rolesEspeciales));
        
        $childrenList = $this->getChildrenList();
        $result = [];
        foreach ($query->column($this->db) as $roleName) {            
            $this->getChildrenRecursive($roleName, $childrenList, $result);
        }

        if (empty($result)) {
            return $permissions;
        }
        
        $query = (new Query())->from($this->itemTable)->where([
            'type' => Item::TYPE_PERMISSION,
            'name' => array_keys($result),
        ]);
        
        foreach ($query->all($this->db) as $row) {
            $permissions[$row['name']] = $this->populateItem($row);
        }
        
        return $permissions;
    }

    protected function getInheritedPermissionsByRrhh()
    {
        $query = (new Query())->select('c.item_name')
            ->from(['a' => $this->efectorAssignmentTable, 'c' => 'servicios'])
            ->where(['a.id_rr_hh' => Yii::$app->user->getIdRecursoHumano()])
            ->andWhere('{{a}}.id_servicio={{c}}.[[id_servicio]]') 
            ->andWhere('{{a}}.deleted_at IS NULL')           
            ->groupBy(['{{c}}.[[item_name]]']);
        //echo $query->createCommand()->getRawSql();die;
        $childrenList = $this->getChildrenList();
        $result = [];
        foreach ($query->column($this->db) as $roleName) {            
            $this->getChildrenRecursive($roleName, $childrenList, $result);
        }

        if (empty($result)) {
            return [];
        }
        
        $query = (new Query())->from($this->itemTable)->where([
            'type' => Item::TYPE_PERMISSION,
            'name' => array_keys($result),
        ]);
        $permissions = [];
        foreach ($query->all($this->db) as $row) {
            $permissions[$row['name']] = $this->populateItem($row);
        }

        return $permissions;        
    }

    /**
     * {@inheritdoc}
     * The roles returned by this method include the roles assigned via [[$defaultRoles]].
     */
    public function getRolesByUser($userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return [];
        }

        $roles = $this->getDefaultRoleInstances();

        // Sumamos los roles por recurso humano, roles para un usuario+efector
        $id_rr_hh = Yii::$app->user->getIdRecursoHumano();
        if ($id_rr_hh !== 0 || $id_rr_hh !== null) {
            $roles = array_merge($roles, $this->getRolesByRrhh());
        }

        // TODO: desde esta linea hasta el final en un futuro debería de quedar parent::getRolesByUser()        
        if (!isset(Yii::$app->authManager->rolesEspeciales) || count(Yii::$app->authManager->rolesEspeciales) == 0) {
            // Agregar rol "paciente" a todos los usuarios logueados
            $this->agregarRolPaciente($roles);
            return $roles;
        }
        foreach (Yii::$app->authManager->rolesEspeciales as $rolEspecial) {
            $rolesEspeciales[] = 'a.item_name LIKE "%'.$rolEspecial.'%"';
        }

        $query = (new Query())->select('b.*')
            ->from(['a' => $this->assignmentTable, 'b' => $this->itemTable])
            ->where('{{a}}.[[item_name]]={{b}}.[[name]]')
            ->andWhere(['a.user_id' => (string) $userId])
            ->andWhere(implode(' OR ', $rolesEspeciales))
            ->andWhere(['b.type' => Item::TYPE_ROLE]);
        //echo $query->createCommand()->getRawSql();die;
        foreach ($query->all($this->db) as $row) {
            $roles[$row['name']] = $this->populateItem($row);
        }

        // Agregar rol "paciente" a todos los usuarios logueados
        $this->agregarRolPaciente($roles);

        return $roles;
    }

    /**
     * Agregar el rol "paciente" a la lista de roles si no existe ya
     * Lanza una excepción si el rol "paciente" no existe en la base de datos
     * @param array $roles Array de roles por referencia
     * @throws \Exception Si el rol "paciente" no existe en la base de datos
     */
    protected function agregarRolPaciente(&$roles)
    {
        // Verificar si el rol "paciente" ya existe en los roles
        if (isset($roles['paciente'])) {
            return;
        }

        // Buscar el rol "paciente" en la base de datos
        $query = (new Query())
            ->from($this->itemTable)
            ->where(['name' => 'paciente', 'type' => Item::TYPE_ROLE])
            ->one($this->db);

        if (!$query) {
            throw new \Exception('El rol "paciente" no existe en la base de datos. Debe crearse antes de usar el sistema.');
        }

        // Si el rol existe, agregarlo a la lista
        $roles['paciente'] = $this->populateItem($query);
    }

    /**
     * Existen roles que no dependen del Efector asignado.
     * Que trabajan con todos o con ninguno
     * Revisar web/config rolesEspeciales
     * TODO: Eventualmente todos los usuarios estarían asignados a roles con prefijos (especiales),
     * el resto de roles son asignados a recursos humanos
     */    
    public function getRolesByRrhh()
    {
        $roles = [];
        $query = (new Query())->select('b.*')
            ->from(['a' => $this->efectorAssignmentTable, 'b' => $this->itemTable, 'c' => 'servicios'])            
            ->where('{{c}}.[[item_name]]={{b}}.[[name]]')
            ->andWhere('{{a}}.id_servicio={{c}}.[[id_servicio]]')
            ->andWhere('{{a}}.deleted_at IS NULL')
            ->andWhere(['a.id_rr_hh' => Yii::$app->user->getIdRecursoHumano()])
            ->andWhere(['b.type' => Item::TYPE_ROLE])
            ->groupBy(['{{c}}.[[item_name]]']);

        foreach ($query->all($this->db) as $row) {
            $roles[$row['name']] = $this->populateItem($row);
        }

        return $roles;
    }
}