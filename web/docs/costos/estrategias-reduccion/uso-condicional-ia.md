# Uso condicional de IA

Reglas, diccionarios y flujos guiados **antes** de llamar al modelo. Contextos: [catálogo de IA](../../producto/catalogo-usos-ia.md).

## Dónde está hoy

- **`analisis-consulta` (captura clínica):** SymSpell + abreviaturas en CPU (`ProcesadorTextoMedico`), luego IA de análisis. SymSpell **no cubre bien** jerga clínica compleja; el ahorro fuerte ahí no es confiar en SymSpell sino en cuándo llamar a `analizar`.
- **`encounter-codificacion-automatica`:** solo al **guardar** encounter con texto clínico; si `encounter_auto_codificacion_habilitada` es false, no invoca IA.
- **Asistente chat (`asistente-preprocess`, `asistente-conversational`):** preprocess con IA; canal operativo con **top-K + reglas PHP** sobre `normalized_text` (**sin** 2.ª IA en el escenario central). Ver [matriz-casos-uso.md](./matriz-casos-uso.md).
- **Onboarding / `intent-engine-classification`:** priorizar reglas + corpus de frases/keywords (consultas reales como fuente de sinónimos), no SymSpell de consulta completa.

## Reducción orientativa

**30–50 %** del costo de IA si se extienden reglas a más intents con señales claras.

**No incluido** en COGS de impuestos-argentina hasta medir con `AICostTracker` (evitadas por CPU/validación vs llamadas reales).

## Referencias

- [producto/captura-clinica.md](../../producto/captura-clinica.md)
