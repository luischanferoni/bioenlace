# QA — App Personal de Salud

[← Índice general](../README.md)

Pruebas de humo en **`mobile/personalsalud`** (Android). Misma API y sesión operativa que la web clínica; los flujos clínicos detallados están en [medico/](../medico/README.md) y [staff/](../staff/README.md) marcando superficie **App**.

## Prerrequisitos

- Usuario Yii creado por **AdminEfector** (ver [admin_efector/gestion-efector.md](../admin_efector/gestion-efector.md) § Usuarios del efector), o alta de consultorio propio en institucional.
- La app **no** ofrece alta comercial in-app: hay CTA a `alta.html?perfil=consultorio` en el login.
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
| APS-07 | Timeline paciente con turno — motivos cargados | Resumen del chat de motivos visible cuando el paciente envió mensajes y cerró la ventana de carga |
| APS-08 | Tap en CTA consultorio del login | Abre el alta web con `perfil=consultorio` |

## Cruzar con web

Tras APS-03, repetir el mismo usuario en web frontend con el mismo efector/servicio: el panel de inicio debe ser coherente (misma lógica `home/panel`).

## Flujos por rol

| Rol RBAC | Guía de fondo |
|----------|----------------|
| `Medico` | [medico/checklist.md](../medico/checklist.md) — en app donde aplique |
| `enfermeria`, `Administrativo` | [staff/checklist.md](../staff/checklist.md) |
| Guardia / IMP | [staff/urgencias-guardia.md](../staff/urgencias-guardia.md), [staff/internacion.md](../staff/internacion.md) |
