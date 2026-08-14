# Google Play — Bioenlace Personal de Salud

Borrador para publicación. Package: `com.bioenlace.personalsalud`.

## Título (30 caracteres máx.)

**Bioenlace Personal de Salud**

## Descripción corta (80 caracteres máx.)

Trabajá en tu centro: guardia, consultas, internación y asistente clínico.

## Descripción completa

Pegar en Play Console (texto plano, ~2 300 caracteres; máximo 4 000). Revisores: [../PLAY_APP_ACCESS.md](../PLAY_APP_ACCESS.md).

```
Bioenlace Personal de Salud es la app de trabajo para el equipo del centro: médicos, enfermería, admisión y coordinación. Es la misma plataforma Bioenlace que la web clínica, pensada para usarla en el celular durante la jornada.

Esta aplicación no es para pacientes. Si sos paciente, buscá la app «Bioenlace».

PARA QUIÉN
Necesitás un usuario creado por la administración de tu efector. La app no registra personal: el primer alta lo hace el administrador del centro; si cambiás de institución, te reasignan con el mismo usuario.

Si tenés consultorio propio, el alta se hace en la web (https://bioenlace.io/alta.html?perfil=consultorio). Después operás desde esta app.

QUÉ PODÉS HACER
• Elegir efector, servicio y área de trabajo (ambulatorio, guardia o internación). El inicio cambia según tu rol y el área.
• Ver la agenda del día en ambulatorio y continuar la atención desde el listado de pacientes.
• Documentar la consulta con texto o voz: historia del paciente y captura clínica en un mismo flujo, alineada al servicio del centro.
• En guardia: tablero de la cola, ingresar pacientes (incluido DNI), triage, atender, notas de enfermería y registrar que el paciente se retiró.
• En internación: mapa de camas y evolución en piso.
• Usar el asistente para tareas operativas (horarios, licencias, mapa de camas y otras acciones según tu permiso).
• Recibir notificaciones de guardia y asignaciones, si habilitás el permiso del dispositivo.

REQUISITOS
• Usuario y contraseña provistos por tu institución
• Conexión a internet
• Que tu centro use Bioenlace

PRIVACIDAD Y CONTACTO
Política de privacidad: https://bioenlace.io/privacidad.html
Contacto: info@bioenlace.io

Los datos de salud se tratan en el marco de tu institución y la normativa aplicable. Bioenlace es una herramienta de trabajo clínico y operativo; no sustituye el criterio profesional.
```

## Categoría

Medicina / Productividad

## Notas internas (screenshots sugeridos)

1. Login — «Personal de Salud»
2. Wizard efector / servicio / área
3. Inicio guardia (tablero EMER)
4. Captura clínica / timeline
5. Asistente en chat
6. Mapa de camas (IMP)

## Builds de tienda

**Firma release obligatoria para Play Console** — ver [../RELEASE_SIGNING.md](../RELEASE_SIGNING.md). Sin `android/key.properties`, el AAB queda firmado en debug y Google lo rechaza.

```bash
flutter build appbundle --release
```
