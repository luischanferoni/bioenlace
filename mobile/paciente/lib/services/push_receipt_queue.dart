import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared/http/bioenlace_http_trace.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Cola local idempotente de recibos push (DELIVERED / OPENED).
class PushReceiptQueue {
  PushReceiptQueue._();

  static const _prefsKey = 'push_receipt_queue_v1';

  static Future<void> enqueue({
    required String notificationRef,
    required String interactionType,
    required String clientEventId,
    String? source,
    String? providerMessageId,
    String? occurredAt,
  }) async {
    final ref = notificationRef.trim();
    final type = interactionType.trim().toUpperCase();
    final eventId = clientEventId.trim();
    if (ref.isEmpty || eventId.isEmpty || (type != 'DELIVERED' && type != 'OPENED')) {
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    final list = _decode(prefs.getString(_prefsKey));
    final exists = list.any(
      (e) =>
          e['notification_ref'] == ref &&
          e['interaction_type'] == type &&
          e['client_event_id'] == eventId,
    );
    if (exists) {
      return;
    }
    list.add({
      'notification_ref': ref,
      'interaction_type': type,
      'client_event_id': eventId,
      if (source != null && source.isNotEmpty) 'source': source,
      if (providerMessageId != null && providerMessageId.isNotEmpty)
        'provider_message_id': providerMessageId,
      'occurred_at':
          occurredAt ?? DateTime.now().toUtc().toIso8601String(),
    });
    await prefs.setString(_prefsKey, json.encode(list));
  }

  static Future<void> flush({String? authToken}) async {
    final prefs = await SharedPreferences.getInstance();
    final token = (authToken ?? prefs.getString('auth_token') ?? '').trim();
    if (token.isEmpty) {
      return;
    }
    final list = _decode(prefs.getString(_prefsKey));
    if (list.isEmpty) {
      return;
    }
    final remaining = <Map<String, dynamic>>[];
    for (final item in list) {
      final ok = await _post(item, token);
      if (!ok) {
        remaining.add(item);
      }
    }
    if (remaining.isEmpty) {
      await prefs.remove(_prefsKey);
    } else {
      await prefs.setString(_prefsKey, json.encode(remaining));
    }
  }

  static Future<bool> _post(Map<String, dynamic> item, String token) async {
    try {
      final uri = Uri.parse(
        '${AppConfig.apiUrl}/notificaciones/registrar-interaccion-push-propia',
      );
      final headers = AppConfig.jsonHeaders(
        bearerToken: token,
        appClient: 'paciente-flutter',
      );
      final response = await http
          .post(uri, headers: headers, body: json.encode(item))
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      BioenlaceHttpTrace.logResponse(
        'notificaciones/registrar-interaccion-push-propia',
        response,
      );
      if (response.statusCode >= 200 && response.statusCode < 300) {
        final data = json.decode(response.body);
        return data is Map && data['success'] == true;
      }
      // 4xx de negocio (ref ajena / inválida): descartar para no reintentar forever.
      if (response.statusCode >= 400 && response.statusCode < 500) {
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('PushReceiptQueue.flush: $e');
      return false;
    }
  }

  static List<Map<String, dynamic>> _decode(String? raw) {
    if (raw == null || raw.trim().isEmpty) {
      return [];
    }
    try {
      final decoded = json.decode(raw);
      if (decoded is! List) {
        return [];
      }
      return decoded
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    } catch (_) {
      return [];
    }
  }
}
