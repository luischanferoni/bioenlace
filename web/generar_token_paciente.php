<?php
/**
 * Script para generar un token de prueba para un paciente por DNI
 * 
 * Uso:
 *   php generar_token_paciente.php 29486884
 * 
 * O desde el navegador:
 *   http://localhost/bioenlace/web/generar_token_paciente.php?dni=29486884
 */

// Configurar el entorno
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/frontend/config/main.php';

new yii\web\Application($config);

// Obtener DNI de los argumentos de línea de comandos o parámetros GET
$dni = isset($argv[1]) ? $argv[1] : (isset($_GET['dni']) ? $_GET['dni'] : null);

if (!$dni) {
    echo "Error: DNI requerido\n";
    echo "Uso: php generar_token_paciente.php <DNI>\n";
    echo "O desde navegador: ?dni=<DNI>\n";
    exit(1);
}

try {
    // Buscar persona por DNI
    $persona = \common\models\Persona::findOne(['documento' => $dni]);
    
    if (!$persona) {
        echo "Error: No se encontró paciente con DNI: $dni\n";
        exit(1);
    }

    // Verificar si tiene usuario asociado
    if (!$persona->id_user) {
        echo "Error: El paciente con DNI $dni no tiene usuario asociado.\n";
        echo "id_persona: {$persona->id_persona}\n";
        echo "Nombre: {$persona->apellido}, {$persona->nombre}\n";
        exit(1);
    }

    // Buscar el usuario
    $user = \common\models\User::findIdentity($persona->id_user);
    
    if (!$user) {
        echo "Error: Usuario no encontrado para id_user: {$persona->id_user}\n";
        exit(1);
    }

    // Verificar que el usuario esté activo
    if ($user->status !== \common\models\User::STATUS_ACTIVE) {
        echo "Error: Usuario inactivo\n";
        exit(1);
    }

    // Generar token JWT
    $payload = [
        'user_id' => $user->id,
        'email' => $user->email,
        'role' => $user->role,
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60), // 24 horas
    ];

    $token = \Firebase\JWT\JWT::encode($payload, Yii::$app->params['jwtSecret'], 'HS256');

    // Mostrar resultados
    if (php_sapi_name() === 'cli') {
        // Modo CLI
        echo "\n=== Token generado exitosamente ===\n\n";
        echo "DNI: {$persona->documento}\n";
        echo "Nombre: {$persona->apellido}, {$persona->nombre}\n";
        echo "ID Persona: {$persona->id_persona}\n";
        echo "ID Usuario: {$user->id}\n";
        echo "Email: {$user->email}\n";
        echo "Rol: {$user->role}\n";
        echo "\nToken JWT:\n";
        echo "$token\n\n";
        echo "Para usar en la app móvil, guarda este token en SharedPreferences con la clave 'auth_token'\n";
        echo "O úsalo en las peticiones HTTP con el header: Authorization: Bearer $token\n\n";
    } else {
        // Modo web (JSON)
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Token generado exitosamente',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'persona' => [
                    'id_persona' => $persona->id_persona,
                    'nombre' => $persona->nombre,
                    'apellido' => $persona->apellido,
                    'documento' => $persona->documento,
                ],
                'token' => $token,
            ],
        ], JSON_PRETTY_PRINT);
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

