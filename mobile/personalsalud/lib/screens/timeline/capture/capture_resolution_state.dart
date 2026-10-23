import 'package:flutter/widgets.dart';

import '../../../models/encounter_capture_analysis.dart';
import 'capture_analysis_helpers.dart';

/// Estado de las decisiones del profesional sobre una revisión de captura:
/// ítems elegidos, resolución de cada issue y de los problemas abiertos.
/// No notifica: quien lo use debe llamar a `setState` alrededor de cada cambio.
class CaptureResolutionState {
  Set<String> stagedItemIds = {};

  /// issue_id → valor elegido (ninguno viene preseleccionado).
  Map<String, dynamic> issueResolutions = {};

  /// open problem key `condition:123` / `care_plan:456` → value.
  Map<String, dynamic> openProblemResolutions = {};

  final Map<String, TextEditingController> _customControllers = {};

  TextEditingController customController(String issueId) =>
      _customControllers.putIfAbsent(issueId, () => TextEditingController());

  String customText(String issueId) =>
      _customControllers[issueId]?.text ?? '';

  /// Descarta resoluciones y textos libres; no toca los ítems elegidos.
  void clearResolutions() {
    for (final c in _customControllers.values) {
      c.dispose();
    }
    _customControllers.clear();
    issueResolutions = {};
    openProblemResolutions = {};
  }

  void toggleStagedItem(String id, bool selected) {
    if (selected) {
      stagedItemIds.add(id);
      return;
    }
    stagedItemIds.remove(id);
    final prefix = '$id:';
    issueResolutions.removeWhere((key, _) => key.startsWith(prefix));
    for (final entry in _customControllers.entries) {
      if (entry.key.startsWith(prefix)) entry.value.clear();
    }
  }

  void selectIssueOption(String issueId, dynamic value, bool wasSelected) {
    if (wasSelected) {
      issueResolutions.remove(issueId);
    } else {
      issueResolutions[issueId] = value;
      _customControllers[issueId]?.clear();
    }
  }

  void setIssueCustomText(EncounterCaptureIssue issue, String trimmed) {
    if (trimmed.isEmpty) {
      // Solo limpia si el valor actual era custom (no chip).
      final current = issueResolutions[issue.id];
      final matchesChip = issue.options.any(
        (o) => o.value.toString() == current?.toString(),
      );
      if (!matchesChip) issueResolutions.remove(issue.id);
    } else {
      issueResolutions[issue.id] = trimmed;
    }
  }

  void selectOpenProblemOption(String key, dynamic value, bool wasSelected) {
    if (wasSelected) {
      openProblemResolutions.remove(key);
    } else {
      openProblemResolutions[key] = value;
    }
  }

  bool allIssuesResolved(EncounterCaptureAnalysis review) =>
      allStagedIssuesResolved(
        review: review,
        stagedItemIds: stagedItemIds,
        resolutions: issueResolutions,
        customText: customText,
      );

  void dispose() {
    for (final c in _customControllers.values) {
      c.dispose();
    }
    _customControllers.clear();
  }
}
