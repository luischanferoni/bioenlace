# Decisiones (ADR)

Registro de decisiones **cerradas** que afectan a más de un módulo o que conviene no repetir en cada `design.md`.

| ID | Título | Ubicación |
|----|--------|-----------|
| Dominio clínico FHIR | Producto, modelo, API clínica, greenfield | [fhir-clinical.md](./fhir-clinical.md) |
| Autorización solo por intents | RBAC assignable = intent_id; retiro grants atributo | [autorizacion-solo-por-intents.md](./autorizacion-solo-por-intents.md) |

## Formato sugerido para nuevas entradas

Crear `NNNN-titulo-corto.md` en esta carpeta con:

1. Contexto  
2. Decisión  
3. Alternativas consideradas y por qué se descartaron  
4. Consecuencias  

No reemplazar entradas antiguas: añadir un archivo nuevo si la decisión cambia.
