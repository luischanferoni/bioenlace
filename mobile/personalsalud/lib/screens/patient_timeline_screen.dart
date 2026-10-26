// lib/screens/patient_timeline_screen.dart
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/encounter_capture_analysis.dart';
import '../models/pending_encounter_capture.dart';
import '../infra/app_texts.dart';
import '../services/historia_clinica_service.dart';
import '../services/emergency_guardia_api.dart';
import '../services/pending_encounter_capture_store.dart';
import 'emergency/emergency_triage_screen.dart';
import 'timeline/blocks/care_pack_cohorte_card.dart';
import 'timeline/blocks/documentacion_medico_card.dart';
import 'timeline/blocks/encounter_reasons_card.dart';
import 'timeline/blocks/episodio_banner.dart';
import 'timeline/blocks/episodio_feed_section.dart';
import 'timeline/blocks/signos_vitales_episodio_card.dart';
import 'timeline/capture/capture_actions_bar.dart';
import 'timeline/capture/capture_analyze_bar.dart';
import 'timeline/capture/collapsible_section.dart';
import 'timeline/capture/draft_notes.dart';
import 'timeline/capture/draft_notes_panel.dart';
import 'timeline/capture/note_playback_controller.dart';
import 'timeline/capture/capture_dictation_controller.dart';
import 'timeline/capture/capture_input_bar.dart';
import 'timeline/capture/capture_analysis_helpers.dart';
import 'timeline/capture/capture_resolution_state.dart';
import 'timeline/capture/capture_server_snapshot.dart';
import 'timeline/timeline_history_controller.dart';
import 'timeline/capture/capture_save_result_dialog.dart';
import 'timeline/capture/microphone_permission_dialog.dart';
import 'timeline/capture/pending_captures_sync.dart';
import 'timeline/capture/capture_review_panel.dart';
import 'timeline/capture/pending_captures_panel.dart';
import 'timeline/blocks/informacion_medica_card.dart';
import 'timeline/blocks/motivos_intake_card.dart';
import 'timeline/blocks/paciente_header_card.dart';
import 'timeline/blocks/signos_vitales_card.dart';

class PatientTimelineScreen extends StatefulWidget {
  final int personaId;
  final String? authToken;

  /// true = solo ver historia clínica (sin formulario); false = barra para escribir notas
  final bool soloVer;

  /// Minimal view of a saved encounter (patient and professional details only).
  final bool encounterSummaryLoaded;

  /// Domain parent context aligned with web / `validarPermisoAtencion`.
  final String? parentType;
  final int? parentId;

  const PatientTimelineScreen({
    Key? key,
    required this.personaId,
    this.authToken,
    this.soloVer = true,
    this.encounterSummaryLoaded = false,
    this.parentType,
    this.parentId,
  }) : super(key: key);

  @override
  State<PatientTimelineScreen> createState() => _PatientTimelineScreenState();
}

class _PatientTimelineScreenState extends State<PatientTimelineScreen> {
  final HistoriaClinicaService _historiaClinicaService =
      HistoriaClinicaService();
  final EncounterCaptureApi _encounterApi = EncounterCaptureApi();
  late final CaptureDictationController _voice;
  late final TimelineHistoryController _history;
  HistoriaClinicaResponse? get _historiaClinicaData => _history.data;
  bool get _isLoading => _history.isLoading;
  String get _errorMessage => _history.errorMessage;
  bool _isAnalyzing = false;
  bool _isSaving = false;
  String? get _pendingAudioPath => _voice.pendingAudioPath;
  set _pendingAudioPath(String? v) => _voice.pendingAudioPath = v;
  DeviceDictationResult? get _lastDictation => _voice.lastDictation;
  set _lastDictation(DeviceDictationResult? v) => _voice.lastDictation = v;
  bool get _dictating => _voice.dictating;
  bool get _audioOnlyRecording => _voice.audioOnlyRecording;
  SttClientConfig get _sttConfig => _voice.config;
  bool get _sttConfigLoaded => _voice.configLoaded;
  String get _sttStatus => _voice.status;
  set _sttStatus(String v) => _voice.status = v;
  Map<String, dynamic>? _lastAnalysis;
  EncounterCaptureAnalysis? _captureReview;
  String? _draftText;
  Set<String> get _stagedItemIds => _resolutions.stagedItemIds;
  set _stagedItemIds(Set<String> v) => _resolutions.stagedItemIds = v;
  final CaptureResolutionState _resolutions = CaptureResolutionState();
  Map<String, dynamic> get _issueResolutions => _resolutions.issueResolutions;
  Map<String, dynamic> get _openProblemResolutions =>
      _resolutions.openProblemResolutions;
  final NotePlaybackController _playback = NotePlaybackController();
  bool _historyExpanded = true;
  bool _draftExpanded = true;
  bool _isAddingNote = false;
  final PendingEncounterCaptureStore _pendingStore =
      PendingEncounterCaptureStore.instance;
  List<PendingEncounterCapture> _pendingCaptures = [];
  String? _activePendingId;

  final TextEditingController _chatController = TextEditingController();
  final FocusNode _chatFocusNode = FocusNode();

  bool get _captureBusy => _isAnalyzing || _isSaving || _isAddingNote;

  bool get _enRevisionCaptura => _captureReview != null;
  bool get _showCaptureBar {
    if (widget.encounterSummaryLoaded) return false;
    final capturaApi = _historiaClinicaData?.capturaPermitida;
    if (capturaApi == false) return false;
    return !widget.soloVer ||
        (widget.parentType != null &&
            widget.parentType!.isNotEmpty &&
            widget.parentId != null);
  }

  bool get _isLoadedEncounterSummary =>
      widget.encounterSummaryLoaded ||
      _historiaClinicaData?.capturaPermitida == false;

  String _tlEpisodioFilter = '';

  bool get _esCapturaInternacion =>
      (widget.parentType ?? '').toUpperCase() ==
      EncounterParentType.internacion;

  bool get _esCapturaGuardia =>
      (widget.parentType ?? '').toUpperCase() == EncounterParentType.guardia;

  bool get _esCapturaEpisodio => _esCapturaInternacion || _esCapturaGuardia;

  bool _autoOpenedReview = false;

  @override
  void initState() {
    super.initState();
    if (widget.authToken != null) {
      _historiaClinicaService.authToken = widget.authToken;
      _encounterApi.authToken = widget.authToken;
    }
    _voice = CaptureDictationController(CaptureDictationHost(
      getText: () => _chatController.text,
      setText: (v) => _chatController.text = v,
      notify: _snack,
      showPermissionDialog: _showMicrophonePermissionDialog,
      saveDraft: (texto) => _persistLocalDraft(
        texto: texto,
        status: PendingEncounterCaptureStatus.draft,
        clearError: true,
      ),
      isBusy: () => _captureBusy,
      hasCaptureContext: () => _tieneContextoCaptura,
    ))..addListener(_onHistoryChanged);
    _loadSttConfig();
    _history = TimelineHistoryController(
      service: _historiaClinicaService,
      personaId: widget.personaId,
      encounterSummaryLoaded: widget.encounterSummaryLoaded,
      parentType: widget.parentType,
      parentId: widget.parentId,
    )..addListener(_onHistoryChanged);
    _cargarHistoriaClinica();
    _loadPendingCaptures();
  }

  Future<void> _loadPendingCaptures() async {
    if (!_tieneContextoCaptura) return;
    final list = await loadMergedPendingCaptures(
      store: _pendingStore,
      api: _encounterApi,
      personaId: widget.personaId,
      parentType: widget.parentType!,
      parentId: widget.parentId!,
    );
    if (!mounted) return;
    setState(() => _pendingCaptures = list);

    if (!_autoOpenedReview && !_enRevisionCaptura && !_captureBusy) {
      final ready = firstCaptureReadyForReview(list);
      if (ready != null) {
        _autoOpenedReview = true;
        await _resumePendingCapture(ready);
      }
    }
  }
  Future<void> _loadSttConfig() async {
    try {
      final cfg = await _encounterApi.fetchSttConfig();
      _voice.applyConfig(cfg);
      debugPrint(
        '[stt-config] loaded device=${cfg.deviceEnabled} '
        'source=${cfg.deviceEnabledSource} server=${cfg.serverEnabled} '
        'serverConfigured=${cfg.serverConfigured} '
        'provider=${cfg.proveedorServidor}',
      );
    } catch (e, st) {
      debugPrint('[stt-config] load failed: $e\n$st');
      _voice.markConfigUnavailable();
    }
  }

  @override
  void dispose() {
    _history.dispose();
    _voice.dispose();
    _chatController.dispose();
    _chatFocusNode.dispose();
    _resolutions.dispose();
    _playback.dispose();
    super.dispose();
  }

  void _onHistoryChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _cargarHistoriaClinica() => _history.load();

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: BioAppBar(
        title: _isLoadedEncounterSummary
            ? AppTexts.timelineTitleLoaded
            : (_esCapturaGuardia
                  ? AppTexts.timelineTitleGuardia
                  : (_esCapturaInternacion
                        ? AppTexts.timelineTitleInternacion
                        : AppTexts.timelineTitleHistory)),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage.isNotEmpty
          ? Center(
              child: Padding(
                padding: BioSpacing.pageAll,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    BioAlert.danger(message: _errorMessage),
                    BioSpacing.gapH(BioSpacing.lg),
                    BioButton.primary(
                      label: 'Reintentar',
                      icon: Icons.refresh,
                      onPressed: _cargarHistoriaClinica,
                    ),
                  ],
                ),
              ),
            )
          : _historiaClinicaData == null
          ? Center(
              child: Padding(
                padding: BioSpacing.pageAll,
                child: BioAlert.info(message: AppTexts.timelineNoData),
              ),
            )
          : Column(
              children: [
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.symmetric(
                      horizontal: BioSpacing.sm,
                      vertical: BioSpacing.md,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        PacienteHeaderCard(persona: _historiaClinicaData!.persona, showDemographics: !_isLoadedEncounterSummary),
                        if (_isLoadedEncounterSummary) ...[
                          if (_historiaClinicaData!
                              .documentacionMedico
                              .tieneDatos) ...[
                            BioSpacing.gapH(BioSpacing.md),
                            DocumentacionMedicoCard(doc:
                              _historiaClinicaData!.documentacionMedico,
                            ),
                          ],
                          if (_historiaClinicaData!
                                  .patientEncounterReasons
                                  .motivosIntake
                                  ?.tieneContenido ==
                              true) ...[
                            BioSpacing.gapH(BioSpacing.md),
                            MotivosIntakeCard(intake:
                              _historiaClinicaData!
                                  .patientEncounterReasons
                                  .motivosIntake!,
                            ),
                          ],
                          if ((_historiaClinicaData!
                                      .patientEncounterReasons
                                      .resumen ??
                                  '')
                              .trim()
                              .isNotEmpty) ...[
                            BioSpacing.gapH(BioSpacing.md),
                            EncounterReasonsCard(hc: _historiaClinicaData!, authToken: widget.authToken),
                          ],
                          if (_historiaClinicaData!
                                  .carePackCohorte
                                  ?.tieneContenido ==
                              true) ...[
                            BioSpacing.gapH(BioSpacing.md),
                            CarePackCohorteCard(cohorte: _historiaClinicaData!.carePackCohorte!),
                          ],
                        ] else ...[
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                          if (_historyExpanded) ...[
                            if (_historiaClinicaData!
                                .documentacionMedico
                                .tieneDatos) ...[
                              BioSpacing.gapH(BioSpacing.md),
                              DocumentacionMedicoCard(doc:
                                _historiaClinicaData!.documentacionMedico,
                              ),
                            ],
                            BioSpacing.gapH(BioSpacing.md),
                            InformacionMedicaCard(info:
                              _historiaClinicaData!.informacionMedica,
                            ),
                            BioSpacing.gapH(BioSpacing.md),
                            if (_esCapturaEpisodio)
                              SignosVitalesEpisodioCard(data:
                                _historiaClinicaData!.signosVitalesEpisodio,
                              )
                            else
                              SignosVitalesCard(sv:
                                _historiaClinicaData!.signosVitales,
                              ),
                            if (_esCapturaEpisodio) ...[
                              BioSpacing.gapH(BioSpacing.md),
                              EpisodioBanner(ctx: _historiaClinicaData!.contextoEpisodio, onAction: _onEpisodioAccion),
                              BioSpacing.gapH(BioSpacing.md),
                              EpisodioFeedSection(feed: _historiaClinicaData!.timelineEpisodio, selectedFilterId: _tlEpisodioFilter, onFilterChanged: (id) => setState(() => _tlEpisodioFilter = id)),
                            ] else ...[
                              BioSpacing.gapH(BioSpacing.md),
                              if (_historiaClinicaData!
                                      .patientEncounterReasons
                                      .motivosIntake
                                      ?.tieneContenido ==
                                  true) ...[
                                MotivosIntakeCard(intake:
                                  _historiaClinicaData!
                                      .patientEncounterReasons
                                      .motivosIntake!,
                                ),
                                BioSpacing.gapH(BioSpacing.md),
                              ],
                              EncounterReasonsCard(hc: _historiaClinicaData!, authToken: widget.authToken),
                              if (_historiaClinicaData!
                                      .carePackCohorte
                                      ?.tieneContenido ==
                                  true) ...[
                                BioSpacing.gapH(BioSpacing.md),
                                CarePackCohorteCard(cohorte: _historiaClinicaData!.carePackCohorte!),
                              ],
                            ],
                          ],
                          if (_notes.isNotEmpty) ...[
                            BioSpacing.gapH(BioSpacing.md),
                            Center(
                              child: InkWell(
                                onTap: () => setState(
                                  () => _historyExpanded = !_historyExpanded,
                                ),
                                borderRadius: BorderRadius.circular(BioRadius.sm),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                    vertical: BioSpacing.sm,
                                    horizontal: BioSpacing.md,
                                  ),
                                  decoration: BoxDecoration(
                                    color: context.bio.paperBackground,
                                    border: Border.all(
                                      color: context.bio.paperBorderDefault,
                                      width: BorderWidth.thin,
                                    ),
                                    borderRadius: BorderRadius.circular(BioRadius.sm),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        _historyExpanded
                                            ? 'Contraer Historia Clínica'
                                            : 'Expandir Historia Clínica',
                                        style: BioTypography.bodySm.copyWith(
                                          color: context.bio.textTitle,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                      BioSpacing.gapW(BioSpacing.xs),
                                      Icon(
                                        _historyExpanded ? Icons.expand_less : Icons.expand_more,
                                        size: 20,
                                        color: context.bio.textTitle,
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ],
                            ],
                          ),
                          if (_notes.isNotEmpty && !_enRevisionCaptura) ...[
                            BioSpacing.gapH(BioSpacing.md),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Padding(
                                  padding: const EdgeInsets.symmetric(
                                    vertical: BioSpacing.sm,
                                  ),
                                  child: Row(
                                    children: [
                                      Text(
                                        AppTexts.notesDraftTitle,
                                        style: BioTypography.title,
                                      ),
                                      BioSpacing.gapW(BioSpacing.sm),
                                      BioBadge(
                                        label: '${_notes.length}',
                                        intent: UiIntent.neutral,
                                      ),
                                    ],
                                  ),
                                ),
                                ListenableBuilder(
                                  listenable: _playback,
                                  builder: (context, _) => DraftNotesPanel(
                                    notes: _notes,
                                    busy: _captureBusy,
                                    playingId: _playback.playingId,
                                    onTogglePlay: _togglePlayNote,
                                    onDelete: _deleteNote,
                                    onRetry: _retryNote,
                                  ),
                                ),
                              ],
                            ),
                          ],
                          if (_isAnalyzing) ...[
                            BioSpacing.gapH(BioSpacing.md),
                            const AnalyzingCaptureCard(),
                          ],
                          if (_captureReview != null) ...[
                            BioSpacing.gapH(BioSpacing.md),
                            CaptureReviewPanel(
                              review: _captureReview!,
                              draftText: _draftText,
                              stagedItemIds: _stagedItemIds,
                              issueResolutions: _issueResolutions,
                              openProblemResolutions: _openProblemResolutions,
                              isSaving: _isSaving,
                              customController: _issueCustomController,
                              onToggleItem: _toggleStagedItem,
                              onIssueOption: _onIssueOption,
                              onIssueCustomText: _onIssueCustomText,
                              onOpenProblemOption: _onOpenProblemOption,
                            ),
                          ],
                        ],
                      ],
                    ),
                  ),
                ),
                if (!_isLoadedEncounterSummary && _enRevisionCaptura)
                  CaptureActionsBar(
                    isSaving: _isSaving,
                    canConfirm: _canConfirmCapture,
                    onConfirm: _confirmarGuardado,
                    onBackToNotes: _backToNotes,
                  ),
                if (!_isLoadedEncounterSummary &&
                    _showCaptureBar &&
                    !_enRevisionCaptura &&
                    _notes.isNotEmpty)
                  CaptureAnalyzeBar(
                    noteCount: _notes.length,
                    enabled: canAnalyzeNotes(_notes) && !_captureBusy,
                    analyzing: _isAnalyzing,
                    onAnalyze: _analyzeDraft,
                    blockedHint: canAnalyzeNotes(_notes)
                        ? null
                        : AppTexts.noteAnalyzeBlocked,
                  ),
                if (!_isLoadedEncounterSummary &&
                    _showCaptureBar &&
                    !_enRevisionCaptura)
                  CaptureInputBar(
                    controller: _chatController,
                    focusNode: _chatFocusNode,
                    onSend: _addNote,
                    busy: _captureBusy,
                    canCapture: _tieneContextoCaptura,
                    isEpisode: _esCapturaEpisodio,
                    sttStatus: _sttStatus,
                    dictating: _dictating || _audioOnlyRecording,
                    onVoice: _tieneContextoCaptura && _sttConfigLoaded && (_sttConfig.deviceEnabled || _sttConfig.serverEnabled) ? _voice.toggle : null,
                  ),
              ],
            ),
    );
  }



  Future<void> _onEpisodioAccion(EpisodioAccionHistoria accion) async {
    if (accion.kind == 'native_triage') {
      final guardiaId =
          widget.parentId ??
          _historiaClinicaData?.contextoEpisodio?.episodioId ??
          0;
      if (guardiaId <= 0) return;
      final token = widget.authToken;
      if (token == null || token.isEmpty) return;
      final ctx = _historiaClinicaData?.contextoEpisodio;
      final persona = _historiaClinicaData?.persona;
      final ok = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => EmergencyTriageScreen(
            guardiaId: guardiaId,
            pacienteNombre: persona?.nombreCompleto ?? 'Paciente',
            api: EmergencyGuardiaApi(authToken: token),
            isRetriage: ctx?.triageLevel != null,
            initialLevel: ctx?.triageLevel,
            initialReason: ctx?.motivo,
          ),
        ),
      );
      if (ok == true && mounted) {
        await _cargarHistoriaClinica();
      }
      return;
    }

    final route = accion.apiRoute;
    final method = (accion.apiMethod ?? 'GET').toUpperCase();
    if (accion.kind != 'ui_json' || route == null || route.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(AppTexts.timelineActionNoScreen),
        ),
      );
      return;
    }
    if (method != 'GET') {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AppTexts.timelineActionMethodUnsupported(method)),
        ),
      );
      return;
    }
    final uri = Uri.parse(resolveApiAbsoluteUrl(route));
    if (!mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => UiJsonScreen(
          apiAbsoluteUrl: uri.toString(),
          authToken: widget.authToken,
          appClient: 'bioenlace-personalsalud',
          title: accion.label,
          onSubmitSuccess: (_) async {
            await _cargarHistoriaClinica();
          },
        ),
      ),
    );
    await _cargarHistoriaClinica();
  }

  // Notas del borrador: toda la cola local salvo la que se está componiendo ahora.
  List<PendingEncounterCapture> get _notes => draftNotes(
    _pendingCaptures,
    composingId: _enRevisionCaptura ? null : _activePendingId,
  );

  bool get _tieneContextoCaptura =>
      widget.parentType != null &&
      widget.parentType!.isNotEmpty &&
      widget.parentId != null;

  Future<void> _showMicrophonePermissionDialog({
    bool permissionRequestFailed = false,
  }) async {
    if (!mounted) return;
    await showMicrophonePermissionDialog(
      context,
      permissionRequestFailed: permissionRequestFailed,
      onRetry: () async {
        try {
          final granted = await _voice.hasMicrophonePermission();
          if (!mounted) return;
          if (granted) {
            await _voice.startAudioOnly();
          } else {
            await _showMicrophonePermissionDialog(permissionRequestFailed: true);
          }
        } catch (_) {
          if (!mounted) return;
          await _showMicrophonePermissionDialog(permissionRequestFailed: true);
        }
      },
    );
  }

  Future<Map<String, dynamic>?> _operationalContextForCapture() async {
    final prefs = await SharedPreferences.getInstance();
    final pes = prefs.getInt('id_profesional_efector_servicio') ?? 0;
    if (pes <= 0) return null;
    final servicio = prefs.getInt('servicio_id');
    return {
      'id_profesional_efector_servicio': pes,
      if (servicio != null && servicio > 0) 'servicio_actual': servicio,
    };
  }

  String _mensajeErrorCaptura(Object e) {
    final friendly = userFriendlyErrorMessage(e);
    if (isRetryableNetworkError(e)) {
      return '$friendly La nota quedó en el teléfono; reintentá cuando haya conexión.';
    }
    return friendly;
  }

  void _clearIssueResolutions() => _resolutions.clearResolutions();

  TextEditingController _issueCustomController(String issueId) =>
      _resolutions.customController(issueId);

  void _clearCaptureDraft({bool deleteLocalPending = true}) {
    final pendingId = _activePendingId;
    setState(() {
      _captureReview = null;
      _lastAnalysis = null;
      _draftText = null;
      _stagedItemIds = {};
      _clearIssueResolutions();
      _lastDictation = null;
      _sttStatus = '';
      _pendingAudioPath = null;
      _activePendingId = null;
      _chatController.clear();
    });
    if (deleteLocalPending && pendingId != null) {
      _pendingStore.delete(pendingId).then((_) => _loadPendingCaptures());
    }
  }

  Future<PendingEncounterCapture?> _persistLocalDraft({
    required String texto,
    PendingEncounterCaptureStatus status = PendingEncounterCaptureStatus.draft,
    String? error,
    Map<String, dynamic>? analysisResponse,
    List<String>? stagedItemIds,
    int? serverCaptureId,
    String? serverStage,
    String? serverStageLabel,
    String? serverStageIntent,
    bool? audioUploaded,
    bool clearError = false,
    bool clearAnalysis = false,
  }) async {
    if (!_tieneContextoCaptura) return null;
    final now = DateTime.now();
    final id = _activePendingId ?? _pendingStore.newId();
    String? audioName;
    final existing = await _pendingStore.getById(id);
    audioName = existing?.audioFileName;

    final tempAudio = _pendingAudioPath;
    if (tempAudio != null && tempAudio.isNotEmpty) {
      final storedAudioPath = existing == null
          ? null
          : await _pendingStore.absoluteAudioPath(existing);
      final alreadyDurable =
          storedAudioPath != null &&
          storedAudioPath.replaceAll('\\', '/') ==
              tempAudio.replaceAll('\\', '/');
      if (!alreadyDurable) {
        try {
          final imported = await _pendingStore.importAudioFile(
            captureId: id,
            sourcePath: tempAudio,
          );
          if (imported != null) {
            audioName = imported;
            if (!kIsWeb) {
              final durable = await _pendingStore.absoluteAudioPath(
                PendingEncounterCapture(
                  id: id,
                  personaId: widget.personaId,
                  parent: widget.parentType!,
                  parentId: widget.parentId!,
                  texto: texto,
                  status: status,
                  createdAt: now,
                  updatedAt: now,
                  audioFileName: imported,
                ),
              );
              if (durable != null) {
                _pendingAudioPath = durable;
              }
            }
          }
        } catch (e, st) {
          debugPrint('[captura] importAudio omitido: $e\n$st');
        }
      }
    }

    Map<String, dynamic>? sttPayload;
    final last = _lastDictation;
    if (last != null && texto.trim() == last.text.trim()) {
      sttPayload = last.toSttPayload();
    }

    final item = PendingEncounterCapture(
      id: id,
      personaId: widget.personaId,
      parent: widget.parentType!,
      parentId: widget.parentId!,
      texto: texto,
      status: status,
      createdAt: existing?.createdAt ?? now,
      updatedAt: now,
      serverCaptureId: serverCaptureId ?? existing?.serverCaptureId,
      serverStage: serverStage ?? existing?.serverStage,
      serverStageLabel: serverStageLabel ?? existing?.serverStageLabel,
      serverStageIntent: serverStageIntent ?? existing?.serverStageIntent,
      audioFileName: audioName,
      stt: sttPayload ?? existing?.stt,
      lastError: clearError ? null : (error ?? existing?.lastError),
      analysisResponse: clearAnalysis
          ? null
          : (analysisResponse ?? existing?.analysisResponse),
      stagedItemIds: stagedItemIds ?? existing?.stagedItemIds ?? const [],
      audioUploaded: audioUploaded ?? existing?.audioUploaded ?? false,
    );
    await _pendingStore.upsert(item);
    _activePendingId = id;
    // Actualizar lista local sin re-sincronizar servidor (evita cuelgues / race).
    if (mounted) {
      final next = List<PendingEncounterCapture>.from(_pendingCaptures);
      final idx = next.indexWhere((e) => e.id == item.id);
      if (idx >= 0) {
        next[idx] = item;
      } else {
        next.insert(0, item);
      }
      setState(() => _pendingCaptures = next);
    }
    return item;
  }

  Future<bool> _confirmDeleteAudio() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text(AppTexts.noteDeleteAudioTitle),
        content: const Text(AppTexts.noteDeleteAudioBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text(AppTexts.noteCancel),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text(AppTexts.noteDelete),
          ),
        ],
      ),
    );
    return ok == true;
  }

  Future<void> _deleteNote(PendingEncounterCapture note) async {
    if (_captureBusy) return;
    if (note.hasAudio && !await _confirmDeleteAudio()) return;
    await _playback.stop();

    if (note.serverCaptureId != null) {
      try {
        await _encounterApi.capturaDescartar(
          clientCaptureId: note.id,
          captureId: note.serverCaptureId,
        );
      } catch (_) {
        if (mounted) _snack(AppTexts.captureDeleteFailed, UiIntent.danger);
        return;
      }
    }
    await _pendingStore.delete(note.id);

    // Con una nota menos el análisis visible ya no corresponde al borrador.
    if (_enRevisionCaptura) _discardReview();
    await _loadPendingCaptures();
    if (mounted) _snack(AppTexts.captureDeleted, UiIntent.neutral);
  }

  /// Cierra la revisión sin tocar las notas, para sumar o borrar notas y volver a analizar.
  void _discardReview() {
    setState(() {
      _captureReview = null;
      _lastAnalysis = null;
      _draftText = null;
      _stagedItemIds = {};
      _clearIssueResolutions();
      _activePendingId = null;
      _sttStatus = '';
      _draftExpanded = true;
      _autoOpenedReview = true;
    });
  }

  Future<void> _togglePlayNote(PendingEncounterCapture note) async {
    final path = await _pendingStore.absoluteAudioPath(note);
    if (path == null) {
      if (mounted) _snack(AppTexts.noteAudioUnavailable, UiIntent.warning);
      return;
    }
    try {
      await _playback.toggle(note.id, path);
    } catch (_) {
      if (mounted) _snack(AppTexts.noteAudioUnavailable, UiIntent.warning);
    }
  }

  Future<void> _resumePendingCapture(PendingEncounterCapture item) async {
    if (_captureBusy) return;
    var current = item;
    final needsReview =
        current.status == PendingEncounterCaptureStatus.pendingSave ||
        current.status == PendingEncounterCaptureStatus.failedSave;
    if (needsReview && current.analysisResponse == null) {
      try {
        final detail = await _encounterApi.capturaVer(
          clientCaptureId: current.id,
          captureId: current.serverCaptureId,
        );
        final capture = detail['capture'];
        if (capture is Map) {
          current = PendingEncounterCapture.fromServerCapture(
            Map<String, dynamic>.from(capture),
            local: current,
          );
          await _pendingStore.upsert(current);
        }
      } catch (_) {}
    }

    final audioPath = await _pendingStore.absoluteAudioPath(current);
    if (!mounted) return;

    if ((current.status == PendingEncounterCaptureStatus.pendingSave ||
            current.status == PendingEncounterCaptureStatus.failedSave) &&
        current.analysisResponse != null) {
      final review = EncounterCaptureAnalysis.fromApiResponse(
        current.analysisResponse!,
      );
      setState(() {
        _activePendingId = current.id;
        _pendingAudioPath = audioPath;
        _lastAnalysis = current.analysisResponse;
        _draftText = current.texto;
        _captureReview = review;
        _clearIssueResolutions();
        _stagedItemIds = current.stagedItemIds.isNotEmpty
            ? current.stagedItemIds.toSet()
            : review.allItems.map((e) => e.id).toSet();
        _chatController.clear();
        _sttStatus = current.status == PendingEncounterCaptureStatus.failedSave
            ? (current.lastError?.trim().isNotEmpty == true
                  ? userFriendlyErrorMessage(current.lastError!)
                  : AppTexts.captureSaveFailed)
            : '';
      });
      return;
    }

    setState(() {
      _activePendingId = current.id;
      _pendingAudioPath = audioPath;
      _captureReview = null;
      _lastAnalysis = null;
      _draftText = null;
      _stagedItemIds = {};
      _clearIssueResolutions();
      _chatController.text = current.texto.startsWith('(audio')
          ? ''
          : current.texto;
      _sttStatus = current.lastError != null && current.lastError!.isNotEmpty
          ? userFriendlyErrorMessage(current.lastError!)
          : '';
    });
  }

  Future<void> _retryNote(PendingEncounterCapture item) async {
    if (_captureBusy) return;
    final audioPath = await _pendingStore.absoluteAudioPath(item);
    if (!mounted) return;
    setState(() {
      _activePendingId = item.id;
      _pendingAudioPath = audioPath;
      _chatController.text = item.texto.startsWith('(audio') ? '' : item.texto;
    });
    await _storeActiveNote(fromStatus: item.status, forcePipeline: true);
  }

  /// Cierra la revisión para sumar o borrar notas y volver a analizar.
  void _backToNotes() {
    if (_isSaving) return;
    _discardReview();
  }

  void _toggleStagedItem(String id, bool selected) {
    setState(() => _resolutions.toggleStagedItem(id, selected));
  }

  /// Agrega la nota escrita o dictada al borrador: la guarda en el teléfono,
  /// la sube y, si es audio, la transcribe. No analiza.
  Future<void> _addNote() async {
    if (!_tieneContextoCaptura) {
      if (!widget.soloVer) {
        _snack(AppTexts.captureDefineParent, UiIntent.warning);
      }
      return;
    }
    if (_enRevisionCaptura || _captureBusy) return;
    if (_dictating || _audioOnlyRecording) {
      await _voice.toggle();
      if (_dictating || _audioOnlyRecording) return;
    }
    final text = _chatController.text.trim();
    if (text.isEmpty && _pendingAudioPath == null) {
      _snack(
        _esCapturaEpisodio
            ? AppTexts.captureWriteEvolution
            : AppTexts.captureWriteNote,
        UiIntent.warning,
      );
      return;
    }
    if (_notes.length >= maxDraftNotes) {
      _snack(AppTexts.noteLimitReached, UiIntent.warning);
      return;
    }
    await _storeActiveNote();
  }

  /// Sube la nota en composición. Si falla queda en la lista con su error para reintentar.
  Future<void> _storeActiveNote({
    PendingEncounterCaptureStatus? fromStatus,
    bool forcePipeline = false,
  }) async {
    await _playback.stop();
    setState(() {
      _isAddingNote = true;
      _sttStatus = '';
    });
    var stored = false;
    try {
      stored = await _uploadActiveNote(
        fromStatus: fromStatus,
        forcePipeline: forcePipeline,
      );
    } finally {
      if (mounted) {
        setState(() {
          _isAddingNote = false;
          // La nota ya es parte del borrador (lista) tanto si subió como si falló.
          _activePendingId = null;
          _lastDictation = null;
          _pendingAudioPath = null;
          _chatController.clear();
          if (stored) {
            _historyExpanded = false;
            _draftExpanded = true;
          }
        });
      }
    }
  }

  Future<bool> _uploadActiveNote({
    PendingEncounterCaptureStatus? fromStatus,
    bool forcePipeline = false,
  }) async {
    final text = _chatController.text.trim();
    if (!_tieneContextoCaptura) {
      _snack(AppTexts.captureMissingParent, UiIntent.warning);
      return false;
    }

    final startStatus =
        fromStatus ??
        (text.isEmpty
            ? PendingEncounterCaptureStatus.pendingUpload
            : PendingEncounterCaptureStatus.pendingAnalyze);

    try {
      // Primero persistir en el dispositivo (texto + audio); luego pipeline servidor.
      final draft = await _persistLocalDraft(
        texto: text.isEmpty ? '(audio pendiente de transcribir)' : text,
        status: startStatus,
        clearError: true,
        clearAnalysis: true,
      );
      if (draft == null) {
        return false;
      }

      final ctx = await _operationalContextForCapture();
      final last = _lastDictation;
      Map<String, dynamic>? sttMeta;
      if (last != null && text.isNotEmpty && text == last.text.trim()) {
        sttMeta = last.toSttPayload();
      }

      var stage = draft.serverStage;
      var stageLabel = draft.serverStageLabel;
      var stageIntent = draft.serverStageIntent;
      var serverId = draft.serverCaptureId;
      var audioUploaded = draft.audioUploaded;
      var transcript = text;

      final skipUpload =
          forcePipeline &&
          (fromStatus == PendingEncounterCaptureStatus.pendingStt ||
              fromStatus == PendingEncounterCaptureStatus.pendingAnalyze) &&
          (draft.serverCaptureId != null || draft.audioUploaded);

      if (!skipUpload) {
        final audioPath =
            await _pendingStore.absoluteAudioPath(draft) ?? _pendingAudioPath;
        try {
          final created = await _encounterApi.capturaCrearOSubir(
            clientCaptureId: draft.id,
            idPersona: widget.personaId,
            parent: widget.parentType,
            parentId: widget.parentId,
            transcript: text.isEmpty ? null : text,
            stt: sttMeta,
            audioPath: audioPath,
            userPerTabConfig: ctx,
          );
          final snap = CaptureServerSnapshot.fromResponse(created);
          if (snap != null) {
            stage = snap.stage;
            stageLabel = snap.stageLabel;
            stageIntent = snap.stageIntent;
            serverId = snap.id;
            audioUploaded = snap.hasAudio;
            if (snap.transcript.isNotEmpty) transcript = snap.transcript;
          }
          await _persistLocalDraft(
            texto: transcript.isEmpty
                ? '(audio pendiente de transcribir)'
                : transcript,
            status: PendingEncounterCapture.statusFromServerStage(
              stage ?? 'UPLOADED',
            ),
            serverCaptureId: serverId,
            serverStage: stage,
            serverStageLabel: stageLabel,
            serverStageIntent: stageIntent,
            audioUploaded: audioUploaded,
            clearError: true,
          );
        } catch (e) {
          await _persistLocalDraft(
            texto: text.isEmpty ? '(audio pendiente de transcribir)' : text,
            status: PendingEncounterCaptureStatus.pendingUpload,
            error: _mensajeErrorCaptura(e),
          );
          if (!mounted) return false;
          _snack(_mensajeErrorCaptura(e), UiIntent.danger);
          return false;
        }
      }

      final needsStt =
          _sttConfig.serverEnabled &&
          (stage == 'UPLOADED' ||
              stage == 'STT_FAILED' ||
              fromStatus == PendingEncounterCaptureStatus.pendingStt);

      if (needsStt &&
          fromStatus != PendingEncounterCaptureStatus.pendingAnalyze) {
        try {
          final sttRes = await _encounterApi.capturaTranscribir(
            clientCaptureId: draft.id,
            captureId: serverId,
            force: stage == 'STT_FAILED',
            userPerTabConfig: ctx,
          );
          final snap = CaptureServerSnapshot.fromResponse(sttRes);
          if (snap != null) {
            stage = snap.stage;
            stageLabel = snap.stageLabel;
            stageIntent = snap.stageIntent;
            serverId = snap.id;
            if (snap.transcript.isNotEmpty) {
              transcript = snap.transcript;
              if (mounted) {
                setState(() => _chatController.text = snap.transcript);
              }
            }
          }
          await _persistLocalDraft(
            texto: transcript,
            status: PendingEncounterCapture.statusFromServerStage(
              stage ?? 'TRANSCRIBED',
            ),
            serverCaptureId: serverId,
            serverStage: stage,
            serverStageLabel: stageLabel,
            serverStageIntent: stageIntent,
            audioUploaded: true,
            clearError: true,
          );
        } catch (e) {
          await _persistLocalDraft(
            texto: transcript.isEmpty
                ? '(audio pendiente de transcribir)'
                : transcript,
            status: PendingEncounterCaptureStatus.pendingStt,
            serverCaptureId: serverId,
            serverStage: stage ?? 'STT_FAILED',
            audioUploaded: audioUploaded,
            error: _mensajeErrorCaptura(e),
          );
          if (!mounted) return false;
          _snack(_mensajeErrorCaptura(e), UiIntent.danger);
          return false;
        }
      }

      if (transcript.trim().isEmpty && !_sttConfig.serverEnabled) {
        const message = AppTexts.captureDictationEmpty;
        await _persistLocalDraft(
          texto: '(audio pendiente de transcribir)',
          status: PendingEncounterCaptureStatus.pendingStt,
          serverCaptureId: serverId,
          serverStage: stage,
          audioUploaded: audioUploaded,
          error: message,
        );
        if (mounted) _snack(message, UiIntent.warning);
        return false;
      }

      if (transcript.trim().isEmpty) {
        await _persistLocalDraft(
          texto: '(audio pendiente de transcribir)',
          status: PendingEncounterCaptureStatus.pendingStt,
          serverCaptureId: serverId,
          serverStage: stage,
          error: AppTexts.captureNothingToAnalyze,
        );
        if (!mounted) return false;
        _snack(AppTexts.captureNoText, UiIntent.warning);
        return false;
      }

      if (stage == 'UPLOADED' || stage == 'STT_FAILED') {
        stage = 'TRANSCRIBED';
      }
      await _persistLocalDraft(
        texto: transcript,
        status: PendingEncounterCaptureStatus.pendingAnalyze,
        serverCaptureId: serverId,
        serverStage: stage ?? 'TRANSCRIBED',
        audioUploaded: audioUploaded,
        clearError: true,
      );
      return true;
    } catch (e, st) {
      debugPrint('[captura] subir nota falló: $e\n$st');
      try {
        await _persistLocalDraft(
          texto: text.isEmpty ? '(audio pendiente de transcribir)' : text,
          status: PendingEncounterCaptureStatus.pendingUpload,
          error: _mensajeErrorCaptura(e),
        );
      } catch (_) {}
      if (!mounted) return false;
      _snack(_mensajeErrorCaptura(e), UiIntent.danger);
      return false;
    } finally {
      await _loadPendingCaptures();
    }
  }

  /// Analiza todas las notas del borrador juntas con una sola llamada.
  Future<void> _analyzeDraft() async {
    final notes = _notes;
    if (_captureBusy || !canAnalyzeNotes(notes)) return;
    final primary = primaryNote(notes);
    if (primary == null) return;
    await _playback.stop();
    setState(() {
      _isAnalyzing = true;
      _sttStatus = '';
    });
    try {
      final ctx = await _operationalContextForCapture();
      final analyzed = await _encounterApi.capturaAnalizar(
        clientCaptureId: primary.id,
        captureId: primary.serverCaptureId,
        batchClientCaptureIds: batchClientIds(notes),
        force: true,
        userPerTabConfig: ctx,
      );
      final snap = CaptureServerSnapshot.fromResponse(analyzed);
      final analysisPayload = buildAnalysisPayload(analyzed, snap);
      if (!mounted) return;
      final review = EncounterCaptureAnalysis.fromApiResponse(analysisPayload);
      final staged = defaultStagedItems(review);
      _activePendingId = primary.id;
      await _persistLocalDraft(
        texto: primary.texto,
        status: PendingEncounterCaptureStatus.pendingSave,
        analysisResponse: analysisPayload,
        stagedItemIds: staged.toList(),
        serverCaptureId: snap?.id ?? primary.serverCaptureId,
        serverStage: snap?.stage ?? 'READY_FOR_REVIEW',
        audioUploaded: primary.audioUploaded,
        clearError: true,
      );
      if (!mounted) return;
      setState(() {
        _lastAnalysis = analysisPayload;
        _draftText = review.textoOriginal;
        _captureReview = review;
        _clearIssueResolutions();
        _stagedItemIds = staged;
        _historyExpanded = false;
        _sttStatus = '';
      });
    } catch (e, st) {
      debugPrint('[captura] analizar borrador falló: $e\n$st');
      _activePendingId = null;
      if (!mounted) return;
      _snack(_mensajeErrorCaptura(e), UiIntent.danger);
    } finally {
      if (mounted) setState(() => _isAnalyzing = false);
    }
  }

  bool get _canConfirmCapture {
    final review = _captureReview;
    return !_isSaving &&
        review != null &&
        review.systemError == null &&
        !review.episodeNoteDuplicate &&
        review.textoProcesado != null &&
        review.missingCategories.isEmpty &&
        _allStagedIssuesResolved(review);
  }

  bool _allStagedIssuesResolved(EncounterCaptureAnalysis review) =>
      _resolutions.allIssuesResolved(review);

  Future<void> _confirmarGuardado() async {
    final review = _captureReview;
    if (review == null || _lastAnalysis == null) {
      _snack(AppTexts.captureAnalyzeFirst, UiIntent.warning);
      return;
    }
    if (review.systemError != null) {
      _snack(
        AppTexts.captureSaveBlockedBySystemError,
        UiIntent.warning,
      );
      return;
    }
    if (review.episodeNoteDuplicate) {
      String? dupMsg;
      for (final a in review.advisories) {
        if (a.code == 'episode_note_duplicate' && a.message.trim().isNotEmpty) {
          dupMsg = a.message;
          break;
        }
      }
      _snack(
        dupMsg ?? AppTexts.captureEditDuplicateNote,
        UiIntent.warning,
      );
      return;
    }
    if (!_allStagedIssuesResolved(review)) {
      _snack(
        AppTexts.captureResolveMissingData,
        UiIntent.warning,
      );
      return;
    }
    final saveIds = review.effectiveSaveItemIds(_stagedItemIds);
    final extraidos = review.toDatosExtraidos(saveIds);
    final textoOriginal =
        (_lastAnalysis!['texto_original'] ?? review.textoOriginal).toString();
    final textoProcesado =
        (_lastAnalysis!['texto_procesado'] ??
                review.textoProcesado ??
                _draftText ??
                textoOriginal)
            .toString();
    final idConfiguracion = _lastAnalysis!['id_configuracion'] is int
        ? _lastAnalysis!['id_configuracion'] as int
        : int.tryParse('${_lastAnalysis!['id_configuracion']}');
    final encounterId = _lastAnalysis!['encounter_id'] is int
        ? _lastAnalysis!['encounter_id'] as int
        : int.tryParse('${_lastAnalysis!['encounter_id']}');

    final resolutions = <String, dynamic>{};
    for (final issue in review.issues) {
      if (_issueResolutions.containsKey(issue.id)) {
        resolutions[issue.id] = _issueResolutions[issue.id];
        continue;
      }
      if (issue.allowCustom) {
        final custom = _resolutions.customText(issue.id).trim();
        if (custom.isNotEmpty) {
          resolutions[issue.id] = custom;
        }
      }
    }

    setState(() => _isSaving = true);
    try {
      // Persistir payload localmente antes de subir (reintento si falla la red).
      await _persistLocalDraft(
        texto: textoOriginal,
        status: PendingEncounterCaptureStatus.pendingSave,
        analysisResponse: _lastAnalysis,
        stagedItemIds: _stagedItemIds.toList(),
        clearError: true,
      );

      final clientId = _activePendingId;
      final conditionResolutions = <String, dynamic>{};
      final carePlanResolutions = <String, dynamic>{};
      _openProblemResolutions.forEach((key, value) {
        final parts = key.split(':');
        if (parts.length != 2) return;
        if (parts[0] == 'care_plan') {
          carePlanResolutions[parts[1]] = value;
        } else {
          conditionResolutions[parts[1]] = value;
        }
      });
      final batchIds = batchClientIds(_notes);
      Map<String, dynamic> guardado;
      if (clientId != null && clientId.isNotEmpty) {
        final out = await _encounterApi.capturaGuardar(
          clientCaptureId: clientId,
          batchClientCaptureIds: batchIds.length > 1 ? batchIds : null,
          datosExtraidos: extraidos,
          // Draft en servidor: el pipeline usa el análisis persistido; no reenviar backup.
          stagedItemIds: _stagedItemIds.toList(),
          textoOriginal: textoOriginal,
          textoProcesado: textoProcesado,
          userPerTabConfig: await _operationalContextForCapture(),
          conditionResolutions: conditionResolutions,
          carePlanResolutions: carePlanResolutions,
          resolutions: resolutions.isEmpty ? null : resolutions,
        );
        final nested = out['guardar'];
        guardado = nested is Map ? Map<String, dynamic>.from(nested) : out;
      } else {
        guardado = await _encounterApi.guardar(
          idPersona: widget.personaId,
          parent: widget.parentType,
          parentId: widget.parentId,
          datosExtraidos: extraidos,
          // Sin draft de captura: solo token de cache (si hay), no mapa completo duplicado.
          analysisCacheToken: _lastAnalysis!['analysis_cache_token']
              ?.toString(),
          textoOriginal: textoOriginal,
          textoProcesado: textoProcesado,
          idConfiguracion: idConfiguracion,
          encounterId: encounterId,
          userPerTabConfig: await _operationalContextForCapture(),
          conditionResolutions: conditionResolutions,
          carePlanResolutions: carePlanResolutions,
          resolutions: resolutions.isEmpty ? null : resolutions,
        );
      }
      if (!mounted) return;
      final aviso = guardado['persist_incomplete'] == true
          ? (guardado['message']?.toString() ??
                AppTexts.captureSavedIncomplete)
          : null;
      debugPrint(
        '[captura] guardar resultado aviso=${aviso ?? 'ok'} '
        'persistido=${guardado['persistido']} '
        'diagnostico=${guardado['diagnostico_guardar']} '
        'log_id=${guardado['log_id']}',
      );
      if (aviso != null) {
        // No hacer pop inmediato: el SnackBar se pierde al cerrar la pantalla.
        await _mostrarResultadoGuardado(
          titulo: AppTexts.captureSavedIncompleteTitle,
          mensaje: aviso,
          intent: UiIntent.warning,
          detalle: captureSaveDiagnosticDetail(guardado),
        );
      }
      final doneId = _activePendingId;
      final doneIds = <String>{...batchClientIds(_notes), ?doneId};
      _clearCaptureDraft(deleteLocalPending: false);
      for (final id in doneIds) {
        await _pendingStore.delete(id);
      }
      if (doneIds.isNotEmpty) await _loadPendingCaptures();
      if (!mounted) return;
      // El aviso de éxito lo muestra la pantalla anterior (home / agenda).
      Navigator.of(context).pop(true);
    } catch (e, st) {
      debugPrint('[captura] Error al guardar: $e\n$st');
      await _persistLocalDraft(
        texto: textoOriginal,
        status: PendingEncounterCaptureStatus.failedSave,
        analysisResponse: _lastAnalysis,
        stagedItemIds: _stagedItemIds.toList(),
        error: _mensajeErrorCaptura(e),
      );
      if (!mounted) return;
      await _mostrarResultadoGuardado(
        titulo: AppTexts.captureSaveFailed,
        mensaje: _mensajeErrorCaptura(e),
        intent: UiIntent.danger,
      );
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  Future<void> _mostrarResultadoGuardado({
    required String titulo,
    required String mensaje,
    required UiIntent intent,
    String? detalle,
  }) async {
    if (!mounted) return;
    await showCaptureSaveResultDialog(
      context,
      titulo: titulo,
      mensaje: mensaje,
      intent: intent,
      detalle: detalle,
    );
  }

  void _onIssueOption(EncounterCaptureIssue issue, dynamic value, bool wasSelected) {
    setState(() => _resolutions.selectIssueOption(issue.id, value, wasSelected));
  }

  void _onIssueCustomText(EncounterCaptureIssue issue, String trimmed) {
    setState(() => _resolutions.setIssueCustomText(issue, trimmed));
  }

  void _onOpenProblemOption(String key, dynamic value, bool wasSelected) {
    setState(() => _resolutions.selectOpenProblemOption(key, value, wasSelected));
  }

  void _snack(String msg, UiIntent intent) {
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(msg), backgroundColor: palette.base));
  }
}
