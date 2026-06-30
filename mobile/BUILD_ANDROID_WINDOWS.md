# Build Android en Windows (proyecto en `D:`)

## Error «different roots» (Kotlin + Pub cache)

Si el repo está en `D:\` y el Pub cache en `C:\Users\...\AppData\Local\Pub\Cache`, Gradle/Kotlin puede fallar al compilar plugins (`record_android`, etc.) con:

```text
IllegalArgumentException: this and base files have different roots
```

**Ya aplicado en el repo:** `kotlin.incremental=false` en `android/gradle.properties` de cada app.

**Recomendado a largo plazo:** misma unidad para proyecto y cache:

```text
PUB_CACHE=D:\Pub\Cache
```

Reiniciar terminal/IDE, luego:

```bash
flutter clean
flutter pub get
```

## Error «failed to strip debug symbols»

Flutter **verifica** el `.aab` con `apkanalyzer` (parte de **Android SDK Command-line Tools**). Si no están instalados, Gradle puede terminar bien pero `flutter build appbundle` falla al final.

`flutter doctor` suele mostrar:

```text
X cmdline-tools component is missing.
```

### Solución (recomendada)

1. Android Studio → **Settings** → **Languages & Frameworks** → **Android SDK** → pestaña **SDK Tools**.
2. Marcar **Android SDK Command-line Tools (latest)** → Apply.
3. En terminal:

```bash
flutter doctor --android-licenses
flutter doctor -v
```

4. Rebuild:

```bash
cd mobile/paciente
flutter clean
flutter pub get
flutter build appbundle --release
```

### Si Gradle ya compiló

El bundle suele quedar igual en:

```text
mobile/paciente/build/app/outputs/bundle/release/app-release.aab
```

Podés generarlo con Gradle directo (útil mientras instalás cmdline-tools):

```bash
cd mobile/paciente/android
.\gradlew --stop
.\gradlew :app:bundleRelease
```

### Cierre de archivos en Windows

Si falla `lintVitalAnalyzeRelease` con «archivo en uso»:

1. El proyecto ya desactiva `lint.checkReleaseBuilds` en release (no bloquea el bundle).
2. Si persiste: `cd mobile/paciente/android` → `.\gradlew --stop`, cerrar Android Studio y borrar `build/`.
3. Reintentar `flutter build appbundle --release`.

## Release store

Ver [../RELEASE_SIGNING.md](../RELEASE_SIGNING.md) — **obligatorio** antes de subir a Play Console.
