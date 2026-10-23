import 'package:shared/http/api_client.dart';

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

    final panel = await ApiClient(
      authToken: authToken,
      appClient: appClient ?? 'flutter',
      traceLabel: 'home/panel',
    ).getData('/home/panel', query: query);

    return HomePanelResponse.fromJson(
      panel,
    );
  }
}
