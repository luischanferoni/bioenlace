# Overview — ddd-norte-org-person-sched

Oleada después del empaquetado transversal: **intenciones en UseCase/** y **Infra tipada** en Organization, Person y Scheduling/Agenda.

## Alcance

- In: Org (`Pes`, `Efector`), Person (`Identity`, `FrontDesk`), Agenda Infra FHIR + Port directions.
- Out: Clinical, BCs compactos (salvo docs), SesionOperativa refactor profundo.

## Orden de ejecución

1. Fase 01 Org UseCases  
2. Fase 02 Person UseCases  
3. Fase 03 Agenda Infra  
4. Fase 04 Docs stale  

Cada fase = commits del usuario; el agente no commitea solo.
