# QA — App Personal de Salud

[← Índice general](../README.md)

Pruebas de humo en **`mobile/personalsalud`** (Android). Misma API y sesión operativa que la web clínica; los flujos clínicos detallados están en [medico/](../medico/README.md) y [staff/](../staff/README.md) marcando superficie **App**.

## Prerrequisitos

- Usuario Yii creado por **AdminEfector** (ver [admin_efector/gestion-efector.md](../admin_efector/gestion-efector.md) § Usuarios del efector), o cuenta profesional creada desde la app y luego incorporada por AdminEfector.
- Las opciones pagas de consultorio y clínica/centro se muestran como no disponibles hasta que exista checkout real; no deben crear licencias ni pagos simulados.
- `google-services.json` con `com.bioenlace.personalsalud` si probás push.

## Checklist rápido

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| APS-01 | Abrir app sin sesión | Pantalla «Personal de Salud», subtítulo sobre usuario del centro |
| APS-02 | Login con usuario staff | Wizard efector / servicio / área |
| APS-03 | Completar wizard | Inicio (`GET /home/panel`) según rol y `encounter_class` |
| APS-04 | Asistente — mensaje simple | Respuesta sin error de `appClient` |
| APS-05 | Cerrar sesión en Configuración | Vuelve a login; no queda contexto operativo |
| APS-06 | Usuario sin asignación a efector | Mensaje claro (no crash) |
| APS-06a | Crear cuenta profesional desde la app sin e-mail | Verifica identidad y crea cuenta; no asigna centro ni habilita funciones clínicas |
| APS-06b | Usuario con rol AdminEfector intenta usar Personal de Salud | Acceso rechazado en servidor; el rol administra desde web |
| APS-07 | Timeline paciente con turno — motivos cargados | Resumen del chat de motivos visible cuando el paciente envió mensajes y cerró la ventana de carga |
| APS-09 | Login `enfermeria` + EMER, paciente en espera de triage | Tap abre triage Manchester (no snack «hacelo en web») |
| APS-09b | Login `enfermeria` + EMER, paciente ya triado | Tap abre HC/nota; **no** llama `iniciar-atencion` (eso es del médico) |
| APS-10 | Login `Administrativo` + EMER, **Ingresar** | Buscar paciente → queda en espera de triage |

## Cruzar con web

Tras APS-03, repetir el mismo usuario en web frontend con el mismo efector/servicio: el panel de inicio debe ser coherente (misma lógica `home/panel`).

## Flujos por rol

| Rol RBAC | Guía de fondo |
|----------|----------------|
| `Medico` | [medico/checklist.md](../medico/checklist.md) — en app donde aplique |
| `enfermeria`, `Administrativo` | [staff/checklist.md](../staff/checklist.md) |
| Guardia / IMP | [staff/urgencias-guardia.md](../staff/urgencias-guardia.md), [staff/internacion.md](../staff/internacion.md) |
