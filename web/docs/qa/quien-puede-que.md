# Quién puede hacer qué

[← Índice](./README.md)

Resumen: **paciente** usa la app paciente (autoregistro); **personal de salud** ingresa con usuario creado por **AdminEfector** (primera asignación a un efector) en **web** y/o **app Personal de Salud** (`mobile/personalsalud`). Detalle RBAC: [roles-desde-bd.md](./roles-desde-bd.md).

## Por tipo de usuario

| Tema | Paciente (app) | Personal de salud (web / app) | Staff (web / app) | Admin efector (web) |
|------|----------------|--------------|-------------|---------------------|
| Entrar | Sí, con su usuario | Sí | Sí | Sí, rol `AdminEfector` |
| Elegir sanatorio / servicio | No | Sí, al empezar el día | Sí | Sí (administra el centro) |
| Sacar turno | Sí, para sí | Atender / agenda propia | Sí, para cualquier paciente | Configura cupos/PES; operación como staff |
| Cancelar / cambiar turno | Sí, con reglas | — | Sí, a veces sin mismas restricciones | Sí (permisos staff ampliados) |
| Ver laboratorio y recetas | Sí, los propios | Emitir en consulta | Carga / integración variable | No (salvo flujos compartidos) |
| Contexto sector / provincia | Sí, en la app | No | No | Edita datos del efector |
| Servicios, PES, usuarios del centro | No | No | No | **Sí** |
| Tablero de guardia y triage | No | Sí | Sí, en servicio de guardia | Sí |
| Mapa de camas e internación | No | Sí (captura piso) | Sí | Sí (+ infraestructura camas) |
| Captura clínica / historia | No (solo resúmenes) | Sí | Enfermería / apoyo | No (salvo permisos clínicos puntuales) |
| Nomencladores y planillas | No | Parcial | Sí, roles administrativos | Sí (reportes del efector) |
| Asistente por chat | Sí, flujos de paciente | Sí, flujos clínicos | Sí, flujos operativos | Sí, flujos admin (PES, indicadores) |

## Por “modo” del sanatorio (lo que elegís al entrar)

| Modo | Pantalla principal que vas a usar |
|------|-----------------------------------|
| **Ambulatorio** | Turnos, agenda, captura de consultorio |
| **Guardia** | Tablero de urgencias, triage, atender |
| **Internación** | Mapa de camas, ingreso, alta |

Si elegís el modo equivocado, el panel no muestra lo que esperás — volvé a elegir efector y servicio ([staff/transversal.md](./staff/transversal.md)).

## Módulos del producto (madurez aproximada)

| Área | Paciente | Médico / staff | Admin efector | Asistente |
|------|----------|----------------|---------------|-----------|
| Turnos y agenda | Muy usado | Muy usado | Config + indicadores | Muchos flujos |
| Guardia / urgencias | — | Muy usado | Muy usado | Tablero y triage |
| Internación | — | Muy usado | Infraestructura + operación | Mapa, ingreso, alta |
| Captura y consulta | Resúmenes | Muy usado | — | Parcial |
| Laboratorio | Ver resultados | Carga / integración variable | — | Ver resultados (paciente) |
| Receta electrónica | Ver recetas | Emitir en consulta | — | Ver recetas (paciente) |
| Planes y recordatorios | Sí | Adherencia staff | — | Sí |
| Quirófano, farmacia, facturación | — | Parcial | Reportes | Poco o nada en asistente |

Detalle HIS: [his-completo/](../his-completo/README.md).

## Dónde está cada flujo paso a paso

| Audiencia | Carpeta |
|-----------|---------|
| Paciente (app) | [paciente/](./paciente/README.md) |
| Médico | [medico/](./medico/README.md) |
| Staff (web) | [staff/](./staff/README.md) |
| Admin efector (web) | [admin_efector/](./admin_efector/README.md) |

## Checklists por audiencia

| Carpeta | Archivo |
|---------|---------|
| Paciente | [paciente/checklist.md](./paciente/checklist.md) |
| Médico | [medico/checklist.md](./medico/checklist.md) |
| Staff | [staff/checklist.md](./staff/checklist.md) |
| Admin efector | [admin_efector/checklist.md](./admin_efector/checklist.md) |
