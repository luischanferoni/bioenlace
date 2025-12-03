<?php

namespace common\traits;

use yii\base\InvalidConfigException;
use yii\db\Expression;

trait SoftDeleteDateTimeTrait {
    
    /**
     * Soft deletes a record
     * 
     * @return boolean Returns TRUE if the record was soft deleted, FALSE otherwise
     */
    public function softDelete()
    {
        $this->{self::getDeletedAtAttribute()} = new Expression('NOW()');
        $this->deleted_by = \Yii::$app->user->id;
        $ret = $this->save(false, [self::getDeletedAtAttribute(), 'deleted_by']);
        $this->afterSoftDelete();
        return $ret;
    }

    /**
     * This method is invoked after deleting a record.
     *
     * You may override this method to do postprocessing after the record is soft deleted.
     */
    public function afterSoftDelete()
    {
        // Default implementation
    }

    /**
     * Override default delete() behavior in order to soft delete
     *
     * @return boolean Returns TRUE if the record was soft deleted, FALSE otherwise
     */
    public function delete()
    {
    	return $this->softDelete();
    }

    /**
     * Performs a hard delete (deletes from the database)
     *
     * @return boolean Returns TRUE on success, FALSE on failure
     */
    public function hardDelete()
    {
        $condition = $this->getOldPrimaryKey(true);
        $command = static::getDb()->createCommand();
        
        if ($condition != null) {
            $command->delete(static::tableName(), $condition);
            #$command->getRawSql();die;
            return $command->execute();
        }             
    }

    /**
     * Override default deleteAll() behavior in order to soft delete
     *
     * @return boolean Returns TRUE if the record was soft deleted, FALSE otherwise
     */    
    public static function deleteAll($condition = null, $params = [])
    {
        // para resguardarse que no haga un softdelete de TODA la tabla
        if ($condition == null || $condition == '' || $condition == false) {
            return false;
        }
        $command = static::getDb()->createCommand();
        
        $command->update(static::tableName(), 
                    ['deleted_at' => new Expression('NOW()'), 'deleted_by' => \Yii::$app->user->id], 
                    $condition, $params);        
        //echo $command->getRawSql();die;
        return $command->execute();
    }

    /**
     * Performs a hard delete (deletes from the database)
     *
     * @return boolean Returns TRUE if the record was soft deleted, FALSE otherwise
     */    
    public static function hardDeleteAll($condition = null, $params = [])
    {
        // para resguardarse que no haga un softdelete de TODA la tabla
        if ($condition == null || $condition == '' || $condition == false) {
            return false;
        }
                
        return parent::deleteAll($condition, $params) !== false;
    }

    /**
     * Override default find() behavior in order to find only active
     *
     * @return boolean Returns TRUE if the record was soft deleted, FALSE otherwise
     */
    public static function find()
    {
    	return parent::find();
        //->where(self::tableName() . '.' . self::getDeletedAtAttribute() . ' IS NULL');
    }

    /**
     * Finds where deleted_at IS NULL
     *
     * @return \yii\db\ActiveQueryInterface The newly created yii\db\ActiveQueryInterface instance.
     * @throws InvalidConfigException
     */
	static public function findActive() 
    {
		return parent::find()->where(self::tableName() . '.' . self::getDeletedAtAttribute() . ' IS NULL');
	}

    /**
     * Finds where deleted_at IS NOT NULL
     *
     * @return \yii\db\ActiveQueryInterface The newly created yii\db\ActiveQueryInterface instance.
     * @throws InvalidConfigException
     */
	static public function findInactive() 
    {
		return parent::find()->andWhere(self::tableName() . '.' . self::getDeletedAtAttribute() . ' IS NOT NULL');
	}

    /**
     * Finds records regardless of deleted_at
     *
     * @return \yii\db\ActiveQueryInterface The newly created yii\db\ActiveQueryInterface instance.
     * @throws InvalidConfigException
     */
	static public function findBoth() 
    {
		return parent::find();
	}

    /**
     * Gets the deleted_at attribute name
     *
     * @throws \Exception
     */
	static public function getDeletedAtAttribute() 
    {
		return 'deleted_at';
	}

	/**
	 * Restores a soft-deleted attribute
     *
     * @return boolean Returns TRUE if the record could be restored, FALSE otherwise
	 */
	public function restore()
	{
        $this->{self::getDeletedAtAttribute()} = null;
        $this->deleted_by = null;
        return $this->save(false, [self::getDeletedAtAttribute(), 'deleted_by']);
	}


}