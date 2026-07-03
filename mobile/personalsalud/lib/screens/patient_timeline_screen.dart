// lib/screens/patient_timeline_screen.dart
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';

import '../models/timeline_event.dart';
import '../services/historia_clinica_service.dart';
import '../services/consulta_guardar_service.dart';

class PatientTimelineScreen extends StatefulWidget {
  final int personaId;
  final String? authToken;

  /// true = solo ver historia clínica (sin formulario); false = barra para escribir notas
  final bool soloVer;

  /// Contexto de consulta (ej. `CIRUGIA`, `TURNO`) alineado con web / `validarPermisoAtencion`.
  final String? consultParent;
  final int? consultParentId;

  const PatientTimelineScreen({
    Key? key,
    required this.personaId,
    this.authToken,
    this.soloVer = true,
    this.consultParent,
    this.consultParentId,
  }) : super(key: key);

  @override
  State<PatientTimelineScreen> createState() => _PatientTimelineScreenState();
}

class _PatientTimelineScreenState extends State<PatientTimelineScreen> {
  final HistoriaClinicaService _historiaClinicaService =
      HistoriaClinicaService();
  final ConsultaGuardarService _consultaGuardar = ConsultaGuardarService();
  final EncounterCaptureApi _encounterApi = EncounterCaptureApi();
  final DeviceSpeechDictation _dictation = DeviceSpeechDictation();
  final AudioRecorder _audioRecorder = AudioRecorder();
  HistoriaClinicaResponse? _historiaClinicaData;
  bool _isLoading = true;
  String _errorMessage = '';
  bool _guardandoConsulta = false;
  bool _dictating = false;
  String? _pendingAudioPath;
  DeviceDictationResult? _lastDictation;
  Map<String, dynamic>? _lastAnalysis;
  String _sttStatus = '';
  SttClientConfig _sttConfig = SttClientConfig.defaults;
  bool _audioOnlyRecording = false;

  final TextEditingController _chatController = TextEditingController();
  final FocusNode _chatFocusNode = FocusNode();

  bool get _mostrarBarraConsulta =>
      !widget.soloVer ||
      (widget.consultParent != null &&
          widget.consultParent!.isNotEmpty &&
          widget.consultParentId != null);

  @override
  void initState() {
    super.initState();
    if (widget.authToken != null) {
      _historiaClinicaService.authToken = widget.authToken;
      _consultaGuardar.authToken = widget.authToken;
      _encounterApi.authToken = widget.authToken;
    }
    _dictation.initialize();
    _loadSttConfig();
    _cargarHistoriaClinica();
  }

  Future<void> _loadSttConfig() async {
    try {
      final cfg = await _encounterApi.fetchSttConfig();
      if (!mounted) return;
      setState(() => _sttConfig = cfg);
    } catch (_) {
      // Mantener defaults locales.
    }
  }

  @override
  void dispose() {
    _chatController.dispose();
    _chatFocusNode.dispose();
    super.dispose();
  }

  Future<void> _cargarHistoriaClinica() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final int? turnoId = widget.consultParent == 'TURNO'
          ? widget.consultParentId
          : null;
      final data = await _historiaClinicaService.getHistoriaClinica(
        widget.personaId,
        turnoId: turnoId,
      );
      if (!mounted) return;
      setState(() {
        _historiaClinicaData = data;
        _isLoading = false;
      });
    } on HistoriaClinicaVentanaException catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = e.message;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Error al cargar historia clínica: ${e.toString()}';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Historia clínica'),
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
                        child: BioAlert.info(
                          message: 'No hay datos disponibles',
                        ),
                      ),
                    )
                  : Column(
                      children: [
                        Expanded(
                          child: SingleChildScrollView(
                            padding: const EdgeInsets.all(BioSpacing.md),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _buildPacienteHeader(
                                    _historiaClinicaData!.persona),
                                BioSpacing.gapH(BioSpacing.md),
                                _buildInformacionMedica(
                                    _historiaClinicaData!.informacionMedica),
                                BioSpacing.gapH(BioSpacing.md),
                                _buildSignosVitales(
                                    _historiaClinicaData!.signosVitales),
                                BioSpacing.gapH(BioSpacing.md),
                                if (_historiaClinicaData!
                                        .motivosConsultaPaciente.motivosIntake
                                        ?.tieneContenido ==
                                    true) ...[
                                  _buildMotivosIntake(
                                    _historiaClinicaData!
                                        .motivosConsultaPaciente.motivosIntake!,
                                  ),
                                  BioSpacing.gapH(BioSpacing.md),
                                ],
                                _buildMotivosConsulta(_historiaClinicaData!),
                                if (_historiaClinicaData!.carePackCohorte
                                        ?.tieneContenido ==
                                    true) ...[
                                  BioSpacing.gapH(BioSpacing.md),
                                  _buildCarePackCohorte(
                                    _historiaClinicaData!.carePackCohorte!,
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                        if (_mostrarBarraConsulta) _buildChatInputBar(context),
                      ],
                    ),
    );
  }

  Widget _buildPacienteHeader(PersonaData persona) {
    return BioCard.intent(
      intent: UiIntent.primary,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(persona.nombreCompleto, style: BioTypography.h3),
          ),
          if (persona.edad != null)
            Text('${persona.edad} años', style: BioTypography.title),
        ],
      ),
    );
  }

  String? _signoValorNodo(dynamic node, String key) {
    if (node is! Map) return null;
    final v = node[key];
    if (v == null) return null;
    final s = v.toString().trim();
    return s.isEmpty ? null : s;
  }

  Widget _buildSignosVitales(SignosVitalesClinica sv) {
    final u = sv.ultimosSv;
    final peso = _signoValorNodo(u?['peso'], 'value');
    final talla = _signoValorNodo(u?['talla'], 'value');
    final imc = _signoValorNodo(u?['imc'], 'value');
    String? tension;
    final ta = u?['ta'];
    if (ta is Map) {
      final sys = ta['sistolica']?.toString().trim() ?? '';
      final dia = ta['diastolica']?.toString().trim() ?? '';
      if (sys.isNotEmpty && dia.isNotEmpty) {
        tension = '$sys/$dia mmHg';
      }
    }
    final hayResumen =
        peso != null || talla != null || imc != null || tension != null;

    final titulo = sv.fechaTitulo.isNotEmpty
        ? 'Signos vitales actuales (${sv.fechaTitulo})'
        : 'Signos vitales actuales';

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(titulo, style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.md),
          if (!hayResumen)
            Text('Sin datos', style: BioTypography.bodySm)
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (peso != null) Text('Peso: $peso kg', style: BioTypography.body),
                if (talla != null)
                  Text('Altura: $talla cm', style: BioTypography.body),
                if (imc != null) Text('IMC: $imc', style: BioTypography.body),
                if (tension != null)
                  Text('Tensión arterial: $tension', style: BioTypography.body),
              ],
            ),
          if (sv.datosSv.isNotEmpty &&
              (sv.tieneMasSv || sv.datosSv.length > 1)) ...[
            BioSpacing.gapH(BioSpacing.md),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(
                'Historial (${sv.totalSv} registros)',
                style: BioTypography.title,
              ),
              children: [
                for (final row in sv.datosSv.take(12))
                  Padding(
                    padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                    child: Text(
                      _formatFilaSignosResumen(row),
                      style: BioTypography.caption,
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  String _formatFilaSignosResumen(Map<String, dynamic> row) {
    final fechaRaw = row['fecha_atencion'] ?? row['fecha'];
    final partes = <String>[];
    if (fechaRaw != null && '$fechaRaw'.trim().isNotEmpty) {
      partes.add('$fechaRaw');
    }
    final ta = row['ta1_sistolica'] != null && row['ta1_diastolica'] != null
        ? '${row['ta1_sistolica']}/${row['ta1_diastolica']}'
        : row['ta']?.toString();
    if (ta != null && ta.trim().isNotEmpty) partes.add('PA $ta');
    if (row['peso'] != null) partes.add('Peso ${row['peso']}');
    if (row['talla'] != null) partes.add('Talla ${row['talla']}');
    return partes.isEmpty ? '—' : partes.join(' · ');
  }

  Widget _buildInformacionMedica(InformacionMedica info) {
    return BioCard.intent(
      intent: UiIntent.warning,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Condición actual', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.lg),
          _buildSeccionInfo(
            'Diagnósticos recientes',
            info.condicionesActivas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          _buildSeccionInfo(
            'Condiciones activas',
            info.condicionesActivas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          _buildSeccionInfo(
            'Condiciones crónicas',
            info.condicionesCronicas
                .map((c) => c.termino ?? 'Sin término')
                .toList(),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          _buildSeccionInfo(
            'Hallazgos',
            info.hallazgos.map((h) => h.termino ?? 'Sin término').toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildSeccionInfo(String titulo, List<String> items) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(titulo, style: BioTypography.overline),
        BioSpacing.gapH(BioSpacing.sm),
        if (items.isEmpty)
          Text('Sin datos', style: BioTypography.bodySm)
        else
          Wrap(
            spacing: BioSpacing.sm,
            runSpacing: BioSpacing.sm,
            children: items
                .map((item) => BioBadge.info(item.toUpperCase()))
                .toList(),
          ),
      ],
    );
  }

  Widget _buildMotivosIntake(MotivosIntakeStaff intake) {
    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Preguntas previas al chat', style: BioTypography.title),
          if (intake.title != null && intake.title!.trim().isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              intake.title!.trim(),
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (intake.notesForStaff != null &&
              intake.notesForStaff!.trim().isNotEmpty) ...[
            Text('Orientación', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Text(intake.notesForStaff!.trim(), style: BioTypography.bodySm),
            BioSpacing.gapH(BioSpacing.md),
          ],
          if (intake.answers.isEmpty)
            Text(
              intake.status == 'submitted'
                  ? 'Respuestas registradas sin detalle disponible.'
                  : 'El paciente aún no completó las preguntas previas.',
              style: BioTypography.bodySm,
            )
          else ...[
            Text('Respuestas del paciente', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.sm),
            ...intake.answers.map(
              (a) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(a.question, style: BioTypography.bodySm),
                    BioSpacing.gapH(BioSpacing.xs),
                    Text(a.answer, style: BioTypography.body),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildMotivosConsulta(HistoriaClinicaResponse hc) {
    final mp = hc.motivosConsultaPaciente;
    final resumen = (mp.resumen ?? mp.resumenIa ?? hc.informacionMedica.motivosConsulta ?? '')
        .trim();
    final hayResumen = resumen.isNotEmpty;
    final sugerencias = mp.sugerenciasClinicas;
    final turnoCtx = mp.turno;
    final subtituloTurno = turnoCtx != null && turnoCtx.etiquetaCorta.isNotEmpty
        ? 'Turno ${turnoCtx.etiquetaCorta}'
        : (mp.turnoId != null ? 'Turno #${mp.turnoId}' : null);

    return BioCard.intent(
      intent: UiIntent.success,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Motivos de esta consulta', style: BioTypography.title),
          if (subtituloTurno != null) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              subtituloTurno,
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (hayResumen) ...[
            Text('Resumen', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: _buildResumenConImagenes(resumen, mp.imagenesAdjuntas),
            ),
          ] else if (mp.resumenPendiente)
            Text(
              'Generando resumen… Actualizá en unos segundos.',
              style: BioTypography.bodySm,
            )
          else
            Text(
              'Sin motivos registrados para esta consulta.',
              style: BioTypography.bodySm,
            ),
          if (sugerencias != null && sugerencias.tieneContenido) ...[
            BioSpacing.gapH(BioSpacing.lg),
            Text('Orientación preliminar', style: BioTypography.overline),
            if (sugerencias.diagnosticos.isNotEmpty) ...[
              BioSpacing.gapH(BioSpacing.sm),
              Text('Diagnósticos a considerar', style: BioTypography.bodySm),
              BioSpacing.gapH(BioSpacing.xs),
              ...sugerencias.diagnosticos.map(
                (d) => Padding(
                  padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                  child: Text(
                    '• ${d.termino}${d.justificacion != null && d.justificacion!.isNotEmpty ? ' — ${d.justificacion}' : ''}',
                    style: BioTypography.bodySm,
                  ),
                ),
              ),
            ],
            if (sugerencias.practicas.isNotEmpty) ...[
              BioSpacing.gapH(BioSpacing.sm),
              Text('Prácticas / estudios', style: BioTypography.bodySm),
              BioSpacing.gapH(BioSpacing.xs),
              ...sugerencias.practicas.map(
                (p) => Padding(
                  padding: const EdgeInsets.only(bottom: BioSpacing.xs),
                  child: Text(
                    '• ${p.termino}${p.justificacion != null && p.justificacion!.isNotEmpty ? ' — ${p.justificacion}' : ''}',
                    style: BioTypography.bodySm,
                  ),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _buildCarePackCohorte(CarePackCohorteStaff cohorte) {
    final assistance = cohorte.assistance;
    final profile = cohorte.cohortProfile;
    final profileParts = <String>[];
    if (profile != null) {
      for (final key in ['life_stage', 'sexo', 'motive_cluster', 'jurisdiction']) {
        final val = profile[key]?.toString().trim();
        if (val != null && val.isNotEmpty) {
          profileParts.add(val);
        }
      }
    }

    return BioCard.intent(
      intent: UiIntent.info,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Asistencia pre-consulta (cohorte)', style: BioTypography.title),
          if (cohorte.cohortKeyShort != null &&
              cohorte.cohortKeyShort!.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              'Cohorte ${cohorte.cohortKeyShort}',
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ],
          if (profileParts.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              profileParts.join(' · '),
              style: BioTypography.bodySm,
            ),
          ],
          BioSpacing.gapH(BioSpacing.md),
          if (assistance.notesForStaff != null &&
              assistance.notesForStaff!.trim().isNotEmpty) ...[
            Text('Orientación', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.xs),
            Text(assistance.notesForStaff!.trim(), style: BioTypography.bodySm),
            BioSpacing.gapH(BioSpacing.md),
          ],
          if (assistance.answers.isEmpty)
            Text(
              assistance.status == 'submitted'
                  ? 'Respuestas registradas sin detalle disponible.'
                  : 'El paciente aún no completó el cuestionario.',
              style: BioTypography.bodySm,
            )
          else ...[
            Text('Respuestas del paciente', style: BioTypography.overline),
            BioSpacing.gapH(BioSpacing.sm),
            ...assistance.answers.map(
              (a) => Padding(
                padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(a.question, style: BioTypography.bodySm),
                    BioSpacing.gapH(BioSpacing.xs),
                    Text(a.answer, style: BioTypography.body),
                  ],
                ),
              ),
            ),
          ],
          if (assistance.deltaRequested) ...[
            BioSpacing.gapH(BioSpacing.sm),
            BioBadge.warning('Requiere adaptación del pack'),
          ],
        ],
      ),
    );
  }

  List<Widget> _buildResumenConImagenes(
    String resumen,
    List<MotivoImagenAdjunta> imagenes,
  ) {
    final byRef = {for (final i in imagenes) i.ref: i.url};
    final pattern = RegExp(r'\[(imagen\d+)\]');
    final widgets = <Widget>[];
    var last = 0;

    for (final match in pattern.allMatches(resumen)) {
      if (match.start > last) {
        widgets.add(Text(
          resumen.substring(last, match.start),
          style: BioTypography.body,
        ));
      }
      final ref = match.group(1)!;
      final url = byRef[ref];
      if (url != null && url.isNotEmpty) {
        widgets.add(Padding(
          padding: const EdgeInsets.symmetric(vertical: BioSpacing.xs),
          child: ChatMediaImage(
            source: url,
            bearerToken: widget.authToken,
            width: double.infinity,
            height: 160,
            fit: BoxFit.contain,
          ),
        ));
      } else {
        widgets.add(Text('[$ref]', style: BioTypography.body));
      }
      last = match.end;
    }
    if (last < resumen.length) {
      widgets.add(Text(resumen.substring(last), style: BioTypography.body));
    }
    if (widgets.isEmpty) {
      widgets.add(Text(resumen, style: BioTypography.body));
    }

    return widgets;
  }

  bool get _tieneContextoCaptura =>
      widget.consultParent != null &&
      widget.consultParent!.isNotEmpty &&
      widget.consultParentId != null;

  Future<void> _toggleDictation() async {
    if (_guardandoConsulta) return;
    if (_dictating || _audioOnlyRecording) {
      if (_audioOnlyRecording) {
        await _stopAudioOnlyRecording();
      } else {
        final result = await _dictation.stop();
        await _stopBackupRecording();
        if (!mounted) return;
        setState(() {
          _dictating = false;
          _lastDictation = result;
          if (result.text.trim().isNotEmpty) {
            _chatController.text = result.text.trim();
          }
          final ok = DeviceSttLocalQuality.isAcceptable(
            _chatController.text,
            result,
          );
          _sttStatus = ok
              ? 'Dictado listo. Revise y analice.'
              : 'Calidad baja: use «Servidor» o corrija el texto.';
        });
      }
      return;
    }
    if (!_sttConfig.deviceEnabled && _sttConfig.serverEnabled) {
      await _startAudioOnlyRecording();
      return;
    }
    if (!_sttConfig.deviceEnabled) {
      _snack('Dictado en dispositivo deshabilitado.', UiIntent.warning);
      return;
    }
    try {
      await _startBackupRecording();
      await _dictation.start(onPartial: (text, _) {
        if (!mounted) return;
        setState(() {
          _chatController.text = text;
          _sttStatus = 'Escuchando…';
        });
      });
      if (!mounted) return;
      setState(() {
        _dictating = true;
        _sttStatus = 'Escuchando… pulse micrófono para detener.';
      });
    } catch (e) {
      _snack('No se pudo iniciar dictado: $e', UiIntent.danger);
    }
  }

  Future<void> _startAudioOnlyRecording() async {
    try {
      if (!await _audioRecorder.hasPermission()) {
        _snack('Permiso de micrófono denegado.', UiIntent.warning);
        return;
      }
      final dir = await getTemporaryDirectory();
      _pendingAudioPath =
          '${dir.path}/encounter_${DateTime.now().millisecondsSinceEpoch}.m4a';
      await _audioRecorder.start(
        const RecordConfig(encoder: AudioEncoder.aacLc, sampleRate: 44100),
        path: _pendingAudioPath!,
      );
      if (!mounted) return;
      setState(() {
        _audioOnlyRecording = true;
        _sttStatus = 'Grabando… pulse micrófono para detener.';
      });
    } catch (e) {
      _snack('No se pudo grabar audio: $e', UiIntent.danger);
    }
  }

  Future<void> _stopAudioOnlyRecording() async {
    if (await _audioRecorder.isRecording()) {
      await _audioRecorder.stop();
    }
    if (!mounted) return;
    setState(() {
      _audioOnlyRecording = false;
      _sttStatus = _pendingAudioPath != null
          ? 'Audio grabado. Pulse «Servidor» para transcribir.'
          : 'No se capturó audio.';
    });
  }

  Future<void> _startBackupRecording() async {
    if (!await _audioRecorder.hasPermission()) return;
    final dir = await getTemporaryDirectory();
    _pendingAudioPath =
        '${dir.path}/encounter_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _audioRecorder.start(
      const RecordConfig(encoder: AudioEncoder.aacLc, sampleRate: 44100),
      path: _pendingAudioPath!,
    );
  }

  Future<void> _stopBackupRecording() async {
    if (await _audioRecorder.isRecording()) {
      await _audioRecorder.stop();
    }
  }

  Future<String?> _audioPathToBase64(String path) async {
    try {
      final bytes = await XFile(path).readAsBytes();
      return 'data:audio/m4a;base64,${base64Encode(bytes)}';
    } catch (_) {
      return null;
    }
  }

  Future<void> _transcribirEnServidor() async {
    if (!_sttConfig.serverEnabled) {
      _snack('Transcripción en servidor deshabilitada.', UiIntent.warning);
      return;
    }
    if (_pendingAudioPath == null) {
      _snack('Grabe con el micrófono antes de usar servidor.', UiIntent.warning);
      return;
    }
    setState(() => _guardandoConsulta = true);
    try {
      final b64 = await _audioPathToBase64(_pendingAudioPath!);
      if (b64 == null) throw Exception('No se pudo leer el audio');
      final text = await _encounterApi.transcribirServidor(audioBase64: b64);
      if (!mounted) return;
      setState(() {
        _chatController.text = text;
        _lastDictation = DeviceDictationResult(
          text: text,
          confidence: 0.9,
          durationMs: 0,
          engine: 'server',
          locale: 'es_AR',
        );
        _sttStatus = 'Transcripción de servidor aplicada.';
      });
    } catch (e) {
      _snack('Error STT servidor: $e', UiIntent.danger);
    } finally {
      if (mounted) setState(() => _guardandoConsulta = false);
    }
  }

  Future<void> _analizarConsulta() async {
    final text = _chatController.text.trim();
    if (text.isEmpty) {
      _snack('Escriba o dicte la consulta.', UiIntent.warning);
      return;
    }
    if (!_tieneContextoCaptura) {
      _snack('Falta contexto de atención (parent).', UiIntent.warning);
      return;
    }
    setState(() {
      _guardandoConsulta = true;
      _sttStatus = 'Analizando…';
    });
    try {
      Map<String, dynamic>? stt;
      String? audioB64;
      if (_lastDictation != null) {
        stt = _lastDictation!.toSttPayload();
        if (!DeviceSttLocalQuality.isAcceptable(text, _lastDictation!) &&
            _pendingAudioPath != null) {
          audioB64 = await _audioPathToBase64(_pendingAudioPath!);
        }
      }
      final res = await _encounterApi.analizar(
        consulta: text,
        idPersona: widget.personaId,
        parent: widget.consultParent,
        parentId: widget.consultParentId,
        stt: stt,
        audioBase64: audioB64,
      );
      if (!mounted) return;
      setState(() {
        _lastAnalysis = res;
        _sttStatus =
            'Análisis listo (${res['stt_provenance'] ?? 'texto'}). Confirme para guardar.';
      });
    } catch (e) {
      _snack('Error al analizar: $e', UiIntent.danger);
      if (mounted) setState(() => _sttStatus = '');
    } finally {
      if (mounted) setState(() => _guardandoConsulta = false);
    }
  }

  Future<void> _confirmarGuardado() async {
    if (_lastAnalysis == null) {
      await _analizarConsulta();
      return;
    }
    final text = _chatController.text.trim();
    final datos = _lastAnalysis!['datos'];
    Map<String, dynamic> extraidos = {};
    if (datos is Map<String, dynamic>) {
      final inner = datos['datosExtraidos'];
      if (inner is Map<String, dynamic>) {
        extraidos = inner;
      } else {
        extraidos = datos;
      }
    }
    setState(() => _guardandoConsulta = true);
    try {
      await _encounterApi.guardar(
        idPersona: widget.personaId,
        parent: widget.consultParent,
        parentId: widget.consultParentId,
        datosExtraidos: extraidos,
        textoOriginal: (_lastAnalysis!['texto_original'] ?? text).toString(),
        textoProcesado: (_lastAnalysis!['texto_procesado'] ?? text).toString(),
      );
      if (!mounted) return;
      _snack('Consulta guardada', UiIntent.success);
      setState(() {
        _chatController.clear();
        _lastAnalysis = null;
        _lastDictation = null;
        _sttStatus = '';
      });
      _chatFocusNode.unfocus();
    } catch (e) {
      _snack('Error al guardar: $e', UiIntent.danger);
    } finally {
      if (mounted) setState(() => _guardandoConsulta = false);
    }
  }

  Future<void> _enviarConsulta() async {
    if (_tieneContextoCaptura) {
      if (_lastAnalysis != null) {
        await _confirmarGuardado();
      } else {
        await _analizarConsulta();
      }
      return;
    }
    if (!widget.soloVer) {
      _snack(
        'Defina contexto de consulta (parent) para captura con IA.',
        UiIntent.warning,
      );
    }
  }

  void _snack(String msg, UiIntent intent) {
    final palette = IntentPalette.of(intent);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: palette.base),
    );
  }

  Widget _buildChatInputBar(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final canCapture = _tieneContextoCaptura;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (_sttStatus.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(
              left: BioSpacing.sm,
              right: BioSpacing.sm,
              bottom: BioSpacing.xs,
            ),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                _sttStatus,
                style: BioTypography.caption.copyWith(color: cs.primary),
              ),
            ),
          ),
        AssistantChatComposerBar(
          controller: _chatController,
          focusNode: _chatFocusNode,
          onSend: _enviarConsulta,
          isSending: _guardandoConsulta,
          hintText: canCapture
              ? (_lastAnalysis != null
                  ? 'Confirmar consulta…'
                  : 'Dictar o escribir y analizar…')
              : 'Escribir consulta…',
          maxLines: 6,
          leading: canCapture
              ? [
                  if (_sttConfig.deviceEnabled || _sttConfig.serverEnabled)
                    IconButton(
                      icon: Icon(
                        (_dictating || _audioOnlyRecording)
                            ? Icons.stop_circle
                            : Icons.mic_none,
                      ),
                      color: (_dictating || _audioOnlyRecording)
                          ? IntentPalette.of(UiIntent.danger).base
                          : cs.onSurfaceVariant,
                      onPressed: _guardandoConsulta ? null : _toggleDictation,
                      tooltip: _sttConfig.deviceEnabled
                          ? 'Dictar'
                          : 'Grabar audio',
                    ),
                  if (_sttConfig.serverEnabled)
                    IconButton(
                      icon: const Icon(Icons.cloud_upload_outlined),
                      color: cs.onSurfaceVariant,
                      onPressed:
                          _guardandoConsulta ? null : _transcribirEnServidor,
                      tooltip: 'Transcribir en servidor',
                    ),
                ]
              : [
                  IconButton(
                    icon: const Icon(Icons.mic_none),
                    color: cs.onSurfaceVariant,
                    onPressed: _guardandoConsulta
                        ? null
                        : () => _snack(
                              'Defina contexto de atención para captura con IA.',
                              UiIntent.info,
                            ),
                  ),
                ],
        ),
      ],
    );
  }
}
