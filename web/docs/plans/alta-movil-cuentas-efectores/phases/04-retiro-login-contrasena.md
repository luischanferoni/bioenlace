# Decisión de login y retiro de flujos legacy

Este documento registra la decisión vigente y reemplaza la propuesta anterior de passkeys como login general.

## Política vigente

- **Frontend:** solo ingreso confirmado desde la app (QR). El formulario de usuario/contraseña no aparece en el navegador porque el controlador no pasa un modelo, pero debe quitarse también del código de la vista junto con su texto. No se ofrece passkey.
- **Admin:** usuario/contraseña, solo para superadmin. El panel no ofrece ingreso con app ni passkey. La restricción a superadmin debe validarse en servidor.
- **App móvil:** crear cuenta/verificar identidad mediante Didit. Los caminos legacy de credenciales y activación por código se retiran del cliente.
- **Alta institucional:** el endpoint requiere identidad móvil autenticada y checkout Mercado Pago; no conservar el pago simulado como fallback.

## Limpieza de cliente y servidor

- [ ] Quitar del cliente móvil las llamadas y pantallas de `auth/registrar`, `auth/login-credenciales` y `auth/activar-cuenta`, junto con helpers sin otros usos.
- [ ] Confirmar por búsqueda de referencias que ningún cliente mantenido usa esos flujos; después retirar rutas y acciones server-side que solo respondían `410 Gone`.
- [ ] Mantener los flujos vigentes de registro Didit en la app.
- [ ] Mantener por separado el alta asistida de pacientes con lectura física de DNI; no usarla para crear o habilitar personal de un efector.
- [ ] Retirar o proteger `POST /api/v1/persona/create`, que hoy permite crear Persona con campos POST sin una prueba de lector físico ni `verification_id`.
- [ ] Eliminar cualquier ruta/camino de alta de efector que permita crear Persona/User desde datos enviados por el cliente sin verificación Didit.
- [ ] Retirar llamadas de cliente y endpoints genéricos que no tengan consumidores legítimos; no borrar rutas compartidas por otros casos de uso sin verificar referencias.

## Criterio de salida

No quedan llamadas ni UI legacy en clientes soportados, ni endpoints públicos obsoletos que acepten altas no verificadas. Se conservan el registro Didit móvil y los flujos asistidos de paciente autorizados. Frontend no contiene formulario de contraseña ni ingreso con passkey; Admin queda restringido a superadmin.
