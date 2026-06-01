import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'client_diagnostic_api.dart';
import 'crashlytics_bootstrap.dart';

/// Diagnóstico orientado a **problemas del usuario**, no trazas de cada paso OK.
///
/// - [reportIssue]: persiste, sube al servidor y (en release) breadcrumb/no fatal en Crashlytics.
/// - [trace]: solo `debugPrint` en modo debug; no inunda servidor ni Crashlytics.
class AppDiagnosticLog {
  AppDiagnosticLog._();

  static const _prefsKey = 'bio_app_diagnostic_log_v1';
  static const _maxEntries = 80;

  static bool _uploadScheduled = false;
  static DateTime? _lastUploadAt;
  static String? _lastDedupeKey;
  static DateTime? _lastDedupeAt;

  /// Fallo o bloqueo que el usuario puede notar (flow, UI JSON, red).
  static Future<void> reportIssue(
    String category,
    String message, {
    Map<String, dynamic>? data,
  }) async {
    final dedupeKey = '$category::$message';
    if (_isDuplicate(dedupeKey)) {
      return;
    }

    final entry = <String, dynamic>{
      'ts': DateTime.now().toUtc().toIso8601String(),
      'level': 'issue',
      'category': category,
      'message': message,
      if (data != null && data.isNotEmpty) 'data': data,
    };

    if (kDebugMode) {
      debugPrint('[issue:$category] $message ${data ?? ''}');
    }

    final crashLine = data != null && data.isNotEmpty
        ? '[$category] $message ${jsonEncode(data)}'
        : '[$category] $message';
    unawaited(CrashlyticsBootstrap.log(crashLine));
    unawaited(CrashlyticsBootstrap.recordError(
      Exception(crashLine),
      StackTrace.current,
      reason: 'logic_$category',
      fatal: false,
      customKeys: data?.map((k, v) => MapEntry(k, v?.toString())),
    ));

    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_prefsKey);
      final list = <Map<String, dynamic>>[];
      if (raw != null && raw.isNotEmpty) {
        final decoded = jsonDecode(raw);
        if (decoded is List) {
          for (final e in decoded) {
            if (e is Map) {
              list.add(Map<String, dynamic>.from(e));
            }
          }
        }
      }
      list.add(entry);
      while (list.length > _maxEntries) {
        list.removeAt(0);
      }
      await prefs.setString(_prefsKey, jsonEncode(list));
    } catch (_) {
      // No bloquear la app si falla el log local.
    }

    _scheduleServerUpload();
  }

  /// Trazas de desarrollo (pasos OK, skips esperados). No se suben al servidor.
  static void trace(
    String category,
    String message, {
    Map<String, dynamic>? data,
  }) {
    if (!kDebugMode) {
      return;
    }
    debugPrint('[trace:$category] $message ${data ?? ''}');
  }

  static bool _isDuplicate(String dedupeKey) {
    final now = DateTime.now();
    if (_lastDedupeKey == dedupeKey &&
        _lastDedupeAt != null &&
        now.difference(_lastDedupeAt!) < const Duration(seconds: 60)) {
      return true;
    }
    _lastDedupeKey = dedupeKey;
    _lastDedupeAt = now;
    return false;
  }

  static void _scheduleServerUpload() {
    final token = ClientDiagnosticApi.authToken?.trim() ?? '';
    if (token.isEmpty) {
      return;
    }
    final now = DateTime.now();
    if (_lastUploadAt != null &&
        now.difference(_lastUploadAt!) < const Duration(seconds: 8)) {
      if (!_uploadScheduled) {
        _uploadScheduled = true;
        Future<void>.delayed(const Duration(seconds: 8), () {
          _uploadScheduled = false;
          unawaited(_flushToServer());
        });
      }
      return;
    }
    if (_uploadScheduled) {
      return;
    }
    _uploadScheduled = true;
    Future<void>.delayed(const Duration(seconds: 3), () {
      _uploadScheduled = false;
      unawaited(_flushToServer());
    });
  }

  static Future<void> _flushToServer() async {
    _lastUploadAt = DateTime.now();
    await flush(ClientDiagnosticApi.upload);
  }

  static Future<List<Map<String, dynamic>>> readAll() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_prefsKey);
      if (raw == null || raw.isEmpty) return const [];
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    } catch (_) {
      return const [];
    }
  }

  static Future<void> clear() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_prefsKey);
    } catch (_) {
      // ignore
    }
  }

  /// Sube entradas al servidor y vacía el buffer si [upload] devuelve true.
  static Future<bool> flush(
    Future<bool> Function(List<Map<String, dynamic>> entries) upload,
  ) async {
    final entries = await readAll();
    if (entries.isEmpty) return true;
    try {
      final ok = await upload(entries);
      if (ok) await clear();
      return ok;
    } catch (_) {
      return false;
    }
  }
}
