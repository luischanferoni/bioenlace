import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';

import '../models/pending_motivo_message.dart';
import '../services/motivos_consulta_service.dart';
import '../services/pending_motivo_message_store.dart';

/// Chat para cargar motivos de consulta: texto y audio.
/// Texto/audio se guardan en el teléfono antes de subir (reintento / eliminar).
class ChatMotivosScreen extends StatefulWidget {
  final int consultaId;
  final String? authToken;
  final String userId;
  final String userName;
  final String titulo;

  const ChatMotivosScreen({
    Key? key,
    required this.consultaId,
    required this.authToken,
    required this.userId,
    required this.userName,
    this.titulo = 'Motivos de la consulta',
  }) : super(key: key);

  @override
  State<ChatMotivosScreen> createState() => _ChatMotivosScreenState();
}

class _ChatMotivosScreenState extends State<ChatMotivosScreen> {
  late MotivosConsultaService _service;
  final TextEditingController _textController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final PendingMotivoMessageStore _pendingStore =
      PendingMotivoMessageStore.instance;
  List<dynamic> _messages = [];
  List<PendingMotivoMessage> _pending = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;
  final AudioRecorder _recorder = AudioRecorder();
  bool _isRecording = false;

  static const String _fallbackWelcomeMessage =
      'Contanos en pocas palabras por qué pediste este turno. Podés escribir o enviar audios hasta poco antes del horario del turno; después armamos un resumen para el médico.';
  bool _inputAbierto = true;
  String? _motivosResumen;
  String? _chatGuideMessage;
  String? _chatGuideTitle;

  @override
  void initState() {
    super.initState();
    _service = MotivosConsultaService(
      userId: widget.userId,
      authToken: widget.authToken,
      userName: widget.userName,
    );
    _loadMessages();
    _loadPending();
  }

  @override
  void dispose() {
    _textController.dispose();
    _scrollController.dispose();
    unawaited(_recorder.dispose());
    super.dispose();
  }

  Future<void> _loadPending() async {
    final list = await _pendingStore.listForConsulta(widget.consultaId);
    if (!mounted) return;
    setState(() => _pending = list);
  }

  Future<void> _loadMessages() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _service.getMessages(widget.consultaId);
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

    final now = DateTime.now();
    final pending = PendingMotivoMessage(
      id: _pendingStore.newId(),
      consultaId: widget.consultaId,
      type: PendingMotivoMessageType.texto,
      status: PendingMotivoMessageStatus.pendingUpload,
      createdAt: now,
      updatedAt: now,
      texto: text,
    );
    await _pendingStore.upsert(pending);
    await _loadPending();
    await _uploadPending(pending);
  }

  Future<void> _recordAndSendAudio() async {
    if (_sending) return;
    if (!_inputAbierto && !_isRecording) {
      _showError('El plazo para cargar motivos ya finalizó.');
      return;
    }
    if (_isRecording) {
      final path = await _recorder.stop();
      setState(() => _isRecording = false);
      if (path != null && path.isNotEmpty) {
        await _persistAndUploadAudio(path);
      }
      return;
    }
    if (!await _recorder.hasPermission()) {
      if (mounted) {
        _showError('Se necesita permiso de micrófono');
      }
      return;
    }
    final dir = await getTemporaryDirectory();
    final path =
        '${dir.path}/audio_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(
      const RecordConfig(encoder: AudioEncoder.aacLc, sampleRate: 44100),
      path: path,
    );
    setState(() => _isRecording = true);
  }

  Future<void> _persistAndUploadAudio(String tempPath) async {
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
      consultaId: widget.consultaId,
      type: PendingMotivoMessageType.audio,
      status: PendingMotivoMessageStatus.pendingUpload,
      createdAt: now,
      updatedAt: now,
      audioFileName: imported,
    );
    await _pendingStore.upsert(pending);
    await _loadPending();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Audio guardado en el teléfono. Subiendo…'),
          backgroundColor: IntentPalette.of(UiIntent.info).base,
        ),
      );
    }
    await _uploadPending(pending);
  }

  Future<void> _uploadPending(PendingMotivoMessage item) async {
    if (_sending) return;
    if (!_inputAbierto) {
      _showError('El plazo para cargar motivos ya finalizó.');
      return;
    }
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
        result = await _service.sendMessage(widget.consultaId, text);
      } else {
        final path = await _pendingStore.absoluteAudioPath(item);
        if (path == null) {
          await _markFailed(item, 'No se encontró el audio en el teléfono');
          return;
        }
        result = await _service.uploadFile(
          widget.consultaId,
          XFile(path),
          messageType: 'audio',
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
      _showError(
        'Quedó guardado en el teléfono. Podés reintentar.\n$error',
      );
    }
  }

  Future<void> _deletePending(String id) async {
    await _pendingStore.delete(id);
    await _loadPending();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Mensaje pendiente eliminado'),
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
            child: _loading
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
        border: Border(
          top: BorderSide(color: context.bio.paperBorderDefault),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Pendientes en el teléfono (${_pending.length})',
            style: BioTypography.overline,
          ),
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            'Se guardaron localmente por si falla la conexión.',
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
    final label = item.type == PendingMotivoMessageType.audio
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
              Expanded(
                child: Text(label, style: BioTypography.bodySm),
              ),
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
                MotivosConsultaGuideBubble(
                  message: guideMessage,
                  title: guideTitle,
                ),
                if (!_inputAbierto) ...[
                  BioSpacing.gapH(BioSpacing.sm),
                  BioAlert.warning(
                    message:
                        'Ya no podés enviar más mensajes. El médico verá un resumen al iniciar la consulta.',
                    icon: Icons.lock_clock_outlined,
                  ),
                ],
                if (_motivosResumen != null &&
                    _motivosResumen!.trim().isNotEmpty) ...[
                  BioSpacing.gapH(BioSpacing.sm),
                  BioAlert.success(
                    message:
                        'Resumen para el médico:\n${_motivosResumen!.trim()}',
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
      child = Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.mic, color: softColor, size: 18),
          BioSpacing.gapW(BioSpacing.sm),
          Text(
            'Audio',
            style: BioTypography.bodySm.copyWith(color: softColor),
          ),
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
    return AssistantChatComposerBar(
      controller: _textController,
      onSend: _sendText,
      isSending: _sending,
      hintText: 'Escribí el motivo o enviá un audio…',
      maxLines: 2,
      onVoice: _recordAndSendAudio,
      voiceActive: _isRecording,
    );
  }
}
