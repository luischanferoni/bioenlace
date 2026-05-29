# Uso condicional de IA

Reglas, diccionarios y flujos guiados **antes** de llamar al modelo.

## Dónde está hoy

- **Captura clínica (médico):** SymSpell + abreviaturas en CPU (`ProcesadorTextoMedico`), luego IA de análisis. SymSpell **no cubre bien** jerga clínica compleja; el ahorro fuerte ahí no es confiar en SymSpell sino en cuándo llamar a `analizar`.
- **Asistente chat:** preprocess con IA (`normalized_text`, goal); canal operativo con **top-K + reglas PHP** sobre `normalized_text` (**sin** 2.ª IA en el escenario central). Ver [matriz-casos-uso.md](./matriz-casos-uso.md).
- **Onboarding / intents:** priorizar reglas + corpus de frases/keywords (consultas reales como fuente de sinónimos), no SymSpell de consulta completa.

## Reducción orientativa

**30–50 %** del costo de IA si se extienden reglas a más intents con señales claras.

**No incluido** en COGS de impuestos-argentina hasta medir con `AICostTracker` (evitadas por CPU/validación vs llamadas reales).

## Referencias

- [producto/captura-clinica.md](../../producto/captura-clinica.md)
