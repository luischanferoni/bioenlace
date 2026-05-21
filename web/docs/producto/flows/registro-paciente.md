# Registro de paciente y médico (API)

## Objetivo

Alta o actualización de **persona** desde apps con validación de identidad (Verifik), empadronamiento MPI y, para médicos, verificación REFEPS/SISA.

## Actores

- App paciente / app médico (self-service).
- Operador web (flujo distinto, manual — ver sección comparación).

## Secuencia — API `registrar`

1. Cliente envía `POST /api/v1/registro/registrar` con `tipo` (`paciente` | `medico`), `dni`, `nombre`, `apellido` y datos de contacto opcionales.
2. `RegistroController` delega en `RegistroService`.
3. **Validación** de campos obligatorios.
4. **Verifik** (`VerifikClient`): si `status = rechazado`, se aborta con 4xx.
5. **Persona**: buscar por documento; crear o actualizar campos básicos.
6. **MPI** (si el componente está configurado): `Mpi::traerPaciente`; actualizar `PersonaMpi`; errores MPI no bloquean pero se devuelven en respuesta.
7. Si `tipo = medico`: **REFEPS/SISA** vía `sisa->getProfesionalesDeSantiago`; si no es profesional habilitado, 4xx.

## Secuencia — app paciente (antes de `registrar`)

1. App envía fotos DNI y selfie a `POST …/signup` (`SignupController`).
2. Backend extrae datos del DNI y verifica selfie (`FaceVerificationManager`).
3. Con datos validados, app llama a `registrar` con `tipo: paciente`.
4. Respuesta se conserva en app para depuración (`data.registro`).

## Secuencia — app médico

Mismo endpoint `registrar` con `tipo: medico`; incluye paso REFEPS. Autenticación de identidad por DNI + verificación externa (no usuario/contraseña clásico en registro).

## Comparación con registro manual web

| Aspecto | Web manual (`PersonaController`) | API `registrar` |
|---------|----------------------------------|-----------------|
| Quién carga datos | Operador administrativo | Usuario en app |
| Verifik / selfie | No automático | Sí |
| MPI | Pasos manuales posibles | Integrado en servicio |
| Médico REFEPS | Manual | Automático |

**Alternativa descartada:** unificar ambos flujos en un solo formulario web para móvil — no cumple self-service ni Verifik en campo.

## Anclas

| Paso | Método / componente |
|------|---------------------|
| Endpoint registro | `RegistroController::actionRegistrar` |
| Negocio | `RegistroService` |
| Signup móvil | `SignupController` |
| Verifik | `VerifikClient` |
| MPI | componente `Yii::$app->mpi` |
| REFEPS | `Yii::$app->sisa` |

## Relacionado

- [design.md](../design.md)
- [plans/phases/10-mobile-paciente.md](../../plans/phases/10-mobile-paciente.md)
