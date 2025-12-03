# Sistema de Base de Datos Semántica para Abreviaturas Médicas

## Descripción

Este sistema permite procesar consultas médicas expandiendo automáticamente las abreviaturas médicas antes de enviarlas a la IA, mejorando significativamente la comprensión y precisión del análisis.

## Beneficios

### Para la IA:
- **Mejor comprensión**: Texto más legible y completo
- **Menos ambigüedad**: Abreviaturas expandidas con contexto
- **Mejor precisión**: Menos errores de interpretación
- **Optimización de tokens**: Texto más eficiente para procesamiento

### Para el sistema:
- **Escalabilidad**: Fácil agregar nuevas abreviaturas
- **Especialización**: Filtrado por especialidad médica
- **Aprendizaje**: Frecuencia de uso para priorizar
- **Mantenimiento**: Gestión centralizada de abreviaturas

## Estructura de la Base de Datos

### Tabla: `abreviaturas_medicas`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT | Identificador único |
| `abreviatura` | VARCHAR(50) | Abreviatura médica (ej: "AV", "PIO") |
| `expansion_completa` | VARCHAR(255) | Expansión completa (ej: "Agudeza Visual") |
| `categoria` | VARCHAR(100) | Categoría (medicion, medicamento, procedimiento, etc.) |
| `especialidad` | VARCHAR(100) | Especialidad médica (oftalmologia, cardiologia, etc.) |
| `contexto` | TEXT | Contexto de uso de la abreviatura |
| `sinonimos` | TEXT | Sinónimos o variaciones |
| `frecuencia_uso` | INT | Contador de uso para priorizar |
| `activo` | TINYINT | Si la abreviatura está activa |
| `fecha_creacion` | TIMESTAMP | Fecha de creación |
| `fecha_actualizacion` | TIMESTAMP | Fecha de última actualización |

## Instalación

### 1. Ejecutar la migración
```bash
php yii migrate --migrationPath=@app/migrations
```

### 2. Verificar la instalación
```bash
php yii migrate/history
```

## Uso del Sistema

### Procesamiento Automático

El sistema se integra automáticamente en el flujo de análisis de consultas:

```php
// En ConsultaController::actionAnalizar()
$textoProcesado = ProcesadorTextoMedico::prepararParaIA($textoConsulta, $servicio->nombre);
$resultadoIA = $this->analizarConsultaConIA($textoProcesado, $servicio->nombre, $idConfiguracion);
```

### Ejemplo de Transformación

**Texto Original:**
```
"Paciente con AV 20/40 OD, PIO 18 mmHg, FO normal, CV reducido. 
Diagnóstico: CAT incipiente. Tratamiento: LATANOPROST 1 gota OD."
```

**Texto Procesado:**
```
"Paciente con Agudeza Visual 20/40 Ojo Derecho, Presión Intraocular 18 milímetros de mercurio, 
Fondo de Ojo normal, Campo Visual reducido. Diagnóstico: Catarata incipiente. 
Tratamiento: Latanoprost 1 gota Ojo Derecho."
```

## API Endpoints

### 1. Obtener Abreviaturas
```http
GET /api/v1/consulta/abreviaturas?especialidad=oftalmologia&categoria=medicion
```

### 2. Agregar Nueva Abreviatura
```http
POST /api/v1/consulta/abreviaturas
Content-Type: application/json

{
    "abreviatura": "OCT",
    "expansion_completa": "Tomografía de Coherencia Óptica",
    "categoria": "procedimiento",
    "especialidad": "oftalmologia",
    "contexto": "Estudio de imagen de alta resolución de la retina"
}
```

### 3. Actualizar Abreviatura
```http
PUT /api/v1/consulta/abreviaturas
Content-Type: application/json

{
    "id": 1,
    "expansion_completa": "Nueva expansión",
    "contexto": "Nuevo contexto"
}
```

### 4. Eliminar Abreviatura
```http
DELETE /api/v1/consulta/abreviaturas?id=1
```

### 5. Estadísticas
```http
GET /api/v1/consulta/estadisticas-abreviaturas
```

### 6. Procesar Texto de Prueba
```http
POST /api/v1/consulta/procesar-texto
Content-Type: application/json

{
    "texto": "Paciente con AV 20/40, PIO 18 mmHg",
    "especialidad": "oftalmologia"
}
```

## Categorías de Abreviaturas

### Oftalmología
- **Mediciones**: AV, PIO, CV
- **Anatomía**: OD, OI, AO
- **Condiciones**: CAT, GLAUCOMA, DMRE
- **Medicamentos**: LATANOPROST, TIMOLOL, DORZOLAMIDA
- **Procedimientos**: FA, OCT, LASIK
- **Síntomas**: DOLOR OCULAR, VISION BORROSA, FOTOFOBIA

### General Médico
- **Condiciones**: HTA, DM
- **Documentos**: HISTORIA CLINICA, ANTECEDENTES
- **Tratamientos**: MEDICAMENTO, DOSIS, FRECUENCIA

## Funcionalidades Avanzadas

### 1. Detección Automática de Abreviaturas
El sistema puede detectar palabras que podrían ser abreviaturas no reconocidas:

```php
$sugerencias = ProcesadorTextoMedico::getSugerenciasAbreviaturas($texto, $especialidad);
```

### 2. Frecuencia de Uso
El sistema incrementa automáticamente el contador de frecuencia cada vez que se usa una abreviatura, permitiendo priorizar las más comunes.

### 3. Filtrado por Especialidad
Las abreviaturas se pueden filtrar por especialidad médica, mostrando solo las relevantes para cada área.

### 4. Metadatos de Procesamiento
Cada procesamiento genera metadatos útiles:

```php
[
    'texto_original' => '...',
    'texto_procesado' => '...',
    'abreviaturas_encontradas' => [...],
    'metadatos' => [
        'longitud_original' => 150,
        'longitud_procesado' => 280,
        'incremento_longitud' => 130,
        'numero_abreviaturas' => 5,
        'categorias_encontradas' => ['medicion', 'medicamento']
    ],
    'mejora_legibilidad' => [
        'palabras_originales' => 25,
        'palabras_expandidas' => 45,
        'incremento_palabras' => 20,
        'porcentaje_incremento' => 80.0
    ]
]
```

## Mantenimiento

### Agregar Abreviaturas Comunes
```php
$abreviaturas = [
    ['AV', 'Agudeza Visual', 'medicion', 'oftalmologia', 'Medición de la capacidad visual'],
    ['PIO', 'Presión Intraocular', 'medicion', 'oftalmologia', 'Medición de la presión del ojo'],
    // ... más abreviaturas
];

foreach ($abreviaturas as $abrev) {
    AbreviaturasMedicas::agregarAbreviatura([
        'abreviatura' => $abrev[0],
        'expansion_completa' => $abrev[1],
        'categoria' => $abrev[2],
        'especialidad' => $abrev[3],
        'contexto' => $abrev[4]
    ]);
}
```

### Limpieza de Datos
```php
// Marcar abreviaturas no utilizadas como inactivas
$abreviaturasInactivas = AbreviaturasMedicas::find()
    ->where(['frecuencia_uso' => 0])
    ->andWhere(['<', 'fecha_creacion', date('Y-m-d', strtotime('-6 months'))])
    ->all();

foreach ($abreviaturasInactivas as $abreviatura) {
    $abreviatura->activo = 0;
    $abreviatura->save();
}
```

## Monitoreo y Logs

El sistema registra información detallada en los logs:

```php
\Yii::info("Texto procesado en {$tiempoProcesamiento}s. Abreviaturas encontradas: " . count($abreviaturasEncontradas), 'procesador-texto');
```

## Consideraciones de Rendimiento

- **Índices optimizados**: La tabla tiene índices en campos de búsqueda frecuente
- **Caché de consultas**: Las abreviaturas se pueden cachear por especialidad
- **Procesamiento asíncrono**: Para textos muy largos, considerar procesamiento en background

## Extensibilidad

El sistema está diseñado para ser fácilmente extensible:

1. **Nuevas especialidades**: Agregar especialidades médicas
2. **Nuevas categorías**: Crear categorías específicas
3. **Integración con otros sistemas**: API REST completa
4. **Machine Learning**: Futuras mejoras con ML para detección automática

## Conclusión

Este sistema de base de datos semántica para abreviaturas médicas mejora significativamente la calidad del análisis de IA al proporcionar texto más legible y contextual. La implementación es escalable, mantenible y fácil de usar, proporcionando una base sólida para el procesamiento inteligente de consultas médicas.
