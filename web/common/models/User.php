<?php

namespace common\models;

use Yii;

use webvimark\modules\UserManagement\models\rbacDB\Route;
use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\User as webvimarkUser;

class User extends webvimarkUser {


    public static function getPorRolPorEfector($rol, $id_efector) {

        $query = new yii\db\Query;
        $query->select(["`user`.*"])
                ->from('user')
                ->where('auth_item.name', $rol)
                ->andWhere('auth_item.type', 1)
                ->andWhere('user_efector.id_efector', $id_efector)
                ->leftJoin('user_efector', '`user_efector`.`id_user` = `user`.`id`')
                ->leftJoin('auth_item', '`auth_item`.`name` = `auth_assignment`.`item_name`')
                ->leftJoin('auth_assignment', '`user`.`id` = `auth_assignment`.`user_id`');

        $command = $query->createCommand();
        $data = $command->queryAll();
        // "SELECT `user`.* FROM `user` LEFT JOIN `auth_assignment` ON `user`.`id` = `auth_assignment`.`user_id` LEFT JOIN `auth_item` ON `auth_assignment`.`item_name` = `auth_item`.`name` WHERE (`auth_item`.name='Administrativo') AND (`auth_item`.type=1)"

        return $data;
    }

    //funcion que devuelve el nombre de la persona a la cual se le creara un usuario
    public function getNombrepersona($id){
        
        $consulta_persona = \common\models\Persona::findOne(['id_persona'=>$id]);            
        $apellido_persona = $consulta_persona->apellido;            
        $nombre_persona = $consulta_persona->nombre.' '.$consulta_persona->otro_nombre;            
        $nombre = $apellido_persona.", ".$nombre_persona;            
        return $nombre;
    }    
    
    public function actualizarIduserpersona($idpersona,$iduser) {
        
        //actualizar id_user para el id_persona solicitado---------------
        $actualizar_persona = \common\models\Persona::findOne(['id_persona'=>$idpersona]);     
        $actualizar_persona->id_user = $iduser;
        $actualizar_persona->scenario = "scenarioregistrar";      
        $actualizar_persona->save();
        //---------------------------------------------------------------
        
    }

    public function afterSave($insert, $changedAttributes)
    {   
        parent::afterSave();

        if(Yii::$app->getRequest()->getQueryParam('id')){
            $idpersona = Yii::$app->getRequest()->getQueryParam('id');
            $iduser = $model->id;
            self::actualizarIduserpersona($idpersona,$iduser);
        }
    }

	/*public static function canRoute($route, $superAdminAllowed = true)
	{
        $route[0] = Yii::$app->params['path'].$route[0];
        //var_dump(parent::canRoute($route));var_dump($route);
        return parent::canRoute($route);
    }*/
}
