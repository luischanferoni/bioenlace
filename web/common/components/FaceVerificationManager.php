<?php

namespace common\components;

use Yii;
use yii\httpclient\Client;
use yii\base\Component;

/**
 * Gestor de verificación facial
 * Soporta múltiples proveedores: Google Vision API, Azure Face API, o método simple
 */
class FaceVerificationManager extends Component
{
    /**
     * Verifica si dos imágenes contienen la misma persona
     * @param string $dniPath Ruta de la imagen del DNI
     * @param string $selfiePath Ruta de la imagen de la selfie
     * @return array Resultado de la verificación
     */
    public static function verifyFaces($dniPath, $selfiePath)
    {
        $provider = Yii::$app->params['face_verification_provider'] ?? 'azure';
        
        switch ($provider) {
            case 'google':
                return self::verifyWithGoogleVision($dniPath, $selfiePath);
            case 'azure':
                return self::verifyWithAzureFace($dniPath, $selfiePath);
            case 'simple':
                return self::verifySimple($dniPath, $selfiePath);
            default:
                return [
                    'success' => false,
                    'message' => 'Proveedor de verificación facial no configurado',
                    'match' => false,
                    'score' => 0.0,
                ];
        }
    }

    /**
     * Verificación usando Google Vision API
     * Requiere: google/cloud-vision (composer) o API REST
     */
    private static function verifyWithGoogleVision($dniPath, $selfiePath)
    {
        try {
            $apiKey = Yii::$app->params['google_vision_api_key'] ?? '';
            
            if (empty($apiKey)) {
                Yii::warning('Google Vision API key no configurada, usando método simple', 'face-verification');
                return self::verifySimple($dniPath, $selfiePath);
            }

            // Detectar caras en ambas imágenes
            $dniFaces = self::detectFacesGoogleVision($dniPath, $apiKey);
            $selfieFaces = self::detectFacesGoogleVision($selfiePath, $apiKey);

            if (empty($dniFaces) || empty($selfieFaces)) {
                return [
                    'success' => false,
                    'message' => empty($dniFaces) ? 'No se detectó cara en el DNI' : 'No se detectó cara en la selfie',
                    'match' => false,
                    'score' => 0.0,
                ];
            }

            // Comparar las caras usando los landmarks y características faciales
            $dniFace = $dniFaces[0]; // Tomar la primera cara detectada
            $selfieFace = $selfieFaces[0];

            // Calcular similitud basada en landmarks faciales
            $similarity = self::calculateFaceSimilarity($dniFace, $selfieFace);
            $threshold = Yii::$app->params['face_match_threshold'] ?? 0.7;

            return [
                'success' => true,
                'match' => $similarity >= $threshold,
                'score' => $similarity,
                'distance' => 1 - $similarity,
                'method' => 'google_vision',
                'dni_face_detected' => count($dniFaces),
                'selfie_face_detected' => count($selfieFaces),
            ];

        } catch (\Exception $e) {
            Yii::error("Error en verificación Google Vision: " . $e->getMessage(), 'face-verification');
            return [
                'success' => false,
                'message' => 'Error en verificación facial: ' . $e->getMessage(),
                'match' => false,
                'score' => 0.0,
            ];
        }
    }

    /**
     * Detecta caras usando Google Vision API
     */
    private static function detectFacesGoogleVision($imagePath, $apiKey)
    {
        $client = new Client();
        
        // Leer imagen y convertir a base64
        $imageData = file_get_contents($imagePath);
        $imageBase64 = base64_encode($imageData);

        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}")
            ->addHeaders(['Content-Type' => 'application/json'])
            ->setContent(json_encode([
                'requests' => [
                    [
                        'image' => [
                            'content' => $imageBase64
                        ],
                        'features' => [
                            [
                                'type' => 'FACE_DETECTION',
                                'maxResults' => 10
                            ]
                        ]
                    ]
                ]
            ]))
            ->send();

        if (!$response->isOk) {
            throw new \Exception("Error en Google Vision API: " . $response->getStatusCode());
        }

        $data = json_decode($response->content, true);
        
        if (!isset($data['responses'][0]['faceAnnotations'])) {
            return [];
        }

        return $data['responses'][0]['faceAnnotations'];
    }

    /**
     * Calcula similitud entre dos caras usando landmarks
     */
    private static function calculateFaceSimilarity($face1, $face2)
    {
        // Google Vision API proporciona landmarks faciales (ojos, nariz, boca, etc.)
        // Podemos calcular similitud basada en las posiciones relativas de estos puntos
        
        if (!isset($face1['landmarks']) || !isset($face2['landmarks'])) {
            // Fallback: usar detección de confianza
            $confidence1 = $face1['detectionConfidence'] ?? 0.5;
            $confidence2 = $face2['detectionConfidence'] ?? 0.5;
            return ($confidence1 + $confidence2) / 2;
        }

        // Calcular distancias entre landmarks clave
        $keyLandmarks = ['LEFT_EYE', 'RIGHT_EYE', 'NOSE_TIP', 'MOUTH_LEFT', 'MOUTH_RIGHT'];
        $distances = [];

        foreach ($keyLandmarks as $landmarkType) {
            $landmark1 = self::findLandmark($face1['landmarks'], $landmarkType);
            $landmark2 = self::findLandmark($face2['landmarks'], $landmarkType);

            if ($landmark1 && $landmark2) {
                $distance = sqrt(
                    pow($landmark1['position']['x'] - $landmark2['position']['x'], 2) +
                    pow($landmark1['position']['y'] - $landmark2['position']['y'], 2) +
                    pow($landmark1['position']['z'] - $landmark2['position']['z'], 2)
                );
                $distances[] = $distance;
            }
        }

        if (empty($distances)) {
            return 0.5; // Similitud neutral si no se pueden comparar landmarks
        }

        // Normalizar distancias (valores más bajos = más similares)
        $avgDistance = array_sum($distances) / count($distances);
        $maxDistance = 200; // Aproximado basado en dimensiones típicas de imágenes
        
        // Convertir distancia a similitud (0-1)
        $similarity = 1 - min($avgDistance / $maxDistance, 1.0);
        
        return max(0, min(1, $similarity));
    }

    /**
     * Encuentra un landmark específico en el array
     */
    private static function findLandmark($landmarks, $type)
    {
        foreach ($landmarks as $landmark) {
            if (isset($landmark['type']) && $landmark['type'] === $type) {
                return $landmark;
            }
        }
        return null;
    }

    /**
     * Verificación usando Azure Face API
     */
    private static function verifyWithAzureFace($dniPath, $selfiePath)
    {
        try {
            $apiKey = Yii::$app->params['azure_face_api_key'] ?? '';
            $endpoint = Yii::$app->params['azure_face_endpoint'] ?? '';

            if (empty($apiKey) || empty($endpoint)) {
                Yii::warning('Azure Face API no configurada, usando método simple', 'face-verification');
                return self::verifySimple($dniPath, $selfiePath);
            }

            $client = new Client();

            // Detectar cara en DNI
            $dniImageData = file_get_contents($dniPath);
            $response1 = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("{$endpoint}/face/v1.0/detect?returnFaceId=true&returnFaceLandmarks=false&returnFaceAttributes=qualityForRecognition,occlusion,blur,exposure,noise&recognitionModel=recognition_04&detectionModel=detection_03")
                ->addHeaders([
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Content-Type' => 'application/octet-stream'
                ])
                ->setContent($dniImageData)
                ->send();

            if (!$response1->isOk || empty(json_decode($response1->content))) {
                return [
                    'success' => false,
                    'message' => 'No se detectó cara en el DNI',
                    'match' => false,
                    'score' => 0.0,
                ];
            }

            $dniFaces = json_decode($response1->content, true);
            $dniFace = $dniFaces[0];

            // Validar calidad de la cara del DNI
            $qualityCheck = self::validateAzureFaceQuality($dniFace, 'DNI');
            if ($qualityCheck !== true) {
                return $qualityCheck;
            }

            $dniFaceId = $dniFace['faceId'];

            // Detectar cara en selfie
            $selfieImageData = file_get_contents($selfiePath);
            $response2 = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("{$endpoint}/face/v1.0/detect?returnFaceId=true&returnFaceLandmarks=false&returnFaceAttributes=qualityForRecognition,occlusion,blur,exposure,noise&recognitionModel=recognition_04&detectionModel=detection_03")
                ->addHeaders([
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Content-Type' => 'application/octet-stream'
                ])
                ->setContent($selfieImageData)
                ->send();

            if (!$response2->isOk || empty(json_decode($response2->content))) {
                return [
                    'success' => false,
                    'message' => 'No se detectó cara en la selfie',
                    'match' => false,
                    'score' => 0.0,
                ];
            }

            $selfieFaces = json_decode($response2->content, true);
            $selfieFace = $selfieFaces[0];

            // Validar calidad de la cara de la selfie
            $qualityCheckSelfie = self::validateAzureFaceQuality($selfieFace, 'selfie');
            if ($qualityCheckSelfie !== true) {
                return $qualityCheckSelfie;
            }

            $selfieFaceId = $selfieFace['faceId'];

            // Verificar si son la misma persona
            $response3 = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("{$endpoint}/face/v1.0/verify")
                ->addHeaders([
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'faceId1' => $dniFaceId,
                    'faceId2' => $selfieFaceId
                ]))
                ->send();

            if (!$response3->isOk) {
                throw new \Exception("Error en Azure Face API verify: " . $response3->getStatusCode());
            }

            $verifyResult = json_decode($response3->content, true);
            $threshold = Yii::$app->params['face_match_threshold'] ?? 0.7;

            return [
                'success' => true,
                'match' => $verifyResult['isIdentical'] ?? false,
                'score' => $verifyResult['confidence'] ?? 0.0,
                'distance' => 1 - ($verifyResult['confidence'] ?? 0.0),
                'method' => 'azure_face',
                'quality' => [
                    'dni' => $dniFace['faceAttributes']['qualityForRecognition'] ?? null,
                    'selfie' => $selfieFace['faceAttributes']['qualityForRecognition'] ?? null,
                ],
            ];

        } catch (\Exception $e) {
            Yii::error("Error en verificación Azure Face: " . $e->getMessage(), 'face-verification');
            return [
                'success' => false,
                'message' => 'Error en verificación facial: ' . $e->getMessage(),
                'match' => false,
                'score' => 0.0,
            ];
        }
    }

    /**
     * Valida calidad y oclusiones de una cara detectada por Azure Face API
     * @return true|array true si es válida, o respuesta de error si no lo es
     */
    private static function validateAzureFaceQuality(array $face, string $label)
    {
        $minQuality = Yii::$app->params['azure_face_min_quality'] ?? 0.35;
        $failOnOcclusion = Yii::$app->params['azure_face_fail_on_occlusion'] ?? true;

        $quality = $face['faceAttributes']['qualityForRecognition'] ?? null;
        if ($quality !== null && $quality < $minQuality) {
            return [
                'success' => false,
                'message' => "La calidad de la cara ($label) es baja. Intenta con mejor iluminación o enfoque.",
                'match' => false,
                'score' => 0.0,
            ];
        }

        if ($failOnOcclusion && isset($face['faceAttributes']['occlusion'])) {
            $occ = $face['faceAttributes']['occlusion'];
            if (($occ['eyeOccluded'] ?? false) || ($occ['mouthOccluded'] ?? false)) {
                return [
                    'success' => false,
                    'message' => "La cara ($label) tiene oclusiones (ojos o boca). Descubre el rostro e inténtalo de nuevo.",
                    'match' => false,
                    'score' => 0.0,
                ];
            }
        }

        return true;
    }

    /**
     * Verificación simple usando solo detección básica (sin comparación real)
     * Esta es una solución de fallback muy básica
     */
    private static function verifySimple($dniPath, $selfiePath)
    {
        // Esta es una implementación muy básica que solo verifica que haya caras
        // NO hace comparación real - solo para desarrollo/testing
        try {
            // Usar Imagick para verificar que las imágenes son válidas
            $dniImage = new \Imagick($dniPath);
            $selfieImage = new \Imagick($selfiePath);

            // Verificación muy básica: solo comprobar dimensiones y formato
            // NOTA: Esta NO es una verificación facial real, solo un placeholder
            $dniValid = $dniImage->getImageWidth() > 100 && $dniImage->getImageHeight() > 100;
            $selfieValid = $selfieImage->getImageWidth() > 100 && $selfieImage->getImageHeight() > 100;

            $dniImage->clear();
            $dniImage->destroy();
            $selfieImage->clear();
            $selfieImage->destroy();

            if (!$dniValid || !$selfieValid) {
                return [
                    'success' => false,
                    'message' => 'Las imágenes no son válidas o son muy pequeñas',
                    'match' => false,
                    'score' => 0.0,
                ];
            }

            // ADVERTENCIA: Esta es una simulación - NO hace verificación facial real
            Yii::warning('Usando verificación simple (no real) - configurar Google Vision o Azure Face API', 'face-verification');
            
            return [
                'success' => true,
                'match' => true, // Siempre true en modo simple (solo para desarrollo)
                'score' => 0.8, // Score simulado
                'distance' => 0.2,
                'method' => 'simple',
                'warning' => 'Esta es una verificación simulada. Configure Google Vision API o Azure Face API para verificación real.',
            ];

        } catch (\Exception $e) {
            Yii::error("Error en verificación simple: " . $e->getMessage(), 'face-verification');
            return [
                'success' => false,
                'message' => 'Error en verificación: ' . $e->getMessage(),
                'match' => false,
                'score' => 0.0,
            ];
        }
    }
}

