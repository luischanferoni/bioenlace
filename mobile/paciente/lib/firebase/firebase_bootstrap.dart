import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';
import 'package:shared/diagnostics/crashlytics_bootstrap.dart';

import '../firebase_options.dart';

/// Inicialización única de Firebase (main + handlers en background).
class FirebaseBootstrap {
  FirebaseBootstrap._();

  static bool _initialized = false;

  static bool get isReady => _initialized;

  static Future<bool> ensureInitialized() async {
    if (kIsWeb) {
      return false;
    }
    if (_initialized) {
      return true;
    }
    try {
      if (DefaultFirebaseOptions.isConfigured) {
        await Firebase.initializeApp(
          options: DefaultFirebaseOptions.currentPlatform,
        );
      } else {
        // google-services.json / GoogleService-Info.plist en carpetas nativas.
        await Firebase.initializeApp();
      }
      _initialized = true;
      await CrashlyticsBootstrap.configure(appTag: 'paciente');
      return true;
    } catch (e, st) {
      debugPrint('Firebase.initializeApp falló: $e\n$st');
      return false;
    }
  }
}
