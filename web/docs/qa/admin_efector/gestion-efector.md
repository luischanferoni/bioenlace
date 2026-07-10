# Admin efector: gestión del centro (web frontend)

[← Admin efector](./README.md) · Checklist: [checklist.md](./checklist.md)

Rol RBAC **`AdminEfector`** en la **web clínica** (`app.bioenlace.io`), sesión frontend.

Referencia permisos: [roles-desde-bd.md](../roles-desde-bd.md).

Operación diaria (turnos, guardia, internación) comparte flujos con [staff/](../staff/README.md); esta guía cubre lo **propio del admin del efector**.

---

## Web — Entrar y contexto

1. **Vos** iniciás sesión con usuario rol `AdminEfector`.
2. **El sistema** muestra los efectores asignados al usuario.
3. **Vos** elegís efector (y servicio si el flujo lo pide).
4. **El sistema** habilita menús de administración del centro (servicios, profesionales, usuarios, reportes).

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-01 | Login AdminEfector | Entra a web frontend |
| AEF-02 | Elegir efector asignado | Contexto operativo OK |

---

## Web — Datos del efector

Permiso: `editarEfector` → `/efectores/update`.

1. **Vos** abrís edición del efector actual.
2. **Vos** modificás un dato permitido (nombre, contacto, etc.).
3. **El sistema** guarda y refleja el cambio.

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-04 | Editar datos del efector | Guarda sin error |
| AEF-05 | Recargar ficha | Persiste el cambio |

---

## Web — Servicios del efector

Permisos: `front_listar_servicios_del_efector`, `crearServicioEfector`, `editarServicioEfector`, `front_eliminar_servicio_efector`, `front_reactivar_servicio_efector`.

1. **Vos** listás servicios del efector.
2. **Vos** creás o editás un servicio (especialidad, habilitación turnos, etc.).
3. **El sistema** actualiza listados y asignaciones PES disponibles.

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-06 | Listar servicios | Listado del efector actual |
| AEF-07 | Crear servicio | Alta OK |
| AEF-08 | Editar servicio | Cambios persisten |
| AEF-09 | Desactivar / reactivar servicio | Estado coherente en listados |

---

## Web — Profesionales (PES) y agendas

Permisos: `crearProfesionalEfectorServicio`, `front_listar_asignaciones_pes`, `configurar_agenda`, `front_eliminar_asignacion_efector`, `front_reactivar_asignacion_efector`.

1. **Vos** asignás un profesional a un servicio del efector (PES).
2. **Vos** configurás agenda del PES si aplica.
3. **El sistema** permite turnos sobre ese PES.

Intents asistente (si probás chat): `profesionales.listado-efector`, `profesionales.conteo-efector`, `profesionales.distribucion-servicio-efector`.

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-10 | Listar asignaciones PES | Profesionales por servicio |
| AEF-11 | Alta PES | Asignación visible |
| AEF-12 | Configurar agenda PES | Cupos reflejados en turnos |
| AEF-13 | Asistente «profesionales del efector» | Intent responde con listado |

---

## Web — Usuarios del efector

Permisos: `crearUsuarioEfector`, `assignRolesToUsers`, `borrarUserEfector`, `asignarEfector`, `FrontEditarUser`.

**Regla de producto**

| Situación | Qué hace AdminEfector |
|-----------|------------------------|
| Persona **sin usuario Yii** que empieza en el efector | **Crea** el usuario y lo vincula al efector con rol (`Medico`, `enfermeria`, `Administrativo`, …) |
| Persona **que ya tiene usuario** y pasa a otro efector | **Asigna** al nuevo efector (mismo login); no hace falta crear otro usuario |
| Personal que abre la **app Personal de Salud** | Solo **login**; si no tiene usuario, debe pedirlo a administración del centro |

La app móvil **no** tiene registro comercial in-app (sí CTA al alta web de consultorio). Ver humo en [app-personalsalud/README.md](../app-personalsalud/README.md).

1. **Vos** creás o vinculás usuario al efector.
2. **Vos** asignás roles (p. ej. `Medico`, `Administrativo`).
3. **El sistema** limita accesos según RBAC.
4. **El personal** puede ingresar en web o en app con ese mismo usuario.

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-14 | Crear usuario efector (primera vez) | Usuario puede login web y app |
| AEF-14b | Vincular persona existente a otro efector | Mismo usuario, nuevo efector en lista |
| AEF-15 | Asignar rol Medico/Administrativo | Menús acordes al rol |
| AEF-16 | Quitar usuario del efector | Pierde acceso al centro |

---

## Web — Licencias y condición laboral (staff)

Permisos: `licencia.cargar-para-profesional-flow`, `condicion-laboral.editar-staff`, pantallas `front_licencias_por_profesional`, etc.

1. **Vos** registrás licencia de un profesional del efector.
2. **El sistema** muestra turnos afectados si aplica (`front_turnos_afectados_por_licencia`).

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-17 | Cargar licencia profesional | Licencia visible |
| AEF-18 | Editar condición laboral staff | Persiste en ficha RRHH |

---

## Web — Infraestructura (camas)

Permisos: `infraestructuraPiso`, `infraestructuraSala`, `infraestructuraCama`.

1. **Vos** administrás pisos, salas y camas del efector.
2. **El sistema** refleja cambios en mapa de internación ([staff/internacion.md](../staff/internacion.md)).

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-19 | Alta piso/sala/cama | Visible en mapa |
| AEF-20 | Editar cama | Estado coherente |

---

## Web — Indicadores y reportes

Permisos: `turnos.indicadores-agenda-flow`, `front_reporte_planilla_c4/c7/c9`, `front_reporte_planilla5`, `front_reporte_farmacia`.

1. **Vos** consultás indicadores de agenda (asistente o pantalla).
2. **Vos** generás planilla en rango de fechas con atenciones cargadas.

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-21 | Indicadores agenda | KPIs sin error |
| AEF-22 | Planilla nomenclador | Export/listado coherente |

Detalle reportes: [staff/reportes-nomenclador.md](../staff/reportes-nomenclador.md) (AdminEfector suele tener los mismos permisos de planillas).

---

## Cruzar con app paciente

Tras configurar servicio + PES + agenda, verificá turnos desde [paciente/turnos.md](../paciente/turnos.md) con paciente del sector/provincia del efector.

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-23 | Paciente con contexto OK + PES con cupos | Puede sacar turno en ese servicio |

## Cruzar con app Personal de Salud

Tras AEF-14 / AEF-15, el mismo usuario debe poder login en `mobile/personalsalud`, completar wizard y ver inicio coherente con web.

| ID | Acción | Resultado esperado |
|----|--------|-------------------|
| AEF-24 | Login app con usuario recién creado | Wizard + inicio OK |
| AEF-25 | Mismo usuario en web y app (mismo efector) | Panel inicio alineado |

Contexto paciente: [paciente/contexto-registro.md](../paciente/contexto-registro.md).
App: [app-personalsalud/README.md](../app-personalsalud/README.md).
