# Fase 01 — UX móvil y alta comercial unificada

## Objetivo

Guiar al titular por una sola configuración de espacio de atención, con una cuenta personal gratuita como recorrido separado.

## Trabajo

- [ ] Reemplazar los acordeones de cuenta profesional/consultorio/clínica por las opciones «Crear cuenta personal gratis» y «Crear espacio de atención».
- [ ] Mover configuración comercial después de Didit y de crear/recuperar Persona/User.
- [ ] Crear un flujo por pasos con guardado de progreso: sector, datos del efector, ministerio/SISA si público, servicios/volúmenes, precio y confirmación de pago.
- [ ] Dar valores iniciales útiles, permitir editarlos y explicar 200 AMB como volumen mensual aproximado de un profesional, sin expresarlo como límite.
- [ ] Mantener lenguaje de usuario: no exponer Didit, MEP, COGS, REFEPS mock u otros detalles internos en el onboarding comercial.
- [ ] Renderizar cotización del backend como fuente única; mostrar mensualidad, impuestos, renovación y cancelación antes del checkout.
- [ ] Mostrar estados recuperables claros para borrador guardado, precio no disponible, efector público no elegible, pago pendiente y pago rechazado.
- [ ] No iniciar Didit ni crear Persona/User si el usuario solo explora precios y no decide crear cuenta/espacio.

## Criterios de aceptación

- El usuario puede elegir 200 AMB con un profesional o con más personal; el volumen no se transforma en un máximo de staff.
- Público exige ministerio y selección de efector SISA; privado presenta sus datos de alta correspondientes.
- El usuario entiende que crear identidad no habilita el espacio: debe completar pago aprobado.
- El mismo contrato de plan alimenta cotización, borrador y checkout, sin cálculo local de importes.
