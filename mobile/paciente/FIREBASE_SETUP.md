# FCM — app paciente (`com.bioenlace.paciente`)

## 1. Proyecto en Firebase Console

1. [Firebase Console](https://console.firebase.google.com/) → crear o abrir el proyecto (p. ej. el mismo de GCP si ya usan Cloud).
2. **Agregar app Android**
   - Package name: `com.bioenlace.paciente`
   - Descargar `google-services.json` → copiar a `android/app/google-services.json`
3. **Agregar app iOS**
   - Bundle ID: `com.bioenlace.paciente`
   - Descargar `GoogleService-Info.plist` → copiar a `ios/Runner/GoogleService-Info.plist`
4. En el proyecto: **Build → Cloud Messaging** → habilitar API si lo pide.
5. **Backend PHP** (proyecto Firebase **separado** de `google_cloud_*` / Vertex):

En `web/common/config/params-local.php` usá el bloque **`fcmPush`** (push de toda la plataforma; no mezclar con `google_cloud_*` de integracion-voz-del-agro):

```php
'fcmPush' => [
    'projectId' => 'august-cirrus-482714-f4',
    'credentialsPath' => __DIR__ . '/credentials/august-cirrus-fcm.json',
    'fcmServerKey' => null,
    'httpEndpoint' => null,
],
```

- Creá `web/common/config/credentials/august-cirrus-fcm.json` con una **cuenta de servicio** del proyecto **august-cirrus** (GCP → IAM → cuenta de servicio → clave JSON). Rol recomendado: *Firebase Cloud Messaging Admin* o permiso `firebasecloudmessaging.messages.create`.
- El backend usa **FCM HTTP v1** con ese JSON. Opcional: `fcmServerKey` (legacy) del mismo proyecto Firebase si no querés cuenta de servicio.

> Los archivos `google-services.json`, plist y `credentials/*.json` **no** se commitean.

## 2. Opción recomendada: FlutterFire CLI

Desde `mobile/paciente`:

```bash
dart pub global activate flutterfire_cli
flutterfire configure --project=TU_PROYECTO_FIREBASE
```

Esto regenera `lib/firebase_options.dart` con los valores correctos. Luego:

```bash
flutter pub get
flutter run
```

## 3. Opción manual (sin flutterfire)

1. Copiar los JSON/plist de la consola (paso 1).
2. Compilar pasando defines (CI o local):

```bash
flutter run \
  --dart-define=FIREBASE_ANDROID_API_KEY=... \
  --dart-define=FIREBASE_ANDROID_APP_ID=1:...:android:... \
  --dart-define=FIREBASE_IOS_API_KEY=... \
  --dart-define=FIREBASE_IOS_APP_ID=1:...:ios:... \
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=... \
  --dart-define=FIREBASE_PROJECT_ID=...
```

## 4. Android

- Plugin Google Services se aplica solo si existe `android/app/google-services.json`.
- Permiso `POST_NOTIFICATIONS` (Android 13+): la app lo pide al iniciar push.
- Canal por defecto: `bioenlace_turnos` (creado en `MainActivity`).

## 5. iOS (Xcode)

1. Abrir `ios/Runner.xcworkspace`.
2. Target **Runner** → **Signing & Capabilities** → **+ Capability**:
   - **Push Notifications**
   - **Background Modes** → marcar **Remote notifications**
3. Subir clave APNs en Firebase: Project settings → Cloud Messaging → Apple app configuration (clave `.p8` o certificado).
4. Para **release/TestFlight**, en `Runner.entitlements` cambiar `aps-environment` de `development` a `production` (o usar perfiles con entitlements automáticos).

## 6. Probar end-to-end

1. Login en la app paciente (JWT válido).
2. En logs de debug debería aparecer registro FCM; en API: `POST /api/v1/devices/push-token` con `push_provider=fcm`.
3. Desde backend (turno en resolución) o con curl a FCM legacy usando el token del dispositivo y la `fcmServerKey` de `params-local.php`.

## 7. Troubleshooting

| Síntoma | Revisar |
|--------|---------|
| `Firebase no configurado` en log | Falta `google-services.json` / plist o `firebase_options` con `REPLACE_ME` |
| Build Android falla en Google Services | Package en JSON ≠ `com.bioenlace.paciente` |
| Token null en emulador | Usar dispositivo físico o emulador con Google Play |
| Push no llega en iOS | APNs en Firebase, capability Push, perfil de provisioning |
| Backend no envía | `fcmPush` en `params-local.php` (credentialsPath o fcmServerKey) |

## 8. Firebase Crashlytics

Crashlytics está integrado en **paciente** y **médico** (crashes + logs del asistente vía `AppDiagnosticLog`).

### Consola

1. Firebase Console → **Build → Crashlytics** → habilitar si lo pide.
2. Los reportes aparecen tras instalar un build **release/profile** (en debug local la recolección está desactivada por defecto).

### Probar en debug

```bash
flutter run --dart-define=CRASHLYTICS_DEBUG=true
```

Forzar un crash de prueba (solo desarrollo):

```dart
FirebaseCrashlytics.instance.crash();
```

### Android

- Requiere `google-services.json` y plugins Gradle (`google-services` + `firebase-crashlytics`).
- Tras el primer crash, la consola puede tardar unos minutos en mostrar datos.

### iOS

- Mismo `GoogleService-Info.plist` que FCM.
- Para builds release, Xcode debe subir dSYM (FlutterFire / archive en Xcode lo gestiona en la mayoría de casos).

### Identificación de usuario

Tras login se llama `CrashlyticsBootstrap.setUserId(userId)` para correlacionar crashes con el usuario (sin datos clínicos en el log).

### Logs de diagnóstico en servidor

La app envía eventos de flow/UI a `POST /api/v1/client-diagnostic/registrar` (cualquier usuario autenticado).

Archivos en el servidor: `web/runtime/logs/client-diagnostic/client-diagnostic-YYYY-MM-DD.log` (JSON por línea).

Categorías útiles: `ui_json_load` (`start`, `ok`, `retry`, `load_fail`, `load_stuck`), `flow_auto_pick` (`definition_ready`, `chat_advance_ok`, `chat_advance_failed`).
