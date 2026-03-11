## Registro de pacientes y médicos – Flujo API vs registro manual Web

Este documento describe el **nuevo flujo de registración vía API con Verifik + MPI + REFEPS** y lo compara con el **registro manual actual desde la Web**.

---

## 1. Flujo de registración vía API (nuevo)

### 1.1. Endpoint principal

- **Ruta**: `POST /api/v1/registro/registrar`  
- **Controlador**: `frontend\modules\api\v1\controllers\RegistroController`  
- **Servicio de negocio**: `common\components\RegistroService`

**Payload mínimo:**

```json
{
  "tipo": "paciente" | "medico",
  "dni": "29486884",
  "nombre": "Mercedes",
  "apellido": "Diaz",
  "fecha_nacimiento": "1984-01-01",
  "email": "mercedes@example.com",
  "telefono": "+54..."
}
```

**Respuesta (éxito, estructura general):**

```json
{
  "success": true,
  "message": "Solicitud de registro recibida correctamente",
  "data": {
    "persona": {
      "id_persona": 123,
      "nombre": "Mercedes",
      "apellido": "Diaz",
      "documento": "29486884",
      "fecha_nacimiento": "1984-01-01",
      "tipo": "paciente",
      "es_nueva": true
    },
    "verifik": {
      "success": true,
      "status": "aprobado",
      "verification_id": "verifik-xyz",
      "raw": { }
    },
    "mpi": {
      "empadronado": true,
      "detalles": { }
    },
    "refeps": {
      "es_profesional": true,
      "detalles": { }
    }
  }
}
```

> Para `tipo = "paciente"`, el campo `refeps` será `null`.

---

### 1.2. Pasos internos del flujo API

1. **Validación de entrada**
   - `tipo` debe ser `paciente` o `medico`.
   - `dni`, `nombre`, `apellido` son obligatorios.

2. **Verificación de identidad con Verifik** (`VerifikClient`)
   - Se llama a la API de Verifik con DNI, nombre y apellido.
   - Si Verifik devuelve **rechazo** (`status = "rechazado"`), se aborta el registro con error 4xx.

3. **Creación / actualización en `personas`** (`RegistroService`)
   - Se busca `Persona` por `documento`:
     - Si **no existe**, se crea una nueva instancia.
     - Si **existe**, se actualizan campos básicos:
       - `nombre`, `apellido`, `documento`, opcionalmente `fecha_nacimiento`, `email`, `telefono`.
   - Se respetan las reglas de validación del modelo `Persona`.

4. **Sincronización con MPI (SEIPA)**
   - Si existe el componente `Yii::$app->mpi`:
     - Se llama a `Mpi::traerPaciente($persona->id_persona)`.
     - Si la respuesta trae `mpi`, se escribe en `id_mpi` mediante el modelo `PersonaMpi`.
   - Errores de MPI no bloquean el alta; se informan en el campo `mpi` de la respuesta.

5. **Verificación de médicos contra REFEPS/SISA** (`tipo = "medico"`)
   - Se usa `Yii::$app->sisa->getProfesionalesDeSantiago(apellido, nombre, "", dni)`.
   - Se interpreta la respuesta (por ejemplo, campos `ok`, `total`, `profesionales`) para marcar `es_profesional`.
   - Si no se confirma que sea profesional, el registro falla con error 4xx:
     - El DNI no corresponde a un profesional habilitado según REFEPS/SISA.

---

## 2. Integración con apps móviles

### 2.1. App Paciente

1. La app envía `dni_photo` y `selfie_photo` a:
   - `POST ${AppConfig.apiUrl}/signup`
2. El backend (`SignupController`):
   - Extrae datos del DNI (ZBar/OCR),
   - Verifica selfie vs foto del DNI (`FaceVerificationManager`),
   - Devuelve `dni_data` (dni, nombre, apellido, etc.) y `face_match`.
3. Si `/signup` es exitoso:
   - La app toma `dni`, `nombre`, `apellido` de `dni_data`.
   - Llama a `POST ${AppConfig.apiUrl}/registro/registrar` con:

```json
{
  "tipo": "paciente",
  "dni": "<dni extraído>",
  "nombre": "<nombre extraído>",
  "apellido": "<apellido extraído>"
}
```

4. La respuesta de `/registro/registrar` se guarda en `data["registro"]` para depuración y uso futuro.
5. Se mantiene el **login simulado** con un usuario de prueba hardcodeado para entornos de desarrollo.

### 2.2. App Médico

- Utiliza el mismo endpoint `POST /api/v1/registro/registrar` con `tipo = "medico"`.
- El backend aplica:
  - Verifik (identidad),
  - Alta/actualización en `personas` + MPI,
  - Verificación en REFEPS/SISA.
- No se usan usuario/contraseña tradicionales en el registro: la autenticación de identidad se basa en DNI + verificación externa.

---

## 3. Script de consola de prueba (Mercedes Díaz)

- **Controlador**: `console\controllers\RegistroController`
- **Comando**:

```bash
php yii registro/simular-mercedes
```

### 3.1. Datos simulados

- Nombre: `Mercedes`
- Apellido: `Diaz`
- DNI: `29486884`
- Tipo: `paciente`

### 3.2. Comportamiento

1. Construye el payload anterior.
2. Llama a `RegistroService::registrar()` (el mismo servicio que usa la API).
3. Muestra en consola:
   - `id_persona`,
   - nombre y apellido,
   - DNI,
   - estado de Verifik (`status`),
   - si MPI está empadronado o no.

---

## 4. Comparación con registro manual Web

### 4.1. Registro manual desde Web

- Se realiza desde formularios en `frontend\controllers\PersonaController` (acciones como `create`, `update`).
- El operador administrativo carga los datos a mano:
  - Identificación, contacto, domicilio, etc.
- No intervienen Verifik, MPI ni REFEPS en forma automática.

### 4.2. Diferencias clave

- **Fuente de datos**:
  - Web manual: datos ingresados manualmente por personal administrativo.
  - API nueva: datos validados por Verifik, complementados con OCR/PDF417 y verificaciones externas.

- **Seguridad**:
  - Web manual: verificación dependiente del operador (visión de DNI físico).
  - API nueva: verificación de identidad automatizada (Verifik + selfie) y, para médicos, confirmación en REFEPS/SISA.

- **Automatización y sincronización**:
  - Web manual: actualización de `personas` y MPI requiere pasos manuales adicionales.
  - API nueva: `RegistroService` centraliza alta/actualización y sincronización con MPI, reduciendo errores humanos.

