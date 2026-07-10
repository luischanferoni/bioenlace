# Google Play — Bioenlace Personal de Salud

Borrador para publicación. Package: `com.bioenlace.personalsalud`.

## Título (30 caracteres máx.)

**Bioenlace Personal de Salud**

## Descripción corta (80 caracteres máx.)

Trabajá en tu centro: guardia, consultas, internación y asistente clínico.

## Descripción completa

Bioenlace Personal de Salud es la app móvil para el **equipo del centro de salud**: médicos, enfermería, administrativos y coordinación.

**Para quién**
- Personal con usuario asignado por administración del efector.
- Profesionales con consultorio propio: el alta comercial se hace en la web (`https://bioenlace.io/alta.html?perfil=consultorio`); la app es para operar después.
- No incluye registro comercial dentro de la app.

**Qué podés hacer**
- Inicio según tu rol y área de trabajo (ambulatorio, guardia, internación).
- Captura clínica con asistente (texto o voz).
- Tablero de urgencias, triage y atención en guardia.
- Mapa de camas e internación.
- Asistente conversacional para tareas operativas.
- Notificaciones de guardia y asignaciones (con permisos del dispositivo).

**Requisitos**
- Usuario y contraseña provistos por tu institución.
- Conexión a internet.
- Misma plataforma Bioenlace que la web clínica del efector.

**Privacidad**
- Política de privacidad: https://bioenlace.io/privacidad.html
- Detalles de acceso (revisores Google Play): ver [../PLAY_APP_ACCESS.md](../PLAY_APP_ACCESS.md)
- Datos de salud bajo políticas de tu institución y normativa aplicable.
- Contacto institucional: info@bioenlace.io

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
