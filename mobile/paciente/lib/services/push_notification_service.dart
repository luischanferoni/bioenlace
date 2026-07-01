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

      FirebaseMessaging.onMessage.listen((msg) {
        debugPrint(
          'FCM foreground: ${msg.notification?.title ?? msg.data['type']}',
        );
      });

      FirebaseMessaging.onMessageOpenedApp.listen((msg) {
        _dispatchOpen(msg.data);
      });

      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        _dispatchOpen(initial.data);
      }

      messaging.onTokenRefresh.listen(registerTokenWithApi);

      final token = await messaging.getToken();
      if (token != null && token.isNotEmpty) {
        await registerTokenWithApi(token);
      }
      _initialized = true;
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
    } catch (e) {
      debugPrint('registerTokenIfLoggedIn: $e');
    }
  }

  Future<void> registerTokenWithApi(String pushToken, {String? authToken}) async {
    if (pushToken.trim().isEmpty) {
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    var deviceId = prefs.getString('device_id');
    if (deviceId == null || deviceId.isEmpty) {
      deviceId = 'pac-${DateTime.now().millisecondsSinceEpoch}';
      await prefs.setString('device_id', deviceId);
    }
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
              'device_id': deviceId,
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
