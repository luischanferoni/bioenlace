# FCM — app Personal de Salud (`com.bioenlace.personalsalud`)

## 1. Android (prioridad actual)

1. [Firebase Console](https://console.firebase.google.com/) → proyecto **august-cirrus-482714-f4** (o el vuestro).
2. **Agregar app Android** (si no existe) con package `com.bioenlace.personalsalud`.
3. Descargar `google-services.json` → `android/app/google-services.json`.
4. Verificar que el JSON incluye un cliente con `"package_name": "com.bioenlace.personalsalud"`.
5. **Backend PHP** — bloque `fcmPush` en `web/common/config/params-local.php` (mismo proyecto Firebase que paciente).

El plugin Gradle solo se activa si el JSON contiene `com.bioenlace.personalsalud` (ver `android/app/build.gradle.kts`).

> `google-services.json` con secretos **no** debería commitearse en producción; en dev puede quedar en el repo local.

## 2. iOS (diferido)

Cuando se habilite iOS:

- Bundle ID: `com.bioenlace.personalsalud`
- Descargar `GoogleService-Info.plist` → `ios/Runner/GoogleService-Info.plist`
- Capabilities: Push Notifications, Background Modes → Remote notifications
- APNs en Firebase Console

Ver patrón completo en `mobile/paciente/FIREBASE_SETUP.md`.

## 3. FlutterFire CLI (opcional)

Automatiza JSON + plist + `lib/firebase_options.dart`. Requiere [Firebase CLI](https://firebase.google.com/docs/cli#install_the_firebase_cli) instalado:

```bash
npm install -g firebase-tools
firebase login
cd mobile/personalsalud
dart pub global activate flutterfire_cli
flutterfire configure --project=august-cirrus-482714-f4
```

**No es obligatorio** si configurás Android manualmente (como ya hiciste).

## 4. Probar push (Android)

1. Login en la app (`appClient: personalsalud-flutter`).
2. API: `POST /api/v1/devices/push-token` con `push_provider=fcm`.
3. Eventos de guardia (`EMERGENCY_*`) según reglas del efector.

## 5. Crashlytics

Tag de app: `personalsalud`. Mismo patrón que paciente en `mobile/paciente/FIREBASE_SETUP.md` §8.
