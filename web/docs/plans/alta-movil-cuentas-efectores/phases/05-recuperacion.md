# Fase 05 — Recuperación de acceso

## Objetivo

Recuperar accesos sin convertir e-mail o soporte en un factor de autenticación débil.

## Checklist

- [ ] App: volver a verificar identidad y revocar credenciales/dispositivos anteriores.
- [ ] Web-only: flujo asistido con prueba de identidad, revisión de riesgo y registro de auditoría.
- [ ] Revocar sesiones activas, passkeys anteriores y desafíos pendientes antes de recuperar.
- [ ] Notificar a canales registrados y otros administradores, sin usarlos como autorización única.
- [ ] Exigir enrolar una passkey nueva y presentar recuperación explícita al usuario.
- [ ] Definir escalamiento/hold para señales de fraude o disputas de propiedad de la organización.

## Criterio de salida

La recuperación completa deja evidencia auditable y no puede completarse únicamente con un e-mail no verificado.
