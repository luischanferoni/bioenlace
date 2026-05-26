import 'dart:convert';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:permission_handler/permission_handler.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../firebase/firebase_bootstrap.dart';

/// FCM para app médico (guardia, asignaciones).
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
    debugPrint('FCM médico background: ${message.messageId}');
  }

  Future<void> init({void Function(Map<String, dynamic> data)? onOpen}) async {
    if (kIsWeb || _initialized) return;
    onNotificationOpen = onOpen;

    final firebaseOk = await FirebaseBootstrap.ensureInitialized();
    if (!firebaseOk) {
      debugPrint('Push médico: Firebase no configurado.');
      return;
    }

    try {
      FirebaseMessaging.onBackgroundMessage(
        firebaseMessagingBackgroundHandler,
      );
      await _requestPermissions();
      final messaging = FirebaseMessaging.instance;

      if (Platform.isIOS) {
        await messaging.setForegroundNotificationPresentationOptions(
          alert: true,
          badge: true,
          sound: true,
        );
      }

      FirebaseMessaging.onMessage.listen((msg) {
        debugPrint('FCM médico foreground: ${msg.data['type']}');
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
      debugPrint('PushNotificationService médico: $e\n$st');
    }
  }

  Future<void> _requestPermissions() async {
    if (Platform.isAndroid) {
      final status = await Permission.notification.status;
      if (!status.isGranted) {
        await Permission.notification.request();
      }
    }
    await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  void _dispatchOpen(Map<String, dynamic> data) {
    onNotificationOpen?.call(Map<String, dynamic>.from(data));
  }

  Future<void> registerTokenIfLoggedIn() async {
    if (!_initialized || kIsWeb) return;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null && token.isNotEmpty) {
        await registerTokenWithApi(token);
      }
    } catch (e) {
      debugPrint('registerTokenIfLoggedIn médico: $e');
    }
  }

  Future<void> registerTokenWithApi(String pushToken, {String? authToken}) async {
    if (pushToken.trim().isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    var deviceId = prefs.getString('device_id');
    if (deviceId == null || deviceId.isEmpty) {
      deviceId = 'med-${DateTime.now().millisecondsSinceEpoch}';
      await prefs.setString('device_id', deviceId);
    }
    final token = authToken ?? prefs.getString('auth_token');
    if (token == null || token.isEmpty) return;

    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/devices/push-token');
      final headers = AppConfig.jsonHeaders(
        bearerToken: token,
        appClient: 'medico-flutter',
      );
      final platform = Platform.isAndroid
          ? 'android'
          : (Platform.isIOS ? 'ios' : 'unknown');
      final response = await http.post(
        uri,
        headers: headers,
        body: json.encode({
          'device_id': deviceId,
          'push_token': pushToken,
          'push_provider': 'fcm',
          'platform': platform,
        }),
      );
      if (response.statusCode >= 400) {
        debugPrint('registerTokenWithApi médico: HTTP ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('registerTokenWithApi médico: $e');
    }
  }

  static int? guardiaIdDesdePush(Map<String, dynamic> data) {
    final type = data['type']?.toString() ?? '';
    if (type != 'EMERGENCY_ASSIGNED_TO_YOU' &&
        type != 'EMERGENCY_PATIENT_CRITICAL') {
      return null;
    }
    final raw = data['guardia_id']?.toString() ?? '';
    final id = int.tryParse(raw);
    return id != null && id > 0 ? id : null;
  }
}
