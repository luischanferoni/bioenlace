import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';

import '../utils/app_texts.dart';

import '../models/pending_motivo_message.dart';
import '../services/encounter_reason_chat_service.dart';
import '../services/pending_motivo_message_store.dart';

/// Chat for encounter reasons: text and audio.
/// Conserva y sube el audio junto con cualquier transcripción local disponible.
class EncounterReasonsChatScreen extends StatefulWidget {
  final int encounterId;
  final String? authToken;
  final String userId;
  final String userName;
  final String titulo;

  const EncounterReasonsChatScreen({
    Key? key,
    required this.encounterId,
    required this.authToken,
    required this.userId,
    required this.userName,
    this.titulo = 'Motivos de la consulta',
  }) : super(key: key);

  @override
  State<EncounterReasonsChatScreen> createState() =>
      _EncounterReasonsChatScreenState();
}

class _EncounterReasonsChatScreenState
    extends State<EncounterReasonsChatScreen> {
  late EncounterReasonChatService _service;
  final TextEditingController _textController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final PendingMotivoMessageStore _pendingStore =
      PendingMotivoMessageStore.instance;
  final DeviceSpeechDictation _dictation = DeviceSpeechDictation();
  List<dynamic> _messages = [];
  List<PendingMotivoMessage> _pending = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;
  final AudioRecorder _recorder = AudioRecorder();
  bool _isRecording = false;
  bool _dictating = false;
  SttClientConfig _sttConfig = SttClientConfig.defaults;
  String? _voiceHint;

  static const String _fallbackWelcomeMessage =
      'Contanos en pocas palabras por qué pediste este turno. Podés escribir o enviar audios hasta poco antes del horario del turno; después armamos un resumen para el médico.';
  bool _inputAbierto = true;
  String? _motivosResumen;
  String? _chatGuideMessage;
  String? _chatGuideTitle;

  @override
  void initState() {
    super.initState();
    _service = EncounterReasonChatService(
      userId: widget.userId,
      authToken: widget.authToken,
      userName: widget.userName,
    );
    _loadMessages();
    _loadPending();
    unawaited(_loadSttConfig());
  }

  @override
  void dispose() {
    _textController.dispose();
    _scrollController.dispose();
    unawaited(_dictation.cancel());
    unawaited(_recorder.dispose());
    super.dispose();
  }

  Future<void> _loadSttConfig() async {
    late final SttClientConfig cfg;
    try {
      cfg = await _service.fetchSttConfig();
    } catch (e, st) {
      debugPrint('[stt-config] motivos load failed: $e\n$st');
      if (!mounted) return;
      setState(() {
        _sttConfig = SttClientConfig.unavailable;
        _voiceHint =
            'No se pudo cargar la configuración de voz. El audio se conservará para su envío.';
      });
      return;
    }

    if (!mounted) return;
    setState(() => _sttConfig = cfg);
    debugPrint(
      '[stt-config] motivos loaded device=${cfg.deviceEnabled} '
      'source=${cfg.deviceEnabledSource} server=${cfg.serverEnabled} '
      'serverConfigured=${cfg.serverConfigured} '
      'provider=${cfg.proveedorServidor}',
    );
    if (cfg.deviceEnabled) {
      try {
        final available = await _dictation.initialize();
        if (!available) {
          debugPrint(
            '[stt-device] local recognizer unavailable; audio fallback remains enabled',
          );
          if (mounted) {
            setState(() {
              _voiceHint =
                  'Dictado local no disponible; se conservará el audio para enviarlo.';
            });
          }
        }
      } catch (e, st) {
        debugPrint(
          '[stt-device] initialize failed; audio fallback remains enabled: $e\n$st',
        );
      }
    }
  }

  Future<void> _loadPending() async {
    final list = await _pendingStore.listForEncounter(widget.encounterId);
    if (!mounted) return;
    setState(() => _pending = list);
  }

  Future<void> _loadMessages() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _service.getMessages(widget.encounterId);
    if (!mounted) return;
    setState(() {
      _loading = false;
      _messages = result['messages'] ?? [];
      _inputAbierto = result['input_abierto'] == true;
      _motivosResumen = result['motivos_resumen']?.toString();
      final guide = result['chat_guide'];
      if (guide is Map) {
        final message = guide['message']?.toString().trim();
        if (message != null && message.isNotEmpty) {
          _chatGuideMessage = message;
          _chatGuideTitle = guide['title']?.toString();
        }
      }
      if (result['success'] != true) _error = result['message'] as String?;
    });
    _scrollToBottom();
  }

  String _formatCreatedAt(dynamic v) {
    final s = v?.toString() ?? '';
    return s.length >= 16 ? s.substring(0, 16) : s;
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: BioMotion.normal,
          curve: BioMotion.standard,
        );
      }
    });
  }

  Future<void> _sendText() async {
    if (!_inputAbierto) {
      _showError('El plazo para cargar motivos ya finalizó.');
      return;
    }
    final text = _textController.text.trim();
    if (text.isEmpty || _sending) return;
    _textController.clear();
    await _persistAndUploadTexto(text);
  }

  Future<void> _recordAndSendAudio() async {
    if (_sending) return;
    if (!_inputAbierto && !_isRecording) {
      _showError('El plazo para cargar motivos ya finalizó.');
      return;
    }
    if (_isRecording) {
      await _finishVoiceCapture();
      return;
    }
    await _startVoiceCapture();
  }

  Future<void> _startVoiceCapture() async {
    if (!await _recorder.hasPermission()) {
      if (mounted) {
        _showError('Se necesita permiso de micrófono');
      }
      return;
    }

    final dir = await getTemporaryDirectory();
    final path =
        '${dir.path}/audio_${DateTime.now().millisecondsSinceEpoch}.m4a';

    // Siempre grabamos audio de respaldo (fallback Groq en el lote si el STT local falla).
    await _recorder.start(
      const RecordConfig(encoder: AudioEncoder.aacLc, sampleRate: 44100),
      path: path,
    );

    var dictating = false;
    if (_sttConfig.deviceEnabled) {
      try {
        await _dictation.start(
          listenMode: DeviceListenMode.dictation,
          preferOnDevice: true,
          onPartial: (text, _) {
            if (!mounted) return;
            setState(() {
              _voiceHint = text.trim().isEmpty ? 'Escuchando…' : text.trim();
            });
          },
        );
        dictating = true;
      } catch (e, st) {
        debugPrint('[stt-motivos] local dictation start failed: $e\n$st');
        // Sin dictado local: queda solo la grabación de audio.
        dictating = false;
      }
    }

    if (!mounted) return;
    setState(() {
      _isRecording = true;
      _dictating = dictating;
      _voiceHint =
          dictating
              ? 'Escuchando… tocá el micrófono para enviar'
              : 'Grabando audio… tocá el micrófono para enviar';
    });
  }

  Future<void> _finishVoiceCapture() async {
    DeviceDictationResult? dictation;
    if (_dictating) {
      try {
        dictation = await _dictation.stop();
      } catch (e, st) {
        debugPrint('[stt-motivos] local dictation stop failed: $e\n$st');
        dictation = null;
      }
    }

    String? path;
    if (await _recorder.isRecording()) {
      path = await _recorder.stop();
    }

    if (!mounted) return;
    setState(() {
      _isRecording = false;
      _dictating = false;
      _voiceHint = null;
    });

    final text = dictation?.text.trim() ?? '';
    debugPrint(
      '[stt-motivos] deviceEnabled=${_sttConfig.deviceEnabled} '
      'chars=${text.length} '
      'confidence=${dictation?.confidence ?? 0} '
      'durationMs=${dictation?.durationMs ?? 0} '
      'onDevice=${dictation?.onDevice ?? false} '
      'final=${dictation?.finalResult ?? false} '
      'error=${dictation?.error ?? 'none'}',
    );

    if (path != null && path.isNotEmpty) {
      await _persistAndUploadAudio(
        path,
        transcript: text.isEmpty ? null : text,
        sttMeta: dictation?.toSttPayload(),
      );
      return;
    }

    if (text.isNotEmpty) {
      await _persistAndUploadTexto(text);
      return;
    }

    _showError('No se capturó voz. Probá de nuevo o escribí el motivo.');
  }

  Future<void> _persistAndUploadTexto(String text) async {
    final trimmed = text.trim();
    if (trimmed.isEmpty) return;

    final now = DateTime.now();
    final pending = PendingMotivoMessage(
      id: _pendingStore.newId(),
      encounterId: widget.encounterId,
      type: PendingMotivoMessageType.texto,
      status: PendingMotivoMessageStatus.pendingUpload,
      createdAt: now,
      updatedAt: now,
      texto: trimmed,
    );
    await _pendingStore.upsert(pending);
    await _loadPending();
    await _uploadPending(pending);
  }

  Future<void> _persistAndUploadAudio(
    String tempPath, {
    String? transcript,
    Map<String, dynamic>? sttMeta,
  }) async {
    if (!kIsWeb) {
      try {
        final file = XFile(tempPath);
        if (await file.length() <= 0) {
          _showError('No se pudo leer el archivo');
          return;
        }
      } catch (_) {
        _showError('No se pudo leer el archivo');
        return;
      }
    }

    final id = _pendingStore.newId();
    final imported = await _pendingStore.importAudioFile(
      messageId: id,
      sourcePath: tempPath,
    );
    if (imported == null) {
      _showError('No se pudo guardar el audio en el teléfono');
      return;
    }

    final now = DateTime.now();
    final pending = PendingMotivoMessage(
      id: id,
      encounterId: widget.encounterId,
      type: PendingMotivoMessageType.audio,
      status: PendingMotivoMessageStatus.pendingUpload,
      createdAt: now,
      updatedAt: now,
      audioFileName: imported,
      transcript: transcript,
      sttMeta: sttMeta,
    );
    await _pendingStore.upsert(pending);
    await _loadPending();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text(AppTexts.audioSavedUploading),
          backgroundColor: IntentPalette.of(UiIntent.info).base,
        ),
      );
    }
    await _uploadPending(pending);
  }

  Future<void> _uploadPending(PendingMotivoMessage item) async {
    if (_sending) return;
    setState(() => _sending = true);
    try {
      Map<String, dynamic> result;
      if (item.type == PendingMotivoMessageType.texto) {
        final text = item.texto?.trim() ?? '';
        if (text.isEmpty) {
          await _pendingStore.delete(item.id);
          await _loadPending();
          return;
        }
        result = await _service.sendMessage(widget.encounterId, text);
      } else {
        final path = await _pendingStore.absoluteAudioPath(item);
        if (path == null) {
          await _markFailed(item, 'No se encontró el audio en el teléfono');
          return;
        }
        result = await _service.uploadFile(
          widget.encounterId,
          XFile(path),
          messageType: 'audio',
          transcript: item.transcript,
          sttMeta: item.sttMeta,
        );
      }

      if (!mounted) return;
      if (result['success'] == true && result['data'] != null) {
        await _pendingStore.delete(item.id);
        setState(() {
          _messages = [..._messages, result['data']];
        });
        await _loadPending();
        _scrollToBottom();
      } else {
        await _markFailed(
          item,
          result['message']?.toString() ?? 'Error al subir',
        );
      }
    } catch (e) {
      await _markFailed(item, e.toString());
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _markFailed(PendingMotivoMessage item, String error) async {
    await _pendingStore.upsert(
      item.copyWith(
        status: PendingMotivoMessageStatus.failedUpload,
        updatedAt: DateTime.now(),
        lastError: error,
      ),
    );
    await _loadPending();
    if (mounted) {
      _showError('Quedó guardado en el teléfono. Podés reintentar.\n$error');
    }
  }

  Future<void> _deletePending(String id) async {
    await _pendingStore.delete(id);
    await _loadPending();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text(AppTexts.pendingMessageDeleted),
          backgroundColor: IntentPalette.of(UiIntent.neutral).base,
        ),
      );
    }
  }

  void _showError(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: IntentPalette.of(UiIntent.danger).base,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: BioAppBar(title: widget.titulo),
      body: Column(
        children: [
          Expanded(
            child:
                _loading
                    ? const Center(child: CircularProgressIndicator())
                    : _error != null
                    ? Center(
                      child: Padding(
                        padding: BioSpacing.pageAll,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            BioAlert.danger(message: _error!),
                            BioSpacing.gapH(BioSpacing.lg),
                            BioButton.primary(
                              label: 'Reintentar',
                              icon: Icons.refresh,
                              onPressed: _loadMessages,
                            ),
                          ],
                        ),
                      ),
                    )
                    : _buildLista(context),
          ),
          if (_pending.isNotEmpty) _buildPendingPanel(context),
          _buildInputBar(context),
        ],
      ),
    );
  }

  Widget _buildPendingPanel(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(
        BioSpacing.md,
        BioSpacing.sm,
        BioSpacing.md,
        BioSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: context.bio.paperSurface,
        border: Border(top: BorderSide(color: context.bio.paperBorderDefault)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            AppTexts.pendingOnPhone(_pending.length),
            style: BioTypography.overline,
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            AppTexts.pendingSavedLocally,
            style: BioTypography.caption.copyWith(color: context.bio.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          for (final item in _pending) ...[
            _buildPendingRow(item),
            if (item != _pending.last) BioSpacing.gapH(BioSpacing.xs),
          ],
        ],
      ),
    );
  }

  Widget _buildPendingRow(PendingMotivoMessage item) {
    final label =
        item.type == PendingMotivoMessageType.audio
            ? 'Audio pendiente'
            : (item.texto != null && item.texto!.length > 60
                ? '${item.texto!.substring(0, 60)}…'
                : (item.texto ?? 'Texto pendiente'));
    return Container(
      padding: const EdgeInsets.all(BioSpacing.sm),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(BioRadius.sm),
        border: Border.all(color: context.bio.paperBorderDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                item.type == PendingMotivoMessageType.audio
                    ? Icons.mic
                    : Icons.chat_bubble_outline,
                size: 16,
                color: context.bio.textMuted,
              ),
              BioSpacing.gapW(BioSpacing.xs),
              Expanded(child: Text(label, style: BioTypography.bodySm)),
            ],
          ),
          if (item.lastError != null && item.lastError!.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              item.lastError!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: BioTypography.caption.copyWith(
                color: IntentPalette.of(UiIntent.danger).base,
              ),
            ),
          ],
          BioSpacing.gapH(BioSpacing.xs),
          Row(
            children: [
              Expanded(
                child: BioButton(
                  label: 'Reintentar',
                  intent: UiIntent.primary,
                  variant: BioButtonVariant.soft,
                  onPressed: _sending ? null : () => _uploadPending(item),
                ),
              ),
              BioSpacing.gapW(BioSpacing.xs),
              Expanded(
                child: BioButton(
                  label: 'Eliminar',
                  intent: UiIntent.danger,
                  variant: BioButtonVariant.soft,
                  onPressed: _sending ? null : () => _deletePending(item.id),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildLista(BuildContext context) {
    final guideMessage = _chatGuideMessage ?? _fallbackWelcomeMessage;
    final guideTitle = _chatGuideTitle;

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(
        vertical: BioSpacing.sm,
        horizontal: BioSpacing.md,
      ),
      itemCount: _messages.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return Padding(
            padding: const EdgeInsets.fromLTRB(
              BioSpacing.xs,
              BioSpacing.sm,
              BioSpacing.xs,
              BioSpacing.lg,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                EncounterReasonsGuideBubble(
                  message: guideMessage,
                  title: guideTitle,
                ),
                if (!_inputAbierto) ...[
                  BioSpacing.gapH(BioSpacing.sm),
                  BioAlert.warning(
                    message: AppTexts.reasonsChatClosed,
                    icon: Icons.lock_clock_outlined,
                  ),
                ],
                if (_motivosResumen != null &&
                    _motivosResumen!.trim().isNotEmpty) ...[
                  BioSpacing.gapH(BioSpacing.sm),
                  BioAlert.success(
                    message: AppTexts.reasonsSummaryForDoctor(_motivosResumen!.trim()),
                    icon: Icons.summarize_outlined,
                  ),
                ],
              ],
            ),
          );
        }
        final m = _messages[index - 1] as Map<String, dynamic>;
        return _buildBubblePropia(context, m);
      },
    );
  }

  Widget _buildBubblePropia(BuildContext context, Map<String, dynamic> m) {
    final palette = IntentPalette.of(UiIntent.primary);
    final type = m['message_type'] as String? ?? 'texto';
    final content = m['content']?.toString() ?? '';
    final softColor = palette.onBase.withValues(alpha: 0.78);

    Widget child;
    if (type == 'texto') {
      child = Text(
        content,
        style: BioTypography.body.copyWith(color: palette.onBase),
      );
    } else if (isImageMessageType(type) && content.isNotEmpty) {
      child = ChatMediaImage(
        source: content,
        bearerToken: widget.authToken,
        width: 220,
        fit: BoxFit.cover,
        placeholderColor: softColor,
      );
    } else if (type == 'audio') {
      final transcript = m['transcript']?.toString().trim() ?? '';
      child = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.mic, color: softColor, size: 18),
              BioSpacing.gapW(BioSpacing.sm),
              Text(
                'Audio',
                style: BioTypography.bodySm.copyWith(color: softColor),
              ),
            ],
          ),
          if (transcript.isNotEmpty) ...[
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              transcript,
              style: BioTypography.bodySm.copyWith(color: palette.onBase),
            ),
          ],
        ],
      );
    } else {
      child = Text(
        content,
        style: BioTypography.bodySm.copyWith(color: softColor),
      );
    }

    return Align(
      alignment: Alignment.centerRight,
      child: Container(
        margin: const EdgeInsets.symmetric(
          vertical: BioSpacing.xs,
          horizontal: BioSpacing.sm,
        ),
        padding: const EdgeInsets.all(BioSpacing.md),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.8,
        ),
        decoration: BoxDecoration(
          color: palette.base,
          borderRadius: BorderRadius.circular(BioRadius.md),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            child,
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              _formatCreatedAt(m['created_at']),
              style: BioTypography.caption.copyWith(
                color: softColor,
                fontSize: 10,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInputBar(BuildContext context) {
    if (!_inputAbierto) {
      return const SizedBox.shrink();
    }
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_voiceHint != null && _voiceHint!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(
              BioSpacing.md,
              BioSpacing.xs,
              BioSpacing.md,
              0,
            ),
            child: Text(
              _voiceHint!,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: BioTypography.caption.copyWith(
                color: context.bio.textMuted,
              ),
            ),
          ),
        AssistantChatComposerBar(
          controller: _textController,
          onSend: _sendText,
          isSending: _sending,
          hintText:
              _sttConfig.deviceEnabled
                  ? 'Escribí el motivo o dictá con el micrófono…'
                  : 'Escribí el motivo o enviá un audio…',
          maxLines: 2,
          onVoice: _recordAndSendAudio,
          voiceActive: _isRecording,
        ),
      ],
    );
  }
}
