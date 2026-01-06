# Guía para Configurar Google Cloud APIs

Esta guía te ayudará a obtener las credenciales necesarias para usar las APIs de Google Cloud (Vertex AI, Generative AI, Vision API) en el proyecto.

## Paso 1: Crear o Seleccionar un Proyecto en Google Cloud

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Si no tienes un proyecto, haz clic en el selector de proyectos (arriba a la izquierda)
3. Haz clic en **"Nuevo Proyecto"**
4. Ingresa un nombre para tu proyecto (ej: "bioenlace-ai")
5. Selecciona una organización (si aplica) y haz clic en **"Crear"**
6. Espera a que se cree el proyecto (puede tardar unos segundos)

## Paso 2: Habilitar las APIs Necesarias

1. En la consola de Google Cloud, ve a **"APIs y Servicios" > "Biblioteca"**
2. Busca y habilita las siguientes APIs:
   - **Vertex AI API** (para modelos de IA generativa)
   - **Generative Language API** (para Gemini y otros modelos)
   - **Cloud Vision API** (para análisis de imágenes, ya está parcialmente configurado)
   - **Cloud Speech-to-Text API** (opcional, para transcripción de audio)

3. Para cada API:
   - Haz clic en el nombre de la API
   - Haz clic en el botón **"Habilitar"**
   - Espera a que se habilite (puede tardar unos minutos)

## Paso 3: Crear una Cuenta de Servicio

1. Ve a **"IAM y Administración" > "Cuentas de servicio"**
2. Haz clic en **"Crear cuenta de servicio"**
3. Completa el formulario:
   - **Nombre**: `bioenlace-ai-service` (o el que prefieras)
   - **ID**: Se generará automáticamente
   - **Descripción**: "Cuenta de servicio para APIs de IA de Bioenlace"
4. Haz clic en **"Crear y continuar"**

## Paso 4: Asignar Permisos a la Cuenta de Servicio

1. En la sección **"Otorgar acceso a esta cuenta de servicio"**, asigna los siguientes roles:
   - **Vertex AI User** (`roles/aiplatform.user`)
   - **Cloud Vision API User** (`roles/cloudvision.user`)
   - **Cloud Speech-to-Text API User** (`roles/cloudspeech.user`) - si usas STT
2. Haz clic en **"Continuar"**
3. Haz clic en **"Listo"**

## Paso 5: Crear y Descargar la Clave JSON

1. En la lista de cuentas de servicio, encuentra la que acabas de crear
2. Haz clic en el email de la cuenta de servicio
3. Ve a la pestaña **"Claves"**
4. Haz clic en **"Agregar clave" > "Crear nueva clave"**
5. Selecciona **"JSON"** como tipo de clave
6. Haz clic en **"Crear"**
7. Se descargará automáticamente un archivo JSON con las credenciales

**⚠️ IMPORTANTE**: 
- Este archivo contiene credenciales sensibles
- **NO** lo subas a Git ni lo compartas públicamente
- Guárdalo en un lugar seguro

## Paso 6: Configurar las Credenciales en el Proyecto

### Opción A: Usar Archivo JSON (Recomendado para Producción)

1. Crea un directorio seguro para las credenciales (fuera del repositorio):
   ```
   web/config/credentials/
   ```

2. Mueve el archivo JSON descargado a ese directorio:
   ```
   web/config/credentials/google-cloud-credentials.json
   ```

3. Asegúrate de que este directorio esté en `.gitignore`:
   ```
   /web/config/credentials/
   ```

4. Actualiza `params-local.php` con la ruta al archivo:
   ```php
   'google_cloud_credentials_path' => __DIR__ . '/../config/credentials/google-cloud-credentials.json',
   'google_cloud_project_id' => 'tu-project-id', // Lo encuentras en el archivo JSON o en la consola
   ```

### Opción B: Usar Variable de Entorno (Recomendado para Desarrollo)

1. Establece la variable de entorno `GOOGLE_APPLICATION_CREDENTIALS`:
   ```bash
   # Windows PowerShell
   $env:GOOGLE_APPLICATION_CREDENTIALS="D:\ruta\a\tu\archivo.json"
   
   # Linux/Mac
   export GOOGLE_APPLICATION_CREDENTIALS="/ruta/a/tu/archivo.json"
   ```

2. O agrega la ruta en `params-local.php`:
   ```php
   'google_cloud_credentials_path' => getenv('GOOGLE_APPLICATION_CREDENTIALS'),
   ```

## Paso 7: Obtener el Project ID

El Project ID lo puedes encontrar de varias formas:

1. **En el archivo JSON descargado**: Busca el campo `project_id`
2. **En la consola de Google Cloud**: Aparece en el selector de proyectos
3. **En la URL de la consola**: `https://console.cloud.google.com/home/dashboard?project=TU-PROJECT-ID`

## Paso 8: Configurar en params-local.php

Agrega las siguientes configuraciones en `web/frontend/config/params-local.php`:

```php
// Configuración de Google Cloud
'google_cloud_credentials_path' => __DIR__ . '/../../config/credentials/google-cloud-credentials.json',
'google_cloud_project_id' => 'tu-project-id-aqui',
'google_cloud_region' => 'us-central1', // o la región que prefieras

// API Keys (alternativa más simple, pero menos segura)
'google_vision_api_key' => '', // Opcional: para Vision API sin autenticación de cuenta de servicio
'google_cloud_api_key' => '', // Opcional: para APIs que soporten API key

// Configuración de modelos de Vertex AI
'vertex_ai_model' => 'gemini-1.5-pro', // o 'gemini-1.5-flash', 'text-bison', etc.
'vertex_ai_location' => 'us-central1',
```

## Paso 9: Verificar la Instalación

Puedes verificar que todo funciona correctamente ejecutando un script de prueba o revisando los logs de la aplicación.

## APIs Disponibles y Modelos

### Vertex AI - Modelos Generativos
- **Gemini 1.5 Pro**: `gemini-1.5-pro`
- **Gemini 1.5 Flash**: `gemini-1.5-flash` (más rápido y económico)
- **PaLM 2**: `text-bison@001`, `chat-bison@001`

### Cloud Vision API
- Detección de objetos
- OCR (reconocimiento de texto)
- Detección de caras
- Análisis de imágenes médicas

### Cloud Speech-to-Text
- Transcripción de audio en múltiples idiomas
- Reconocimiento de voz en tiempo real

## Costos y Límites

- **Vertex AI**: Tiene un tier gratuito limitado, luego se cobra por uso
- **Vision API**: Primeros 1,000 requests/mes gratis
- **Speech-to-Text**: Primeros 60 minutos/mes gratis

Revisa la [página de precios de Google Cloud](https://cloud.google.com/pricing) para más detalles.

## Solución de Problemas

### Error: "Could not load the default credentials"
- Verifica que la ruta al archivo JSON sea correcta
- Asegúrate de que el archivo existe y es legible
- Verifica los permisos del archivo

### Error: "Permission denied"
- Verifica que la cuenta de servicio tenga los roles correctos
- Asegúrate de que las APIs estén habilitadas

### Error: "API not enabled"
- Ve a la consola y habilita las APIs necesarias
- Espera unos minutos después de habilitarlas

## Seguridad

- **NUNCA** subas el archivo JSON de credenciales a Git
- Usa variables de entorno en producción
- Rota las credenciales periódicamente
- Limita los permisos de la cuenta de servicio al mínimo necesario

## Recursos Adicionales

- [Documentación de Vertex AI](https://cloud.google.com/vertex-ai/docs)
- [Documentación de Vision API](https://cloud.google.com/vision/docs)
- [Guía de autenticación de Google Cloud](https://cloud.google.com/docs/authentication)

