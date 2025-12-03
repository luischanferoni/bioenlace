<?php
namespace common\validators;

use Yii;
use yii\validators\Validator;

/**
 * DependentFieldsValidator validate consistency of attributes values, force a value for an attribute depending the value of another.
 *
 * The value being compared with can be another attribute value
 * (specified via [[compareAttribute]])
 * And the comparison can be either [[strict]] or not.
 *
 * @author Luis Chanferoni
 * @since 1.0
 */
class DependentFieldsValidator extends Validator
{
    /**
     * @var string the name of the attribute to be compared with
     */
    public $compareAttribute;

    /**
     * @var any this would be the value of [[compareAttribute]] that force the attribute to have [[forcedValue]]
     */
    public $triggerValue;

    /**
     * @var any this would be the mandatory value of the attribute when a certain value on [[compareAttribute]] is present
     */    
    public $forcedValue;

    public function init()
    {
        parent::init();
        $this->message = 'Inconsistencia de datos con otro campo.';
    }

    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        $attrLabel = $model->getAttributeLabel($attribute);

        $compareAttribute = $this->compareAttribute;
        $compareAttrLabel = $model->getAttributeLabel($this->compareAttribute);
        $compareValue = $model->$compareAttribute;
        
        $this->message = Yii::t('yii', $attrLabel.' o '.$compareAttrLabel.' is invalido');

        if ($compareValue == $this->triggerValue) {
            if ($value != $this->forcedValue) {                
                $this->addError($model, $this->message);
            }
        }
    }

    public function clientValidateAttribute($model, $attribute, $view)
    {
        $compareAttribute = $this->compareAttribute;        

        $message = json_encode($this->message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $class_name_arr = explode("\\", get_class($model));
        //var_dump(explode("\\", get_class($model)));die;
        $class_name = end($class_name_arr);
        return <<<JS
        if ($("input[name='{$class_name}[{$compareAttribute}]']:checked").val() == $this->triggerValue) {
            if ($("input[name='{$class_name}[{$attribute}]']:checked").val() != $this->forcedValue) {
                messages.push($message);
            }
        }
JS;
    }
}