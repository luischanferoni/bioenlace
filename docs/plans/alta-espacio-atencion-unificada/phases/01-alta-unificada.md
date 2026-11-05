# Fase 01 — UX móvil y alta comercial unificada

## Objetivo

Guiar al titular por una sola configuración de espacio de atención, con una cuenta personal gratuita como recorrido separado.

## Trabajo

- [x] Reemplazar los acordeones de cuenta profesional/consultorio/clínica por las opciones «Crear cuenta personal gratis» y «Crear espacio de atención».
- [x] Mover configuración comercial después de Didit y de crear/recuperar Persona/User.
- [x] Crear un flujo por pasos: sector, datos del efector, ministerio/SISA si público, servicios/volúmenes y precio/pago.
- [x] Guardar y recuperar el progreso del alta.
- [x] Dar valores iniciales útiles, permitir editarlos y explicar 200 AMB como volumen mensual aproximado de un profesional, sin expresarlo como límite.
- [x] Mantener lenguaje de usuario: no exponer Didit, MEP, COGS, REFEPS mock u otros detalles internos en el onboarding comercial.
- [x] Renderizar cotización del backend como fuente única; mostrar mensualidad, impuestos, renovación y cancelación antes del checkout.
- [x] Mostrar estados recuperables claros para borrador guardado, precio no disponible, efector público no elegible, pago pendiente y pago rechazado.
- [x] No iniciar Didit ni crear Persona/User si el usuario solo explora precios y no decide crear cuenta/espacio.

## Criterios de aceptación

- [x] El usuario puede elegir 200 AMB con un profesional o con más personal; el volumen no se transforma en un máximo de staff.
- [x] Público exige ministerio y selección de efector SISA; privado presenta sus datos de alta correspondientes.
- [x] El usuario entiende que crear identidad no habilita el espacio: debe completar pago aprobado.
- [x] El mismo contrato de plan alimenta cotización, borrador y checkout, sin cálculo local de importes.
