# Fase 6 — Invariantes de forma y limpieza

## Objetivo

Que el espejo deje de depender de la memoria: tests que fallan cuando una palabra queda huérfana o fuera de forma.

## Tareas

### 6.1 Test de forma del árbol

- [ ] El conjunto de dominios sale de `components/Domain/` (única fuente)
- [ ] Para `models/`, `controllers/`, `views/json/`, `metadata/bioenlace/`, `tests/unit/`: el primer nivel es un dominio, `platform`, o está en la lista de excepciones documentadas
- [ ] Una sola grafía por dominio (falla `persona` si el dominio es `person`)
- [ ] Sin archivos planos en las capas que ya se ordenaron

### 6.2 Test de dependencias

- [ ] Ningún dominio importa el namespace de otro salvo los casos inventariados en Fase 2
- [ ] `components/Platform/` no importa `components/Domain/<X>` salvo por registry/interfaz

### 6.3 Limpieza

- [ ] Borrar mapas y constantes que quedaron sin uso (Fases 3–5)
- [ ] `common/components/Domain/Platform/` no existe
- [ ] Revisar que ninguna doc estable enlace a `plans/`

### 6.4 Reglas y documentación

- [ ] Corregir `.cursor/rules/arquitectura-yii2-bioenlace.mdc`: hoy dice `components/{Clinical|Scheduling|Person|Organization|Core|Ui}/…`, y el árbol real es `components/Domain/<Dominio>` + `components/Platform/<Área>`
- [ ] Extender `common-components-organizacion.mdc` (ya define Platform vs Domain) con la invariante de forma para las demás capas
- [ ] Volcar la invariante a `docs/arquitectura/` (queda cuando se borre este plan)
- [ ] Borrar `docs/plans/arbol-espejo-dominios/` y quitar la fila de `docs/plans/README.md`

## Criterios de aceptación

- [ ] Agregar una carpeta fuera de forma hace fallar la suite con un mensaje que dice dónde
- [ ] Las reglas del proyecto describen el árbol real
- [ ] La invariante vive en `arquitectura/`, no en un plan

## PR sugerido

`test(arch): invariantes de forma dominio/plataforma + limpieza de mapas`
