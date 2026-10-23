import 'package:flutter/foundation.dart';
import 'package:shared/shared.dart';

import '../../infra/app_texts.dart';
import '../../services/historia_clinica_service.dart';
import 'timeline_history_loader.dart';

/// Estado de carga de la historia clínica del timeline (datos, cargando, error).
class TimelineHistoryController extends ChangeNotifier {
  TimelineHistoryController({
    required this.service,
    required this.personaId,
    required this.encounterSummaryLoaded,
    this.parentType,
    this.parentId,
  });

  final HistoriaClinicaService service;
  final int personaId;
  final bool encounterSummaryLoaded;
  final String? parentType;
  final int? parentId;

  HistoriaClinicaResponse? data;
  bool isLoading = true;
  String errorMessage = '';

  Future<void> load() async {
    isLoading = true;
    errorMessage = '';
    notifyListeners();
    try {
      data = await fetchTimelineHistory(
        service,
        personaId: personaId,
        encounterSummaryLoaded: encounterSummaryLoaded,
        parentType: parentType,
        parentId: parentId,
      );
    } on HistoriaClinicaVentanaException catch (e) {
      errorMessage = e.message;
    } catch (e) {
      errorMessage = AppTexts.timelineHistoryLoadError(userFriendlyErrorMessage(e));
    }
    isLoading = false;
    notifyListeners();
  }
}
