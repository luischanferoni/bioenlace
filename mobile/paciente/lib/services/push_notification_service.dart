import 'dart:convert';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';
import 'package:shared/http/bioenlace_http_trace.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../firebase/firebase_bootstrap.dart';
import 'push_receipt_queue.dart';

/// Registro FCM y manejo de taps (requiere `google-services.json` / `GoogleService-Info.plist`).
class PushNotificationService {
  PushNotificationService._();
  static final PushNotificationService instance = PushNotificationService._();

  static bool _initialized = false;
  void Function(Map<String, dynamic> data)? onNotificationOpen;

  @pragma('vm:entry-point')
  static Future<void> firebaseMessagingBackgroundHandler(
    RemoteMessage message,
  ) async {
    await FirebaseBootstrap.ensureInitialized();
    debugPrint('FCM background: ${message.messageId}');
    await _enqueueDelivered(message, source: 'background_handler');
  }

  Future<void> init({void Function(Map<String, dynamic> data)? onOpen}) async {
    if (kIsWeb || _initialized) {
      return;
    }
    onNotificationOpen = onOpen;

    final firebaseOk = await FirebaseBootstrap.ensureInitialized();
    if (!firebaseOk) {
      debugPrint(
        'PushNotificationService: Firebase no listo (ver FIREBASE_SETUP.md).',
      );
      return;
    }

    try {
      FirebaseMessaging.onBackgroundMessage(
        firebaseMessagingBackgroundHandler,
      );

      await _requestNotificationPermissions();

      final messaging = FirebaseMessaging.instance;

      if (Platform.isIOS) {
        await messaging.setForegroundNotificationPresentationOptions(
          alert: true,
          badge: true,
          sound: true,
        );
      }

      FirebaseMessaging.onMessage.listen((msg) async {
        debugPrint(
          'FCM foreground: ${msg.notification?.title ?? msg.data['type']}',
        );
        await _enqueueDelivered(msg, source: 'on_message');
        await PushReceiptQueue.flush();
      });

      FirebaseMessaging.onMessageOpenedApp.listen((msg) async {
        await _enqueueOpened(msg, source: 'on_message_opened_app');
        await PushReceiptQueue.flush();
        _dispatchOpen(msg.data);
      });

      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        await _enqueueOpened(initial, source: 'get_initial_message');
        await PushReceiptQueue.flush();
        _dispatchOpen(initial.data);
      }

      messaging.onTokenRefresh.listen(registerTokenWithApi);

      final token = await messaging.getToken();
      if (token != null && token.isNotEmpty) {
        await registerTokenWithApi(token);
      }
      _initialized = true;
      await PushReceiptQueue.flush();
    } catch (e, st) {
      debugPrint('PushNotificationService: $e\n$st');
    }
  }

  Future<void> _requestNotificationPermissions() async {
    if (Platform.isAndroid) {
      final status = await Permission.notification.status;
      if (!status.isGranted) {
        await Permission.notification.request();
      }
    }
    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  void _dispatchOpen(Map<String, dynamic> data) {
    onNotificationOpen?.call(Map<String, dynamic>.from(data));
  }

  /// Llamar tras login si el token se obtuvo antes de tener JWT.
  Future<void> registerTokenIfLoggedIn() async {
    if (!_initialized || kIsWeb) return;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null && token.isNotEmpty) {
        await registerTokenWithApi(token);
      }
      await PushReceiptQueue.flush();
    } catch (e) {
      debugPrint('registerTokenIfLoggedIn: $e');
    }
  }

  Future<void> registerTokenWithApi(String pushToken, {String? authToken}) async {
    if (pushToken.trim().isEmpty) {
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    final token = authToken ?? prefs.getString('auth_token');
    if (token == null || token.isEmpty) {
      return;
    }
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/devices/push-token');
      final headers = AppConfig.jsonHeaders(
        bearerToken: token,
        appClient: 'paciente-flutter',
      );
      final platform = Platform.isAndroid
          ? 'android'
          : (Platform.isIOS ? 'ios' : 'unknown');
      final response = await http
          .post(
            uri,
            headers: headers,
            body: json.encode({
              'push_token': pushToken,
              'push_provider': 'fcm',
              'platform': platform,
            }),
          )
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      BioenlaceHttpTrace.logResponse('devices/push-token', response);
      if (response.statusCode >= 400) {
        debugPrint(
          'registerTokenWithApi: HTTP ${response.statusCode} ${response.body}',
        );
      }
    } catch (e) {
      debugPrint('registerTokenWithApi: $e');
    }
  }

  static Future<void> _enqueueDelivered(
    RemoteMessage message, {
    required String source,
  }) async {
    final ref = _notificationRefFromData(message.data);
    if (ref == null) {
      return;
    }
    final messageId = (message.messageId ?? '').trim();
    final clientEventId = messageId.isNotEmpty
        ? 'delivered:$messageId'
        : 'delivered:$ref:$source';
    await PushReceiptQueue.enqueue(
      notificationRef: ref,
      interactionType: 'DELIVERED',
      clientEventId: clientEventId,
      source: source,
      providerMessageId: messageId.isEmpty ? null : messageId,
    );
  }

  static Future<void> _enqueueOpened(
    RemoteMessage message, {
    required String source,
  }) async {
    final ref = _notificationRefFromData(message.data);
    if (ref == null) {
      return;
    }
    final messageId = (message.messageId ?? '').trim();
    final clientEventId = messageId.isNotEmpty
        ? 'opened:$messageId'
        : 'opened:$ref:$source';
    await PushReceiptQueue.enqueue(
      notificationRef: ref,
      interactionType: 'OPENED',
      clientEventId: clientEventId,
      source: source,
      providerMessageId: messageId.isEmpty ? null : messageId,
    );
  }

  static String? _notificationRefFromData(Map<String, dynamic> data) {
    final ref = data['notification_ref']?.toString().trim() ?? '';
    return ref.isEmpty ? null : ref;
  }

  /// Resumen de atención publicado (`ENCOUNTER_SUMMARY_READY`).
  static int? encounterIdDesdePush(Map<String, dynamic> data) {
    if (data['type']?.toString() != 'ENCOUNTER_SUMMARY_READY') {
      return null;
    }
    final raw = data['encounter_id']?.toString() ?? '';
    final id = int.tryParse(raw);
    return id != null && id > 0 ? id : null;
  }

  /// Touchpoint de seguimiento post-consulta (`CARE_FOLLOWUP_TOUCHPOINT`).
  static int? followupTouchpointIdDesdePush(Map<String, dynamic> data) {
    if (data['type']?.toString() != 'CARE_FOLLOWUP_TOUCHPOINT') {
      return null;
    }
    final raw = data['touchpoint_id']?.toString() ?? '';
    final id = int.tryParse(raw);
    return id != null && id > 0 ? id : null;
  }

  /// Payload de confirmación de asistencia desde push/bandeja.
  static Map<String, dynamic>? confirmacionDesdeData(Map<String, dynamic> data) {
    final type = data['type']?.toString() ?? '';
    final action = data['action']?.toString() ?? '';
    final isConfirm = type == 'TURNO_CONFIRMAR' || action == 'confirmar_asistencia';
    if (!isConfirm) {
      return null;
    }
    final idTurnoRaw = data['id_turno']?.toString() ?? data['id']?.toString() ?? '';
    final idTurno = int.tryParse(idTurnoRaw);
    if (idTurno == null || idTurno <= 0) {
      return null;
    }
    return {
      'id_turno': idTurno,
      'token': data['token']?.toString(),
      'notification_ref': data['notification_ref']?.toString(),
      'action_label': data['action_label']?.toString() ?? 'Confirmar asistencia',
    };
  }

  /// Payload de oferta de adelantamiento desde push/bandeja.
  static Map<String, dynamic>? adelantamientoDesdeData(Map<String, dynamic> data) {
    final type = data['type']?.toString() ?? '';
    final action = data['action']?.toString() ?? '';
    final isAdvance =
        type == 'TURNO_ADVANCE_OFFER' || action == 'adelantar_turno';
    if (!isAdvance) {
      return null;
    }
    final token = data['offer_token']?.toString().trim() ?? '';
    if (token.isEmpty) {
      return null;
    }
    final idTurnoRaw = data['id_turno']?.toString() ?? '';
    final idTurno = int.tryParse(idTurnoRaw);
    return {
      'offer_token': token,
      'id_turno': idTurno != null && idTurno > 0 ? idTurno : null,
      'fecha': data['fecha']?.toString(),
      'hora': data['hora']?.toString(),
      'notification_ref': data['notification_ref']?.toString(),
      'action_label': data['action_label']?.toString() ?? 'Adelantar mi turno',
    };
  }

  /// Abre el flow adecuado según payload push (desde MainScreen).
  static Map<String, dynamic>? turnoStubDesdePush(Map<String, dynamic> data) {
    final type = data['type']?.toString() ?? '';
    if (type != 'TURNO_REQUIERE_REUBICACION' &&
        type != 'TURNO_CANCELADO_EFECTOR') {
      return null;
    }
    final idTurno = data['id_turno']?.toString() ?? '';
    if (idTurno.isEmpty) {
      return null;
    }
    return {
      'id': int.tryParse(idTurno) ?? idTurno,
      'estado': 'EN_RESOLUCION',
      'en_resolucion': true,
      'turno_resolucion': {
        'origen': type == 'TURNO_REQUIERE_REUBICACION' ? 'gestion_staff' : '',
        'tiene_opciones_vecinas': false,
      },
    };
  }
}
