// Generado con `flutterfire configure` o completar con --dart-define (ver FIREBASE_SETUP.md).
import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;

/// Opciones de Firebase para [com.bioenlace.paciente].
class DefaultFirebaseOptions {
  static const String _unset = 'REPLACE_ME';

  /// true cuando hay valores reales (flutterfire configure o dart-define).
  static bool get isConfigured {
    if (kIsWeb) return false;
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return !_isPlaceholder(android.apiKey) && !_isPlaceholder(android.appId);
      case TargetPlatform.iOS:
        return !_isPlaceholder(ios.apiKey) && !_isPlaceholder(ios.appId);
      case TargetPlatform.macOS:
        return !_isPlaceholder(ios.apiKey) && !_isPlaceholder(ios.appId);
      default:
        return false;
    }
  }

  static bool _isPlaceholder(String value) {
    final v = value.trim();
    return v.isEmpty || v == _unset || v.startsWith('REPLACE');
  }

  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      throw UnsupportedError('FCM no está habilitado en web para esta app.');
    }
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      case TargetPlatform.macOS:
        return ios;
      default:
        throw UnsupportedError(
          'Plataforma no soportada para Firebase: $defaultTargetPlatform',
        );
    }
  }

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: String.fromEnvironment(
      'FIREBASE_ANDROID_API_KEY',
      defaultValue: 'AIzaSyAVQmQ1KiRtDKQiLUcwgWQGMHwbogC7u94',
    ),
    appId: String.fromEnvironment(
      'FIREBASE_ANDROID_APP_ID',
      defaultValue: '1:684325222184:android:8d9f090b95eec51976421d',
    ),
    messagingSenderId: String.fromEnvironment(
      'FIREBASE_MESSAGING_SENDER_ID',
      defaultValue: '684325222184',
    ),
    projectId: String.fromEnvironment(
      'FIREBASE_PROJECT_ID',
      defaultValue: 'august-cirrus-482714-f4',
    ),
    storageBucket: String.fromEnvironment(
      'FIREBASE_STORAGE_BUCKET',
      defaultValue: 'august-cirrus-482714-f4.firebasestorage.app',
    ),
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: String.fromEnvironment(
      'FIREBASE_IOS_API_KEY',
      defaultValue: _unset,
    ),
    appId: String.fromEnvironment(
      'FIREBASE_IOS_APP_ID',
      defaultValue: _unset,
    ),
    messagingSenderId: String.fromEnvironment(
      'FIREBASE_MESSAGING_SENDER_ID',
      defaultValue: _unset,
    ),
    projectId: String.fromEnvironment(
      'FIREBASE_PROJECT_ID',
      defaultValue: _unset,
    ),
    storageBucket: String.fromEnvironment(
      'FIREBASE_STORAGE_BUCKET',
      defaultValue: '',
    ),
    iosBundleId: 'com.bioenlace.paciente',
  );
}
