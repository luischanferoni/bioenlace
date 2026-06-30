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
- No incluye registro en la app: el administrador del centro crea tu acceso la primera vez.

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
