<?php
namespace common\traits;
use Yii;


trait PersistentParametersTrait {
    /*
     * Retornar array de Key => default_value
     */
    protected function definePersistentParameters() {
        return [];
    }
    
    /*
     * Toma los valores guardados en session y combina
     * con valores nuevos que vengan en peticion get.
     * Luego guarda nuevamente en session.
     * Significa que los parametros se pueden cambiar solo 
     * con peticiones get.
     */
    protected function configurePersistentParameters(){
        # get default values
        $defined_params = $this->definePersistentParameters();
        $session_params = $defined_params;
        
        # load data from session if exists
        $session_key = static::class . '_persistent_params';
        $session_obj = Yii::$app->session->get($session_key);
        if(null !== $session_obj) {
            $session_params = unserialize($session_obj);
        }
        
        # update values from request
        $params = [];
        foreach ($defined_params as $key => $default_value) {
            $value = Yii::$app->request->get($key, null);
            if(null !== $value) {
                $params[$key] = $value;
            }
        }
        $params = array_merge($session_params, $params);
        
        # store again in session
        Yii::$app->session->set($session_key, serialize($params));
        
        return $params;
    }
    
    public function getPersistentParameters() {
        $parameters = $this->configurePersistentParameters();
        return $parameters;
    }
    
    public function getPersistentParameter(string $name, $default = null)
    {
        $parameters = $this->getPersistentParameters();

        return $parameters[$name] ?? $default;
    }

}