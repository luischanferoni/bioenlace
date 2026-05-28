import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import 'assistant_envelope.dart';

const String asistenteTimeoutErrorMessage =
    'Hubo un error, por favor intente enviar el mensaje de nuevo en unos minutos.';

/// Misma heurística que `SubIntentEngine::userWantsNearby` (PHP).
bool asistenteUserSaysNearbyForEfectorChooser(String content) {
  final s = content.trim().toLowerCase();
  if (s.isEmpty) return false;
  final re = RegExp(
    r'\b(cerca|cercanos|cercano|cercanas|cercana|cercanía|cercania)\b',
    caseSensitive: false,
  );
  return re.hasMatch(s);
}

class AtajoItem {
  final String intentId;
  final String title;
  final String description;

  AtajoItem({
    required this.intentId,
    required this.title,
    this.description = '',
  });
}

class AtajoCategoria {
  final String titulo;
  final List<AtajoItem> items;

  AtajoCategoria({required this.titulo, required this.items});
}

String normalizeAtajoDescription(String raw) {
  var s = raw.trim();
  if (s.isEmpty) return '';

  // Quitar prefijos basura tipo ":" o "·" o "-" que llegan en algunos catálogos.
  s = s.replaceAll(RegExp(r'^\s*[:\-–—•·]+\s*'), '');

  // Evitar jerga interna en UI (usuario final).
  s = s.replaceAll(
    RegExp(r'\bAutogesti[oó]n\b', caseSensitive: false),
    '',
  );
  s = s.replaceAll(
    RegExp(r'\bstaff\b', caseSensitive: false),
    '',
  );

  // Limpieza: espacios repetidos y signos sueltos.
  s = s.replaceAll(RegExp(r'\s+'), ' ').trim();
  s = s.replaceAll(RegExp(r'\s+([,.;:])'), r'$1').trim();

  // Reformulaciones a español neutro + conciso (UX).
  // Nota: estas reglas intencionalmente pisan el texto original cuando detectan un patrón conocido.
  final lower = s.toLowerCase();

  // "no-show y dias desde reserva hasta la cita en un período."
  if (lower.contains('no-show') ||
      (lower.contains('días') || lower.contains('dias')) &&
          lower.contains('reserva') &&
          lower.contains('cita')) {
    s = 'Estadísticas de tu agenda para ayudarte a mejorarla';
  }

  // Reprogramación de turnos.
  if (lower.contains('reprogram') &&
      (lower.contains('turno') || lower.contains('cita'))) {
    s = 'Para cambiar el horario de tus turnos pendientes';
  }

  // "Flujo conversacional para ... turno: servicio, centro de salud, profesional..."
  if (lower.contains('flujo conversacional') &&
      lower.contains('turno')) {
    s = 'Para reservar un turno en un centro de salud público';
  }

  // Dejarlo conciso (1 línea).
  if (s.length > 120) {
    s = '${s.substring(0, 117).trim()}...';
  }
  return s;
}

String? intentIdFromCommonActionMap(Map<String, dynamic> a) {
  final co = a['client_open'];
  if (co is Map) {
    final m = Map<String, dynamic>.from(co);
    if ('${m['kind']}' == 'intent' && m['intent_id'] != null) {
      final s = '${m['intent_id']}'.trim();
      if (s.isNotEmpty) return s;
    }
  }
  final aid = a['action_id'];
  if (aid != null) {
    final s = '$aid'.trim();
    if (s.isNotEmpty) return s;
  }
  return null;
}

String actionDisplayNameFromMap(Map<String, dynamic> a) {
  final n = a['name'] ?? a['display_name'];
  if (n != null && '$n'.trim().isNotEmpty) return '$n'.trim();
  return intentIdFromCommonActionMap(a) ?? '';
}

/// Cliente HTTP del asistente (`POST /api/v1/asistente/enviar`, sobre v3).
class AsistenteService {
  String userId;
  final String? authToken;
  final String appClient;

  String? currentIntentId;
  String? currentSubintentId;
  Map<String, dynamic> draft = {};

  AsistenteService({
    required this.userId,
    this.authToken,
    required this.appClient,
  });

  void _syncFromEnvelope(Map<String, dynamic> root) {
    final flow = AssistantFlowView.fromEnvelope(root);
    if (flow == null) return;
    if (flow.intentId.isNotEmpty) currentIntentId = flow.intentId;
    if (flow.subintentId.isNotEmpty) currentSubintentId = flow.subintentId;
    if (flow.draftDelta.isNotEmpty) {
      draft = {...draft, ...flow.draftDelta};
    }
  }

  void resetFlow() {
    currentIntentId = null;
    currentSubintentId = null;
    draft = {};
  }

  Future<Map<String, dynamic>> procesarInteraccion(
    String textoInteraccionUsuario, {
    String? actionId,
  }) async {
    try {
      final trimmedIn = textoInteraccionUsuario.trim();
      if ((actionId == null || actionId.isEmpty) &&
          trimmedIn.isNotEmpty &&
          !asistenteUserSaysNearbyForEfectorChooser(textoInteraccionUsuario)) {
        resetFlow();
      }

      final uri = Uri.parse('${AppConfig.apiUrl}/asistente/enviar');
      final headers = AppConfig.jsonHeaders(
        bearerToken: authToken,
        appClient: appClient,
      );

      final body = <String, dynamic>{};
      if (currentIntentId != null && currentIntentId!.isNotEmpty) {
        body['intent_id'] = currentIntentId;
        if (currentSubintentId != null && currentSubintentId!.isNotEmpty) {
          body['subintent_id'] = currentSubintentId;
        }
        body['draft'] = draft;
        body['content'] = textoInteraccionUsuario;
        final idServ = draft['id_servicio_asignado'] ?? draft['id_servicio'];
        if (idServ != null && idServ.toString().trim().isNotEmpty) {
          body['id_servicio_asignado'] = idServ;
        }
      } else {
        body['content'] = textoInteraccionUsuario;
      }
      if (actionId != null && actionId.isNotEmpty) {
        body['action_id'] = actionId;
      }

      final response = await http
          .post(uri, headers: headers, body: json.encode(body))
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final decoded = json.decode(response.body);
      if (response.statusCode != 200) {
        final msg = decoded is Map
            ? (decoded['message']?.toString() ?? 'Error en la consulta')
            : 'Error en la consulta';
        return {
          'success': false,
          'message': msg,
          if (decoded is Map) 'errors': decoded['errors'],
        };
      }

      if (decoded is! Map) {
        return {'success': false, 'message': 'Respuesta inválida del asistente'};
      }
      final root = Map<String, dynamic>.from(decoded);
      final kind = root['kind']?.toString() ?? '';
      if (kind.isEmpty) {
        return {
          'success': false,
          'message': root['message']?.toString() ?? 'Error en la consulta',
          'data': root,
        };
      }

      _syncFromEnvelope(root);
      return {'success': true, 'data': root};
    } catch (e) {
      return {
        'success': false,
        'message': e is TimeoutException
            ? asistenteTimeoutErrorMessage
            : 'Error de conexión: ${e.toString()}',
      };
    }
  }

  Future<List<AtajoCategoria>> cargarAtajos() async {
    try {
      final uri = Uri.parse('${AppConfig.apiUrl}/acciones/comunes');
      final headers = AppConfig.jsonHeaders(
        bearerToken: authToken,
        appClient: appClient,
      )..remove('Content-Type');

      final response = await http
          .get(uri, headers: headers)
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));

      final responseData = json.decode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200 || responseData['success'] != true) {
        return [];
      }

      final catsRaw = responseData['categories'];
      if (catsRaw is List && catsRaw.isNotEmpty) {
        final out = <AtajoCategoria>[];
        for (final c in catsRaw) {
          if (c is! Map) continue;
          final cm = Map<String, dynamic>.from(c);
          final titulo = cm['titulo']?.toString() ?? 'Atajos';
          final actions = cm['actions'];
          final items = <AtajoItem>[];
          if (actions is List) {
            for (final a in actions) {
              if (a is! Map) continue;
              final am = Map<String, dynamic>.from(a);
              final iid = intentIdFromCommonActionMap(am);
              if (iid == null || iid.isEmpty) continue;
              items.add(AtajoItem(
                intentId: iid,
                title: actionDisplayNameFromMap(am),
                description: normalizeAtajoDescription(
                  am['description']?.toString() ?? '',
                ),
              ));
            }
          }
          if (items.isNotEmpty) {
            out.add(AtajoCategoria(titulo: titulo, items: items));
          }
        }
        if (out.isNotEmpty) return out;
      }

      final raw = responseData['actions'];
      if (raw is! List) return [];
      final flat = <AtajoItem>[];
      for (final a in raw) {
        if (a is! Map) continue;
        final am = Map<String, dynamic>.from(a);
        final iid = intentIdFromCommonActionMap(am);
        if (iid == null || iid.isEmpty) continue;
        flat.add(AtajoItem(
          intentId: iid,
          title: actionDisplayNameFromMap(am),
          description: normalizeAtajoDescription(
            am['description']?.toString() ?? '',
          ),
        ));
      }
      if (flat.isEmpty) return [];
      return [AtajoCategoria(titulo: 'Atajos', items: flat)];
    } catch (_) {
      return [];
    }
  }
}
