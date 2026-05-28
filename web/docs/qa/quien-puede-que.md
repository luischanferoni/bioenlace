# Quién puede hacer qué

[← Índice](./README.md)

Resumen en criollo: **paciente** usa la app; **personal** usa la web (y a veces el asistente); algunas cosas las hacen los dos con permisos distintos.

## Por tipo de usuario

| Tema | Paciente (app) | Personal (web) |
|------|----------------|----------------|
| Entrar | Sí, con su usuario | Sí, con usuario staff |
| Elegir sanatorio / servicio | No | Sí, al empezar el día |
| Sacar turno | Sí, para sí | Sí, para cualquier paciente (si tiene permiso) |
| Cancelar / cambiar turno | Sí, con reglas de anticipación | Sí, a veces sin las mismas restricciones |
| Ver laboratorio y recetas | Sí, los propios | No es lo habitual (ve captura en consulta) |
| Tablero de guardia y triage | No | Sí, en servicio de guardia |
| Mapa de camas e internación | No | Sí, en servicio de internación |
| Captura clínica / historia | No (solo ve resúmenes) | Sí |
| Nomencladores y planillas | No | Sí, roles administrativos / médicos |
| Asistente por chat | Sí, flujos de paciente | Sí, flujos de staff |

## Por “modo” del sanatorio (lo que elegís al entrar)

| Modo | Pantalla principal que vas a usar |
|------|-----------------------------------|
| **Ambulatorio** | Turnos, agenda, captura de consultorio |
| **Guardia** | Tablero de urgencias, triage, atender |
| **Internación** | Mapa de camas, ingreso, alta |

Si elegís el modo equivocado, el menú no muestra lo que esperás — volvé a elegir efector y servicio ([00-transversal](./00-transversal.md)).

## Módulos del producto (madurez aproximada)

Referencia de qué tan armado está cada cosa (no es una checklist):

| Área | Paciente | Personal | Asistente |
|------|----------|----------|------------|
| Turnos y agenda | Muy usado | Muy usado | Muchos flujos |
| Guardia / urgencias | — | Muy usado | Tablero y triage |
| Internación | — | Muy usado | Mapa, ingreso, alta |
| Captura y consulta | Resúmenes | Muy usado | Parcial |
| Laboratorio | Ver resultados | Carga / integración variable | Ver resultados (paciente) |
| Receta electrónica | Ver recetas | Emitir en consulta | Ver recetas (paciente) |
| Planes y recordatorios | Sí | Adherencia staff | Sí |
| Quirófano, farmacia, facturación | — | Parcial | Poco o nada en asistente |

Detalle de madurez HIS: [his-completo/](../his-completo/README.md).

## Dónde está cada flujo paso a paso

| Tema | Archivo |
|------|---------|
| Entrar y permisos | [00-transversal.md](./00-transversal.md) |
| Consulta e historia | [01-captura-clinica.md](./01-captura-clinica.md) |
| Turnos | [02-turnos-agenda.md](./02-turnos-agenda.md) |
| Guardia | [03-urgencias-guardia.md](./03-urgencias-guardia.md) |
| Internación | [04-internacion.md](./04-internacion.md) |
| Lab, receta, planes | [05-laboratorio-receta-planes.md](./05-laboratorio-receta-planes.md) |
| Reportes | [06-reportes-nomenclador.md](./06-reportes-nomenclador.md) |
| Frases del asistente | [07-asistente.md](./07-asistente.md) |
