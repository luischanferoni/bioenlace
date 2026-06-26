import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/config/api_config.dart';

/// Sección del panel de inicio (`GET /api/v1/home/panel`).
class HomePanelSection {
  final String id;
  final String kind;
  final Map<String, dynamic> data;
  final int? pollIntervalSeconds;

  HomePanelSection({
    required this.id,
    required this.kind,
    required this.data,
    this.pollIntervalSeconds,
  });

  factory HomePanelSection.fromJson(Map<String, dynamic> json) {
    return HomePanelSection(
      id: (json['id'] as String?) ?? '',
      kind: (json['kind'] as String?) ?? '',
      data: Map<String, dynamic>.from(json['data'] as Map? ?? {}),
      pollIntervalSeconds: json['poll_interval_seconds'] as int?,
    );
  }
}

class HomePanelResponse {
  final String layout;
  final String? audience;
  final String? encounterClass;
  final String? fecha;
  final String title;
  final List<HomePanelSection> sections;

  HomePanelResponse({
    required this.layout,
    this.audience,
    this.encounterClass,
    this.fecha,
    required this.title,
    required this.sections,
  });

  factory HomePanelResponse.fromJson(Map<String, dynamic> json) {
    final rawSections = json['sections'] as List<dynamic>? ?? [];
    return HomePanelResponse(
      layout: (json['layout'] as String?) ?? '',
      audience: json['audience'] as String?,
      encounterClass: json['encounter_class'] as String?,
      fecha: json['fecha'] as String?,
      title: (json['title'] as String?) ?? 'Inicio',
      sections: rawSections
          .map((e) => HomePanelSection.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }

  HomePanelSection? sectionByKind(String kind) {
    for (final s in sections) {
      if (s.kind == kind) return s;
    }
    return null;
  }

  List<HomePanelSection> sectionsByKind(String kind) {
    return sections.where((s) => s.kind == kind).toList(growable: false);
  }
}

class HomePanelApi {
  String? authToken;
  String? userId;
  String? appClient;

  HomePanelApi({this.authToken, this.userId, this.appClient});

  Map<String, String> get _headers {
    final headers = AppConfig.jsonHeaders(
      bearerToken: authToken,
      appClient: appClient ?? 'flutter',
    );
    return headers;
  }

  Future<HomePanelResponse> getPanel({
    String? fecha,
    String? sections,
    int? idEfector,
    int? subjectPersonaId,
  }) async {
    final query = <String, String>{};
    if (fecha != null && fecha.isNotEmpty) query['fecha'] = fecha;
    if (sections != null && sections.isNotEmpty) query['sections'] = sections;
    if (idEfector != null) query['id_efector'] = idEfector.toString();
    if (subjectPersonaId != null && subjectPersonaId > 0) {
      query['subject_persona_id'] = subjectPersonaId.toString();
    }
    if (userId != null &&
        (authToken == null ||
            authToken!.isEmpty ||
            authToken!.startsWith('simulated_'))) {
      query['user_id'] = userId!;
    }

    final uri = Uri.parse('${AppConfig.apiUrl}/home/panel')
        .replace(queryParameters: query.isNotEmpty ? query : null);

    final response = await http.get(uri, headers: _headers);
    if (response.statusCode != 200) {
      throw Exception('Error al cargar panel (${response.statusCode})');
    }

    final decoded = json.decode(response.body);
    if (decoded is! Map<String, dynamic> || decoded['success'] != true) {
      throw Exception(
        decoded is Map ? (decoded['message'] as String? ?? 'Error panel') : 'Error panel',
      );
    }

    return HomePanelResponse.fromJson(
      Map<String, dynamic>.from(decoded['data'] as Map? ?? {}),
    );
  }
}
