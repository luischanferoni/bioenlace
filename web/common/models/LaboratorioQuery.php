<?php

namespace common\models;

/**
 * This is the ActiveQuery class for [[Laboratorio]].
 *
 * @see Laboratorio
 */
class LaboratorioQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return Laboratorio[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return Laboratorio|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
