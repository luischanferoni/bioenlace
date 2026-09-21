# Fase 05 — BCs compactos (Geo, Content, Terminology, Programs)

**Estado:** pendiente

## Objetivo

Aplicar **roles CA + sufijos transversales** sin forzar módulos si el BC es mono-capacidad.

## Regla

- Layer-first en la raíz del BC **OK** si hay una sola capacidad.
- `Application/*` = solo roles CA (evitar el anti-patrón Organization).
- Si aparece segunda capacidad → promover a módulo-primero (misma gramática).

## Checklist

- [ ] **Geo** — `Application/Seed` OK como plugin; resto UseCase/Service; naming
- [ ] **Content** — Application vacío/parcial; alinear Domain/Model + Infra
- [ ] **Terminology** — Application/Domain vacíos parcialmente; Infra External OK; naming adapters
- [ ] **Programs** — inventariar si hay código; aplicar misma regla
- [ ] **Integrations/** — confirmar solo README (sin código nuevo)

## No hacer

Inventar módulos fantasma (`Geo/Pais/`, etc.) “por simetría” con Clinical.
