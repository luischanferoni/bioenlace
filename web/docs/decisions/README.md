# Decisiones (ADR)

Registro de decisiones **cerradas** que afectan a más de un módulo o que conviene no repetir en cada `design.md`.

| ID | Título | Ubicación |
|----|--------|-----------|
| Dominio clínico FHIR | Producto, modelo, API clínica, greenfield | [fhir-clinical.md](./fhir-clinical.md) |
| Protocolos de cuidado | PlanDefinition-lite en BD (Nación/Provincia); ABM superadmin | [care-protocols-plandefinition-lite.md](./care-protocols-plandefinition-lite.md) |
| Autorización solo por intents | RBAC assignable = intent_id; retiro grants atributo | [autorizacion-solo-por-intents.md](./autorizacion-solo-por-intents.md) |
| Onboarding comercial | Self-service AdminEfector; ministerio asistido; sector vs pago | [onboarding-comercial-self-service.md](./onboarding-comercial-self-service.md) |

## Formato sugerido para nuevas entradas

Crear `NNNN-titulo-corto.md` en esta carpeta con:

1. Contexto  
2. Decisión  
3. Alternativas consideradas y por qué se descartaron  
4. Consecuencias  

No reemplazar entradas antiguas: añadir un archivo nuevo si la decisión cambia.
