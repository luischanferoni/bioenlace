# Bioenlace Personal de Salud (`mobile/personalsalud`)

App móvil Flutter para el **personal de salud** del efector: médicos, enfermería, administrativos y roles clínicos con sesión operativa. Comparte API v1 y `packages/shared` con la app paciente.

| Identificador | Valor |
|---------------|--------|
| Paquete Dart | `personalsalud` |
| Android / iOS bundle | `com.bioenlace.personalsalud` |
| Cabecera API `X-App-Client` | `personalsalud-flutter` (UI JSON asistente: `bioenlace-personalsalud`) |

## Desarrollo

```bash
cd mobile/personalsalud
flutter pub get
flutter run
```

Firebase / FCM: ver [FIREBASE_SETUP.md](./FIREBASE_SETUP.md).

## Alcance

Paridad con el **frontend web clínico** (sin superadmin `/admin`): inicio dinámico (`GET /home/panel`), asistente, captura encounter, guardia, internación.

Alta **solo médicos nuevos** vía REFEPS en pantalla de registro; el resto del personal ingresa con usuario del centro.
