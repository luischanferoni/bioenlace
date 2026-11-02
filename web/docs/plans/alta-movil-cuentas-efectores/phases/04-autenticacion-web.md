# Fase 04 — Acceso web: app en frontend y superadmin en Admin

## Objetivo

Mantener los canales web separados según lo acordado: frontend solo permite confirmar el ingreso desde la app; el panel Admin conserva usuario/contraseña exclusivamente para superadmin.

## Decisiones y checklist

- [x] Frontend ofrece “Ingresar con la app” mediante aprobación QR.
- [x] El panel Admin no ofrece passkey ni ingreso con app; conserva usuario/contraseña.
- [ ] Eliminar de la vista frontend el bloque PHP del formulario de usuario/contraseña y el texto que lo anuncia, aunque hoy no se rendericen porque `actionLogin()` no pasa un modelo.
- [ ] Retirar de la vista frontend todo CTA de passkey; no reintroducirlo como fallback.
- [ ] Asegurar en servidor que el acceso al panel Admin quede restringido a superadmin, no solo ocultar métodos alternativos en la interfaz.
- [ ] Verificar cómo ingresan los AdminEfector a frontend antes de habilitar clínicas: la app es el mecanismo de aprobación web, pero la política actual de acceso móvil excluye AdminEfector.
- [ ] Preservar el carácter de un solo uso, la expiración y el vínculo del desafío QR con la sesión/navegador.
- [ ] Mantener recuperación asistida donde corresponda sin convertir e-mail o contraseña en un mecanismo de acceso alternativo en frontend.
- [ ] Agregar pruebas de vista/controlador que aseguren que frontend no emite formulario de contraseña ni CTA de passkey y que Admin rechaza usuarios que no sean superadmin.

## Fuera de alcance de esta fase

- No retirar el mecanismo QR de frontend mientras el ingreso desde la app siga siendo el canal acordado.
- No habilitar a AdminEfector en la app sin una decisión explícita de producto y autorización server-side.

## Criterio de salida

Frontend solo ofrece aprobación con la app y no muestra formulario de contraseña ni CTA de passkey. Admin admite usuario/contraseña únicamente para superadmin. Está resuelto y probado el acceso web de AdminEfector antes del alta comercial de clínicas.
