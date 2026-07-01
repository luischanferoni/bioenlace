import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

/// Trazas HTTP en consola durante `flutter run` (solo debug).
class BioenlaceHttpTrace {
  BioenlaceHttpTrace._();

  static void logResponse(
    String label,
    http.Response response, {
    int maxBodyChars = 4000,
  }) {
    if (!kDebugMode) {
      return;
    }

    final url = response.request?.url;
    debugPrint('[HTTP $label] ${response.statusCode} $url');

    final body = response.body;
    if (body.isEmpty) {
      debugPrint('[HTTP $label body] <vacío>');
      return;
    }

    final preview = body.length > maxBodyChars
        ? '${body.substring(0, maxBodyChars)}…'
        : body;
    debugPrint('[HTTP $label body] $preview');
  }
}
