import 'dart:async';
import 'dart:ui';

import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:flutter/foundation.dart';

/// Inicialización de Firebase Crashlytics (después de [Firebase.initializeApp]).
class CrashlyticsBootstrap {
  CrashlyticsBootstrap._();

  static bool _configured = false;
  static bool _collectionEnabled = false;

  static bool get isReady => _configured;
  static bool get collectionEnabled => _collectionEnabled;

  /// Llamar una sola vez tras Firebase Core, típicamente desde `FirebaseBootstrap`.
  static Future<bool> configure({String? appTag}) async {
    if (kIsWeb) {
      return false;
    }
    if (_configured) {
      return _collectionEnabled;
    }

    try {
      final crashlytics = FirebaseCrashlytics.instance;
      _collectionEnabled = _shouldCollect();
      await crashlytics.setCrashlyticsCollectionEnabled(_collectionEnabled);

      if (_collectionEnabled) {
        FlutterError.onError = crashlytics.recordFlutterFatalError;
        PlatformDispatcher.instance.onError = (error, stack) {
          unawaited(
            crashlytics.recordError(error, stack, fatal: true),
          );
          return true;
        };
      }

      if (appTag != null && appTag.trim().isNotEmpty) {
        await crashlytics.setCustomKey('app', appTag.trim());
      }

      _configured = true;
      return _collectionEnabled;
    } catch (e, st) {
      debugPrint('CrashlyticsBootstrap.configure falló: $e\n$st');
      return false;
    }
  }

  static bool _shouldCollect() {
    const debugFlag = bool.fromEnvironment(
      'CRASHLYTICS_DEBUG',
      defaultValue: false,
    );
    if (debugFlag) {
      return true;
    }
    // Release/profile en dispositivos reales; debug local sin ruido en consola Firebase.
    return !kDebugMode;
  }

  static Future<void> log(String message) async {
    if (!_configured || !_collectionEnabled) return;
    try {
      await FirebaseCrashlytics.instance.log(message);
    } catch (_) {
      // ignore
    }
  }

  static Future<void> recordError(
    Object error,
    StackTrace? stack, {
    String? reason,
    bool fatal = false,
    Map<String, Object?>? customKeys,
  }) async {
    if (!_configured || !_collectionEnabled) return;
    try {
      final crashlytics = FirebaseCrashlytics.instance;
      if (customKeys != null) {
        for (final entry in customKeys.entries) {
          final v = entry.value;
          if (v == null) continue;
          await crashlytics.setCustomKey(entry.key, v);
        }
      }
      await crashlytics.recordError(
        error,
        stack,
        reason: reason,
        fatal: fatal,
      );
    } catch (_) {
      // ignore
    }
  }

  static Future<void> setUserId(String? userId) async {
    if (!_configured || !_collectionEnabled) return;
    final id = userId?.trim() ?? '';
    if (id.isEmpty) return;
    try {
      await FirebaseCrashlytics.instance.setUserIdentifier(id);
    } catch (_) {
      // ignore
    }
  }
}
