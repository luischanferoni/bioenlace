# Overview — Alta unificada de espacios de atención

## Objetivo

Unificar el alta paga de consultorios y clínicas/centros en un único espacio de atención configurable por sector, servicios y volumen. La identidad se verifica primero; la licencia y AdminEfector se habilitan únicamente después de confirmar el primer pago.

## Recorridos

| Recorrido | Identidad | Pago | Capacidades |
|-----------|-----------|------|-------------|
| Cuenta personal gratuita | Didit desde Personal de Salud; crear o recuperar Persona/User | No | Capacidad de paciente en la app paciente. Sin rol profesional ni acceso a Personal de Salud; un AdminEfector puede incorporarlo después |
| Crear espacio de atención | Didit; Persona/User se crean o recuperan antes de configurar el efector | Suscripción mensual en ARS; habilitación solo tras webhook aprobado | Antes del pago: borrador y checkout, sin AdminEfector ni licencia activa. Después: licencia, AdminEfector y acceso al asistente administrativo |
| Incorporarse como profesional | AdminEfector busca una persona existente de origen `MOBILE_APP`; REFEPS según la política vigente | Cubierto por la licencia aplicable | Asignación clínica explícita y separada del rol AdminEfector |

## Recorrido comercial

1. La persona elige «Crear espacio de atención» e inicia Didit.
2. La API valida la verificación y crea o recupera Persona/User sin asignar capacidades profesionales.
3. La persona configura el efector. Indica si es público o privado. En público selecciona ministerio y efector oficial SISA, sujeto a las reglas de pertenencia y conflicto actuales.
4. Elige tipos de atención y volúmenes mensuales, consulta el precio y confirma la suscripción.
5. Se crea o recupera el checkout persistido. Mientras esté pendiente, no se crea AdminEfector ni se activa la licencia.
6. Un webhook firmado y conciliado confirma el pago. La activación crea/activa efector, licencia y asignación AdminEfector idempotentemente.
7. El titular entra en Personal de Salud como AdminEfector y el asistente le guía para configurar servicios y asignar personal. Si quiere atender, se incorpora como profesional mediante el flujo clínico independiente.

Los 200 AMB pueden ofrecerse como volumen inicial orientativo, equivalente aproximadamente a la carga de un profesional. No representan un límite de personal ni una categoría de producto.

## Reanudación

- Si Didit fue aprobado y falló la creación de la cuenta, reintentar la API con el `verification_id` temporal guardado en almacenamiento seguro del dispositivo. La API vuelve a consultar Didit; no se confía en el valor local sin validación.
- Si Persona/User ya fue creado, la app no abre una sesión de paciente en Personal de Salud. En cada inicio la persona verá el login; desde «Crear cuenta» podrá volver a Didit, identificarse y recuperar su borrador.
- Si la app conserva datos locales de onboarding, puede volver directamente al paso guardado después de revalidar la identidad. El servidor sigue siendo la fuente de verdad.
- Desde otro dispositivo o después de reinstalar, una nueva verificación Didit identifica a la Persona existente y permite recuperar el borrador sin duplicar la cuenta.
- Un checkout pendiente se recupera/reabre o se reemplaza según su estado, sin crear cobros o suscripciones duplicados.

## Fuera de alcance

- Dar acceso a Personal de Salud a cuentas con solo capacidad/rol Paciente.
- Asignar automáticamente un rol clínico al propietario por pagar.
- Conservar `CONSULTORIO` y `CLINICA` como variantes de producto o contratos nuevos por compatibilidad.
- Quitar las validaciones de efector público, ministerio o SISA.
- Anualidad, expansión comercial fuera de Argentina o cambio de proveedor de pagos.
