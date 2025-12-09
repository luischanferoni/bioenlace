<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\web\BadRequestHttpException;
use yii\helpers\FileHelper;
use yii\base\Exception;
use common\components\FaceVerificationManager;

use Imagick;
use ImagickPixel;

class SignupController extends BaseController
{
    public $modelClass = ''; // No usamos ActiveController para este endpoint
    public $enableCsrfValidation = false; // Importante para API

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // No requerir autenticación para signup
        $behaviors['authenticator']['except'] = ['options', 'index'];
        return $behaviors;
    }

    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // Validar método
        if (!Yii::$app->request->isPost) {
            throw new BadRequestHttpException('Método no permitido');
        }

        // Validar campos obligatorios
        $dniPhoto = UploadedFile::getInstanceByName('dni_photo');
        $selfiePhoto = UploadedFile::getInstanceByName('selfie_photo');

        if (!$dniPhoto || !$selfiePhoto) {
            return [
                'success' => false,
                'message' => 'Faltan campos obligatorios',
            ];
        }

        $userId = uniqid('user_');
        $uploadDir = Yii::getAlias("@app/web/uploads/signups/$userId");
        FileHelper::createDirectory($uploadDir);

        $dniPath = "$uploadDir/dni.{$dniPhoto->extension}";
        $selfiePath = "$uploadDir/selfie.{$selfiePhoto->extension}";

        // Guardar el archivo        
        if (!$dniPhoto->saveAs($dniPath) || !$selfiePhoto->saveAs($selfiePath)) {
            return ['success' => false, 'message' => 'Error al guardar archivos'];
        } 

        $this->autoRotateImage($dniPath, $uploadDir);
        
        // Convertir ruta Windows a WSL (/mnt/c/...)
        $wslPath = str_replace('\\', '/', $dniPath);
        $wslPath = str_replace('D:', '/mnt/d', $wslPath); // Ajustar si no usás C:
        $wslPath = escapeshellarg($wslPath);

        // Intentar múltiples métodos de lectura
        $dniData = $this->extractDniData($dniPath);
        
        if (!$dniData) {
            return $this->asJson([
                'success' => false,
                'message' => 'No se pudo extraer información del DNI',
            ]);
        }

        // Verificar coincidencia facial entre selfie y foto del DNI
        $faceMatch = $this->verifyFaceMatch($dniPath, $selfiePath);
        
        if (!$faceMatch['success']) {
            return $this->asJson([
                'success' => false,
                'message' => $faceMatch['message'] ?? 'Error en verificación facial',
                'face_match' => $faceMatch,
            ]);
        }

        if (!$faceMatch['match']) {
            return $this->asJson([
                'success' => false,
                'message' => 'La verificación facial falló. Las caras no coinciden.',
                'face_match' => $faceMatch,
                'dni_data' => $dniData,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'user_id' => $userId,
            'dni_data' => $dniData,
            'face_match' => $faceMatch,
        ];
    }

    /**
     * Extrae datos del DNI usando múltiples estrategias
     */
    private function extractDniData($imagePath)
    {
        // Estrategia 1: ZBar con PDF417
        $data = $this->extractWithZBar($imagePath);
        if ($data) {
            return $data;
        }

        // Estrategia 2: OCR con Tesseract
        $data = $this->extractWithOCR($imagePath);
        if ($data) {
            return $data;
        }

        // Estrategia 3: OpenCV para detección de texto
        $data = $this->extractWithOpenCV($imagePath);
        if ($data) {
            return $data;
        }

        return null;
    }

    /**
     * Extrae datos usando ZBar (PDF417)
     */
    private function extractWithZBar($imagePath)
    {
        try {
            // Convertir ruta Windows a WSL
            $wslPath = str_replace('\\', '/', $imagePath);
            $wslPath = str_replace('D:', '/mnt/d', $wslPath);
            $wslPath = escapeshellarg($wslPath);

            // Intentar con diferentes parámetros
            $commands = [
                "wsl zbarimg -q --raw $wslPath",
                "wsl zbarimg -q --raw --xml $wslPath",
                "wsl zbarimg -q --raw --xml --raw $wslPath"
            ];

            foreach ($commands as $cmd) {
                Yii::info("Ejecutando: $cmd");
                $output = shell_exec($cmd);
                
                if ($output && trim($output)) {
                    Yii::info("ZBar output: " . $output);
                    
                    // Procesar datos del PDF417
                    $data = $this->parsePdf417Data(trim($output));
                    if ($data) {
                        return $data;
                    }
                }
            }
        } catch (Exception $e) {
            Yii::error("Error en ZBar: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Extrae datos usando OCR (Tesseract)
     */
    private function extractWithOCR($imagePath)
    {
        try {
            // Preprocesar imagen para OCR
            $processedPath = $this->preprocessImageForOCR($imagePath);
            
            // Convertir ruta para WSL
            $wslPath = str_replace('\\', '/', $processedPath);
            $wslPath = str_replace('D:', '/mnt/d', $wslPath);
            $wslPath = escapeshellarg($wslPath);

            // Ejecutar Tesseract
            $cmd = "wsl tesseract $wslPath stdout -l spa";
            Yii::info("Ejecutando OCR: $cmd");
            $output = shell_exec($cmd);
            
            if ($output) {
                Yii::info("OCR output: " . $output);
                return $this->parseOCRData($output);
            }
        } catch (Exception $e) {
            Yii::error("Error en OCR: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Extrae datos usando OpenCV
     */
    private function extractWithOpenCV($imagePath)
    {
        try {
            // Usar OpenCV para detectar texto
            $cmd = "wsl python3 -c \"
import cv2
import numpy as np
import sys

# Cargar imagen
img = cv2.imread('$imagePath')
if img is None:
    sys.exit(1)

# Convertir a escala de grises
gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

# Aplicar threshold
thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)[1]

# Guardar imagen procesada
cv2.imwrite('$imagePath.processed.jpg', thresh)
print('Imagen procesada')
\"";
            
            shell_exec($cmd);
            
            // Ahora usar Tesseract en la imagen procesada
            return $this->extractWithOCR($imagePath . '.processed.jpg');
            
        } catch (Exception $e) {
            Yii::error("Error en OpenCV: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Preprocesa imagen para mejorar OCR
     */
    private function preprocessImageForOCR($imagePath)
    {
        $image = new Imagick($imagePath);
        
        // Convertir a escala de grises
        $image->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        
        // Aumentar contraste
        $image->normalizeImage();
        
        // Redimensionar si es muy pequeña
        $geometry = $image->getImageGeometry();
        if ($geometry['width'] < 1000) {
            $image->scaleImage($geometry['width'] * 2, $geometry['height'] * 2);
        }
        
        // Aplicar filtro para mejorar texto
        $image->unsharpMaskImage(0.5, 1, 1, 0.05);
        
        $processedPath = $imagePath . '.processed.jpg';
        $image->writeImage($processedPath);
        $image->clear();
        $image->destroy();
        
        return $processedPath;
    }

    /**
     * Parsea datos del PDF417
     */
    private function parsePdf417Data($rawData)
    {
        // El formato del PDF417 del DNI argentino es: @dni@apellido@nombre@sexo@nacionalidad@fecha_nac@fecha_emision@...
        $data = explode('@', trim($rawData));
        
        if (count($data) < 8) {
            return null;
        }

        return [
            'dni' => $data[1] ?? null,
            'apellido' => $data[2] ?? null,
            'nombre' => $data[3] ?? null,
            'sexo' => $data[4] ?? null,
            'nacionalidad' => $data[5] ?? null,
            'fecha_nacimiento' => $data[6] ?? null,
            'fecha_emision' => $data[7] ?? null,
            'fecha_vencimiento' => $data[8] ?? null,
            'ejemplar' => $data[9] ?? null,
            'method' => 'pdf417'
        ];
    }

    /**
     * Parsea datos del OCR
     */
    private function parseOCRData($ocrText)
    {
        // Buscar patrones comunes en el DNI
        $patterns = [
            'dni' => '/(\d{7,8})/',
            'apellido' => '/([A-ZÁÉÍÓÚÑÜ][A-ZÁÉÍÓÚÑÜ\s]+)/',
            'nombre' => '/([A-ZÁÉÍÓÚÑÜ][A-ZÁÉÍÓÚÑÜ\s]+)/',
            'fecha_nacimiento' => '/(\d{2}\/\d{2}\/\d{4})/',
        ];

        $data = [];
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $ocrText, $matches)) {
                $data[$key] = $matches[1];
            }
        }

        if (empty($data)) {
            return null;
        }

        $data['method'] = 'ocr';
        return $data;
    }

    function autoRotateImage($filePath, $uploadDir) 
    {
        $image = new Imagick($filePath);
        $orientation = $image->getImageOrientation();
        Yii::info("orientacion: " . $orientation);
        Yii::info("Imagick::ORIENTATION_TOPLEFT: " . Imagick::ORIENTATION_TOPLEFT);

        switch ($orientation) {
            case Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateImage(new ImagickPixel('#00000000'), 360);
                break;
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $image->rotateImage(new ImagickPixel(), 180);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateImage(new ImagickPixel(), -90);
                break;
        }
    
        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        $r = $image->writeImage($filePath);
        $image->clear();
        $image->destroy();
    }

    /**
     * Verifica si la cara de la selfie coincide con la del DNI
     * Usa FaceVerificationManager que soporta múltiples proveedores
     * @param string $dniPath Ruta de la imagen del DNI
     * @param string $selfiePath Ruta de la imagen de la selfie
     * @return array Resultado de la verificación
     */
    private function verifyFaceMatch($dniPath, $selfiePath)
    {
        try {
            return FaceVerificationManager::verifyFaces($dniPath, $selfiePath);
        } catch (Exception $e) {
            Yii::error("Error en verificación facial: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error en verificación facial: ' . $e->getMessage(),
                'match' => false,
                'score' => 0.0,
            ];
        }
    }
    

}
