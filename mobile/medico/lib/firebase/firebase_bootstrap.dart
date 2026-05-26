import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';

import '../firebase_options.dart';

/// Inicialización única de Firebase (app médico).
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
        await Firebase.initializeApp();
      }
      _initialized = true;
      return true;
    } catch (e, st) {
      debugPrint('Firebase.initializeApp (médico) falló: $e\n$st');
      return false;
    }
  }
}
