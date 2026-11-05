# Fase 1 — Vocabulario y carga

## Objetivo

El YAML puede declararse como statechart. Al cargar, el compilador arma el recorrido que los lectores ya ejecutan. Un intent lineal piloto queda migrado.

## Checklist

- [x] Plan en `plans/flow-statechart/`
- [x] Contrato: sección statechart en `SUBINTENT_CONTRACT.md`
- [x] `StatechartManifest` en la carga de intents
- [x] Piloto `plataforma.enviar-queja-como-paciente-flow` (`states`, `type: final`, `meta.open_ui`)
- [x] Test del compilador (guarda, comodín, `initial`, final, contexto)

## Qué no entra

Migrar Solicitar Atención ni hacer que la guía lea `states` (fases 2 y 3).
