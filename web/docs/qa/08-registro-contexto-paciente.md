# Registro de paciente y contexto operativo

[← Índice](./README.md) · Producto: [registro-paciente.md](../producto/registro-paciente.md) · Checklist: [10-checklist-ejecutable.md](./10-checklist-ejecutable.md)

El **contexto paciente** (`sector_salud` + `provincia`) define qué efectores, intents y secciones del home ve el paciente en la app.

---

## Preparar datos de prueba (consola)

```bash
php yii clinical-seed/efector-demo-contexto
php yii clinical-seed/efector-demo-contexto-info
```

Crea (o actualiza):

| Efector | Sector | Uso |
|---------|--------|-----|
| `[DEV] CAP … Demo` | Público (`Provincial`) | Otra provincia (ej. Santa Fe) |
| `[DEV] Clínica Privada Demo` | Privado | Santiago del Estero (referencia efector 863) |

Cada uno con médico **MED GENERAL** y agenda Lun–Vie 08–17.

---

## Configurar contexto del paciente (app / API)

1. **Vos** (paciente autenticado) abrís la app o llamás `GET /api/v1/paciente-contexto/obtener`.
2. **El sistema** devuelve `sector_salud`, `id_provincia_contexto`, estado de domicilio y si podés operar.
3. **Vos** actualizás con `POST /api/v1/paciente-contexto/actualizar` (`sector_salud`, `id_provincia_contexto`).
4. **El sistema** persiste en `persona_paciente_contexto` y recalcula la oferta.

### Casos a probar

| ID | Sector | Provincia | Resultado esperado |
|----|--------|-----------|-------------------|
| CTX-01 | `PUBLICO` | Provincia del CAP demo | En búsqueda de efectores / turnos aparece el CAP demo; no la clínica privada |
| CTX-02 | `PRIVADO` | Provincia de la clínica demo | Aparece la clínica privada; no el CAP público de otra provincia |
| CTX-03 | `PUBLICO` | Provincia distinta al CAP demo | No aparecen efectores demo si la provincia no coincide |
| CTX-04 | Sin provincia (`id_provincia_contexto` null) | — | Intents que requieren contexto operativo bloqueados; banner de verificación |
| CTX-05 | Cambio de sector después de elegir efector | — | Al pedir turno, `assertEfectorPermitido` rechaza efector incompatible |

---

## Sugerir provincias (geolocalización)

1. **Vos** llamás `GET /api/v1/paciente-contexto/sugerir-provincias-como-paciente`.
2. **El sistema** devuelve hasta 5 provincias (IP + vecinos declarativos en `provincias-vecinas.yaml`).
3. **CTX-06:** Con IP local/privada, la lista incluye Santiago del Estero (`86`) entre las sugeridas.

---

## Recurso provincial (asistente / FAQ)

1. **Vos** (paciente con contexto listo) disparás el intent `paciente-contexto.recurso-provincial-como-paciente-flow` o la frase equivalente en el asistente.
2. **El sistema** busca en `recursos-provinciales.yaml` por `cod_indec` de la provincia del contexto.
3. **CTX-07:** Para Santiago del Estero muestra ministerio / contacto declarado en metadata.
4. **CTX-08:** Para Santa Fe muestra el recurso de Santa Fe, no el de otra provincia.

---

## Registro autoregistro (app)

1. **Vos** completás identidad (Didit / flujo configurado).
2. **El sistema** crea persona, usuario, rol paciente e inicializa contexto (`PUBLICO` por defecto).
3. **CTX-09:** Tras el alta existe fila en `persona_paciente_contexto`.
4. **CTX-10:** Cron `paciente-domicilio/run` avanza estado de domicilio RENAPER (reintentos).

---

## Registro por staff (asistente)

1. **Vos** (staff) abrís `/personas/registrar-paciente` vía asistente o atajo.
2. **Vos** escaneás DNI (PDF417) o usás Didit.
3. **El sistema** confirma alta sin redirigir a ficha clínica ni cambiar tu sesión operativa (efector/servicio staff).
4. **CTX-11:** El `id_persona` del nuevo paciente no queda en sesión del profesional.
5. **CTX-12:** Pantallas legacy MPI (`buscar-persona`, candidatos) responden **410 Gone**.

---

## Home panel y intents filtrados

1. **Vos** (paciente sin provincia operativa) abrís el home de la app.
2. **El sistema** oculta secciones que requieren contexto (`upcoming_appointments`, `patient_async_consultations`).
3. **CTX-13:** Tras fijar provincia, esas secciones vuelven a mostrarse.
4. **CTX-14:** Intent `turnos.crear-como-paciente` no aparece o no avanza sin `puedeOperarApp()`.

---

## Representación (tutor / familiar)

1. **Vos** actuás en nombre de otro paciente (representación verificada).
2. **CTX-15:** El contexto operativo usado es el del **actor logueado**, no del `subject_persona_id` — documentar comportamiento actual al probar representación.

---

## API — referencia rápida

| Método | Ruta | Rol |
|--------|------|-----|
| GET | `/api/v1/paciente-contexto/obtener` | Paciente |
| POST | `/api/v1/paciente-contexto/actualizar` | Paciente |
| GET | `/api/v1/paciente-contexto/sugerir-provincias-como-paciente` | Paciente |
| GET | `/api/v1/paciente-contexto/buscar-recurso-provincial-como-paciente` | Paciente |

Más casos en [10-checklist-ejecutable.md](./10-checklist-ejecutable.md) (prefijo **CTX-**).
