import 'dart:convert';

import 'package:flutter/foundation.dart';
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
    final token =
        (authToken ?? await AuthTokenStore.instance.read() ?? '').trim();
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
      await ApiClient(
        authToken: token,
        appClient: 'paciente-flutter',
        traceLabel: 'notificaciones/registrar-interaccion-push-propia',
      ).postData(
        '/notificaciones/registrar-interaccion-push-propia',
        body: item,
      );
      return true;
    } on ApiContractException catch (e) {
      // 4xx de negocio (ref ajena / inválida): descartar para no reintentar forever.
      final status = e.statusCode ?? 0;
      return status >= 400 && status < 500;
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
