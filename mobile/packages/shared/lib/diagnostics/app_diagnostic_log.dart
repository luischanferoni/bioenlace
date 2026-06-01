import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'client_diagnostic_api.dart';
import 'crashlytics_bootstrap.dart';

/// Log local en dispositivo (debug + release). Pensado para diagnosticar flujos
/// en APK/IPA instalados y subir luego a un endpoint propio.
class AppDiagnosticLog {
  AppDiagnosticLog._();

  static const _prefsKey = 'bio_app_diagnostic_log_v1';
  static const _maxEntries = 200;

  static bool _uploadScheduled = false;
  static DateTime? _lastUploadAt;

  static Future<void> log(
    String category,
    String message, {
    Map<String, dynamic>? data,
  }) async {
    final entry = <String, dynamic>{
      'ts': DateTime.now().toUtc().toIso8601String(),
      'category': category,
      'message': message,
      if (data != null && data.isNotEmpty) 'data': data,
    };

    if (kDebugMode) {
      debugPrint('[diag:$category] $message ${data ?? ''}');
    }

    final crashLine = data != null && data.isNotEmpty
        ? '[$category] $message ${jsonEncode(data)}'
        : '[$category] $message';
    unawaited(CrashlyticsBootstrap.log(crashLine));
    final reportAsNonFatal = message == 'error' ||
        message == 'chat_advance_error' ||
        message == 'chat_advance_failed' ||
        message == 'advance_failed' ||
        message == 'load_fail' ||
        message == 'load_stuck';
    if (reportAsNonFatal) {
      unawaited(CrashlyticsBootstrap.recordError(
        Exception(crashLine),
        StackTrace.current,
        reason: category,
        fatal: false,
        customKeys: data?.map((k, v) => MapEntry(k, v?.toString())),
      ));
    }

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

  static void _scheduleServerUpload() {
    final token = ClientDiagnosticApi.authToken?.trim() ?? '';
    if (token.isEmpty) {
      return;
    }
    final now = DateTime.now();
    if (_lastUploadAt != null &&
        now.difference(_lastUploadAt!) < const Duration(seconds: 4)) {
      if (!_uploadScheduled) {
        _uploadScheduled = true;
        Future<void>.delayed(const Duration(seconds: 4), () {
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
    Future<void>.delayed(const Duration(seconds: 2), () {
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
