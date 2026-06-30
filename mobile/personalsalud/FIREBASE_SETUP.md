# FCM — app Personal de Salud (`com.bioenlace.personalsalud`)

## 1. Proyecto en Firebase Console

1. [Firebase Console](https://console.firebase.google.com/) → crear o abrir el proyecto.
2. **Agregar app Android**
   - Package name: `com.bioenlace.personalsalud`
   - Descargar `google-services.json` → copiar a `android/app/google-services.json`
3. **Agregar app iOS**
   - Bundle ID: `com.bioenlace.personalsalud`
   - Descargar `GoogleService-Info.plist` → copiar a `ios/Runner/GoogleService-Info.plist`
4. En el proyecto: **Build → Cloud Messaging** → habilitar API si lo pide.
5. **Backend PHP** — mismo bloque `fcmPush` que paciente en `web/common/config/params-local.php` (proyecto Firebase compartido).

> Los archivos `google-services.json`, plist y `credentials/*.json` **no** se commitean (el JSON en repo puede ser placeholder hasta `flutterfire configure`).

## 2. Opción recomendada: FlutterFire CLI

Desde `mobile/personalsalud`:

```bash
dart pub global activate flutterfire_cli
flutterfire configure --project=TU_PROYECTO_FIREBASE
```

```bash
flutter pub get
flutter run
```

## 3. Probar push

1. Login en la app (`appClient: personalsalud-flutter`).
2. API: `POST /api/v1/devices/push-token` con `push_provider=fcm`.
3. Eventos de guardia (`EMERGENCY_*`) y asignaciones según reglas del efector.

## 4. Crashlytics

Tag de app: `personalsalud`. Ver sección Crashlytics en `mobile/paciente/FIREBASE_SETUP.md` (mismo patrón).
