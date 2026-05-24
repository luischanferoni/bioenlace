# Overview — Receta electrónica

## Objetivo

Implementar el circuito de **receta emitida** en Bioenlace, separado de `MedicationRequest` (orden clínica), alineado al perfil nacional MSAL y preparado para firma e integración con repositorio oficial en fases posteriores.

## Modos de producto

| Modo | Descripción |
|------|-------------|
| **A — MVP interno** (Fase 1) | Borrador → emitida → anulada en BD; número propio; sin farmacia ni repositorio nacional |
| **B — Circuito oficial** (Fase 3+) | Emisión/validación vía operador Receta Digital / provincial |
| **C — Híbrido** | A ahora, B cuando haya credenciales y contrato |

## Actores

- **Médico** (staff con PES en encounter): crea borrador, emite, anula.
- **Paciente**: consulta recetas emitidas propias.
- **Operaciones** (futuro): conciliación con repositorio.

## Fuera de alcance inicial

- Firma digital homologada (Fase 2).
- Dispensación en farmacia (Fase 3).
- Asistente conversacional para emitir (Fase 4).
- Receta de lentes como subtipo (mantener `vision_prescription` aparte).

## Fases

Ver carpeta [phases/](./phases/).
