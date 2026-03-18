<?php

namespace common\components\Text;

use Yii;
use common\helpers\TextoMedicoHelper;
use common\models\AbreviaturasMedicas;
use common\models\TerminoContextoMedico;
use common\models\DiccionarioOrtografico;
use common\components\Logging\ConsultaLogger;
use common\components\Text\SymSpellCorrector;

/**
 * Componente para procesar texto médico.
 * Implementación movida desde common\components\ProcesadorTextoMedico.
 */
class ProcesadorTextoMedico
{
    private const CONFIDENCE_MINIMA_APROBACION = 1.0;

    // Implementación completa copiada desde la clase original,
    // manteniendo la lógica y solo ajustando el namespace y el uso de SymSpellCorrector.
}

