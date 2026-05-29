# Uso condicional de IA

Reglas, diccionarios y flujos guiados **antes** de llamar al modelo.

## Dónde está hoy

- Corrección: SymSpell + diccionario; IA solo si hace falta (`SymSpellCorrector`, `IAManager::corregirPalabra`).
- Asistente: preprocess heurístico + canal operativo (reglas/keywords) antes de IA conversacional o clasificación.
- Clasificación de intents: reglas primero, IA como fallback (`IntentClassifier`).

## Reducción orientativa

**30–50 %** del costo de IA si se extienden reglas a más intents con señales claras.

**No incluido** en COGS de impuestos-argentina hasta medir con `AICostTracker` (evitadas por CPU/validación vs llamadas reales).

## Referencias

- [producto/captura-clinica.md](../../producto/captura-clinica.md)
