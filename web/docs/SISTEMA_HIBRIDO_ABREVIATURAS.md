# Sistema Híbrido de Abreviaturas Médicas

## Descripción

Sistema inteligente que combina una base de datos de abreviaturas conocidas con detección automática y aprendizaje de nuevas abreviaturas no reconocidas.

## Características Principales

### ✅ **Base de Datos de Abreviaturas**
- **Abreviaturas médicas específicas**: Oftalmología, cardiología, etc.
- **Abreviaturas genéricas**: Del lenguaje común (Pte, Dr, Dra, etc.)
- **Categorización**: Por especialidad y tipo
- **Frecuencia de uso**: Para priorizar las más comunes

### ✅ **Detección Inteligente**
- **Patrones automáticos**: Detecta abreviaturas no reconocidas
- **Análisis de contexto**: Determina si es contexto médico o general
- **Reporte automático**: Guarda sugerencias para revisión

### ✅ **Sistema de Aprendizaje**
- **Reportes de usuarios**: Los usuarios pueden reportar abreviaturas
- **Aprobación/Rechazo**: Sistema de moderación
- **Estadísticas**: Análisis de frecuencia de reportes

## Estructura de Base de Datos

### Tabla: `abreviaturas_medicas`
```sql
CREATE TABLE abreviaturas_medicas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    abreviatura VARCHAR(50) NOT NULL,
    expansion_completa VARCHAR(255) NOT NULL,
    categoria VARCHAR(100), -- 'medicion', 'medicamento', 'general', etc.
    especialidad VARCHAR(100), -- 'oftalmologia', 'cardiologia', NULL para general
    contexto TEXT,
    sinonimos TEXT,
    frecuencia_uso INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabla: `abreviaturas_sugeridas`
```sql
CREATE TABLE abreviaturas_sugeridas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    abreviatura VARCHAR(50) NOT NULL,
    expansion_propuesta VARCHAR(255),
    contexto TEXT,
    texto_completo TEXT,
    especialidad VARCHAR(100),
    usuario_id INT,
    frecuencia_reporte INT DEFAULT 1,
    estado VARCHAR(20) DEFAULT 'pendiente', -- 'pendiente', 'aprobada', 'rechazada'
    fecha_reporte TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_revision TIMESTAMP NULL,
    revisado_por INT,
    comentarios TEXT
);
```

## Categorías de Abreviaturas

### **Médicas Específicas**
- **Oftalmología**: AV, PIO, OD, OI, CAT, GLAUCOMA, etc.
- **Cardiología**: ECG, EKG, IAM, HTA, etc.
- **General**: HTA, DM, etc.

### **Genéricas del Lenguaje**
- **Títulos**: Dr, Dra, Sra, Sr, Prof, Lic, etc.
- **Referencias**: Pte (Paciente), Ref (Referencia), etc.
- **Tiempo**: Hoy, Ayer, Sem (Semana), Mes, Año
- **Ubicaciones**: Dir (Dirección), Tel (Teléfono), Email
- **Estados**: Estado, Condición, Situación, Problema
- **Medidas**: Cant (Cantidad), Unidad, Dosis, Frecuencia
- **Documentos**: Doc (Documento), Reg (Registro), Hist (Historia)
- **Comunicación**: Com (Comunicación), Informe, Reporte, Resumen
- **Acciones**: Eval (Evaluación), Examen, Prueba, Test, Análisis
- **Resultados**: Resultado, Hallazgo, Conclusión, Recomendación

## API Endpoints

### **Frontend - ConsultaController**

#### **1. Analizar Consulta (Procesamiento Automático)**
```http
POST /api/v1/consulta/analizar
Content-Type: application/json

{
    "consulta": "Pte con AV 20/40 OD, PIO 18 mmHg, Dr. García",
    "userPerTabConfig": {
        "id_rrhh_servicio": 1,
        "servicio_actual": 1
    },
    "id_configuracion": 1
}
```

**Funcionalidad:**
- ✅ Procesa texto expandiendo abreviaturas automáticamente
- ✅ Detecta abreviaturas no reconocidas
- ✅ Reporta automáticamente a la base de datos
- ✅ Envía texto expandido a la IA para análisis

### **Backend - AbreviaturasController**

#### **2. Listar Abreviaturas**
```http
GET /backend/abreviaturas/index?especialidad=oftalmologia&categoria=medicion&busqueda=AV
```

#### **3. Obtener Abreviaturas Más Reportadas**
```http
GET /backend/abreviaturas/mas-reportadas?limite=20
```

#### **4. Obtener Estadísticas de Sugerencias**
```http
GET /backend/abreviaturas/estadisticas
```

#### **5. Obtener Sugerencias Pendientes**
```http
GET /backend/abreviaturas/sugerencias-pendientes?limite=50
```

#### **6. Aprobar Abreviatura**
```http
POST /backend/abreviaturas/aprobar
Content-Type: application/json

{
    "id": 1,
    "comentarios": "Abreviatura válida para radiología"
}
```

#### **7. Rechazar Abreviatura**
```http
POST /backend/abreviaturas/rechazar
Content-Type: application/json

{
    "id": 1,
    "comentarios": "No es una abreviatura médica válida"
}
```

#### **8. Agregar Nueva Abreviatura**
```http
POST /backend/abreviaturas/agregar
Content-Type: application/json

{
    "abreviatura": "NUEVA",
    "expansion_completa": "Nueva Expansión",
    "categoria": "medicamento",
    "especialidad": "oftalmologia",
    "contexto": "Nuevo medicamento oftalmológico"
}
```

#### **9. Actualizar Abreviatura**
```http
POST /backend/abreviaturas/actualizar
Content-Type: application/json

{
    "id": 1,
    "expansion_completa": "Nueva expansión actualizada",
    "categoria": "medicamento"
}
```

#### **10. Eliminar Abreviatura**
```http
GET /backend/abreviaturas/eliminar?id=1
```

### **Uso Directo de Modelos**

#### **Procesar Texto Programáticamente**
```php
$resultado = ProcesadorTextoMedico::expandirAbreviaturas($texto, $especialidad);
// Retorna: texto_original, texto_procesado, abreviaturas_encontradas, metadatos
```

#### **Reportar Abreviatura Manualmente**
```php
$datos = [
    'abreviatura' => 'XYZ',
    'expansion_propuesta' => 'Examen de Rayos X',
    'contexto' => 'contexto_medico',
    'texto_completo' => 'Pte con XYZ normal',
    'especialidad' => 'oftalmologia',
    'usuario_id' => 1
];

AbreviaturasSugeridas::reportarAbreviatura($datos);
```

## Patrones de Detección

### **Tipos de Abreviaturas Detectadas**

1. **Abreviaturas Médicas** (`/^[A-Z]{2,4}$/`)
   - Ejemplos: AV, PIO, ECG, IAM
   - 2-4 letras mayúsculas

2. **Abreviaturas con Punto** (`/^[A-Z]{1,3}\.$/`)
   - Ejemplos: Dr., Dra., Sra.
   - 1-3 letras mayúsculas + punto

3. **Abreviaturas con Números** (`/^[A-Z]{1,3}\d+$/`)
   - Ejemplos: AV20, PIO18
   - Letras + números

4. **Abreviaturas Mixtas** (`/^[A-Z][a-z]{1,2}$/`)
   - Ejemplos: Pte, Ref, Obs
   - Primera mayúscula + minúsculas

### **Análisis de Contexto**

El sistema analiza el contexto alrededor de la abreviatura para determinar si es médica:

**Palabras clave médicas:**
- consulta, paciente, doctor, medicamento, diagnóstico
- tratamiento, síntoma, enfermedad, examen, prueba
- resultado, prescripción, dosis, frecuencia, control
- seguimiento, historia, clínica, médico, hospital

## Flujo de Trabajo

### **1. Procesamiento Automático**
```
Texto → Limpiar → Expandir Abreviaturas Conocidas → Detectar No Reconocidas → Reportar → Incrementar Frecuencia
```

### **2. Sistema de Aprendizaje**
```
Detección → Reporte Automático → Revisión Manual → Aprobación/Rechazo → Agregar a Base de Datos
```

### **3. Gestión de Sugerencias**
```
Usuarios Reportan → Moderación → Aprobación → Integración → Uso Automático
```

## Estadísticas y Monitoreo

### **Métricas Disponibles**
- Total de abreviaturas en base de datos
- Abreviaturas por especialidad
- Frecuencia de uso
- Sugerencias pendientes
- Abreviaturas más reportadas
- Tasa de aprobación/rechazo

### **Endpoint de Estadísticas**
```http
GET /backend/abreviaturas/estadisticas
```

**Respuesta:**
```json
{
    "success": true,
    "data": {
        "total_sugerencias": 150,
        "pendientes": 25,
        "aprobadas": 100,
        "rechazadas": 25,
        "por_especialidad": [
            {"especialidad": "oftalmologia", "total": 80},
            {"especialidad": "cardiologia", "total": 45},
            {"especialidad": null, "total": 25}
        ]
    }
}
```

## Ventajas del Sistema Híbrido

### **1. Inmediato**
- Funciona desde el primer día
- No requiere entrenamiento previo
- Base de datos inicial completa

### **2. Inteligente**
- Detecta automáticamente abreviaturas no reconocidas
- Analiza contexto médico
- Aprende de los usuarios

### **3. Escalable**
- Fácil agregar nuevas abreviaturas
- Sistema de moderación
- Estadísticas de uso

### **4. Eficiente**
- Procesamiento rápido
- Índices optimizados
- Caché de consultas frecuentes

### **5. Mantenible**
- API REST completa
- Logs detallados
- Sistema de reportes

## Casos de Uso

### **Ejemplo 1: Texto con Abreviaturas Conocidas**
**Input:** "Pte con AV 20/40 OD, PIO 18 mmHg"
**Output:** "Paciente con Agudeza Visual 20/40 Ojo Derecho, Presión Intraocular 18 milímetros de mercurio"

### **Ejemplo 2: Texto con Abreviaturas No Reconocidas**
**Input:** "Pte con XYZ normal, Dr. García"
**Output:** "Paciente con XYZ normal, Doctor García"
**Reporte:** XYZ detectada como posible abreviatura médica

### **Ejemplo 3: Aprendizaje Automático**
1. Usuario escribe "Pte con ABC positivo"
2. Sistema detecta "ABC" como posible abreviatura
3. Sistema reporta automáticamente
4. Moderador aprueba con expansión "Análisis de Sangre Completo"
5. ABC se agrega a la base de datos
6. Futuros textos con ABC se expanden automáticamente

## Configuración y Mantenimiento

### **Agregar Abreviaturas Manualmente**
```php
AbreviaturasMedicas::agregarAbreviatura([
    'abreviatura' => 'NUEVA',
    'expansion_completa' => 'Nueva Expansión',
    'categoria' => 'medicamento',
    'especialidad' => 'oftalmologia',
    'contexto' => 'Nuevo medicamento oftalmológico'
]);
```

### **Limpiar Sugerencias Antiguas**
```php
// Eliminar sugerencias rechazadas de hace más de 6 meses
AbreviaturasSugeridas::deleteAll([
    'and',
    ['estado' => AbreviaturasSugeridas::ESTADO_RECHAZADA],
    ['<', 'fecha_revision', date('Y-m-d', strtotime('-6 months'))]
]);
```

### **Optimizar Base de Datos**
```sql
-- Actualizar estadísticas de frecuencia
UPDATE abreviaturas_medicas 
SET frecuencia_uso = (
    SELECT COUNT(*) 
    FROM logs_procesamiento 
    WHERE abreviatura = abreviaturas_medicas.abreviatura
);
```

## Arquitectura del Sistema

### **Separación Frontend/Backend**

**Frontend (ConsultaController):**
- ✅ Procesamiento automático de consultas
- ✅ Expansión de abreviaturas en tiempo real
- ✅ Detección y reporte automático de abreviaturas no reconocidas
- ✅ Integración con IA para análisis médico

**Backend (AbreviaturasController):**
- ✅ Administración completa de abreviaturas
- ✅ Moderación de sugerencias (aprobar/rechazar)
- ✅ Estadísticas y reportes
- ✅ Gestión de base de datos de abreviaturas

### **Flujo de Datos**

```
Consulta Médica → Frontend → ProcesadorTextoMedico → 
├── Expande abreviaturas conocidas
├── Detecta abreviaturas no reconocidas
├── Reporta automáticamente a base de datos
└── Envía texto expandido a IA

Administrador → Backend → AbreviaturasController →
├── Revisa sugerencias pendientes
├── Aprueba/rechaza abreviaturas
├── Gestiona base de datos
└── Monitorea estadísticas
```

## Conclusión

El Sistema Híbrido de Abreviaturas Médicas combina lo mejor de ambos mundos:
- **Base de datos sólida** con abreviaturas conocidas
- **Detección inteligente** de abreviaturas no reconocidas
- **Sistema de aprendizaje** que mejora continuamente
- **Arquitectura MVC** con separación clara de responsabilidades
- **API completa** para gestión y monitoreo

Este enfoque garantiza que el sistema sea inmediatamente útil, pero también capaz de crecer y adaptarse a las necesidades específicas de cada especialidad médica, con una arquitectura limpia y mantenible.
