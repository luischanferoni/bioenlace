import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';

import '../services/motivos_consulta_service.dart';

/// Chat para cargar motivos de consulta: texto, fotos y audio.
/// El médico verá luego un resumen estructurado (proceso aparte en backend).
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
  List<dynamic> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;
  final AudioRecorder _recorder = AudioRecorder();
  bool _isRecording = false;

  static const String _fallbackWelcomeMessage =
      'Contanos en pocas palabras por qué pediste este turno. Podés escribir, enviar audios o fotos hasta poco antes del horario del turno; después armamos un resumen para el médico.';
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
  }

  @override
  void dispose() {
    _textController.dispose();
    _scrollController.dispose();
    super.dispose();
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
    setState(() => _sending = true);

    final result = await _service.sendMessage(widget.consultaId, text);
    if (!mounted) return;
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      setState(() => _messages = [..._messages, result['data']]);
      _scrollToBottom();
    } else {
      _showError(result['message']?.toString() ?? 'Error');
    }
  }

  Future<void> _pickImage() async {
    if (!_inputAbierto) {
      _showError('El plazo para cargar motivos ya finalizó.');
      return;
    }
    final picker = ImagePicker();
    final XFile? file = await picker.pickImage(source: ImageSource.gallery);
    if (file == null || !mounted) return;
    await _uploadFile(file, 'imagen');
  }

  Future<void> _recordAndSendAudio() async {
    if (!_inputAbierto && !_isRecording) {
      _showError('El plazo para cargar motivos ya finalizó.');
      return;
    }
    if (_isRecording) {
      final path = await _recorder.stop();
      setState(() => _isRecording = false);
      if (path != null && path.isNotEmpty) {
        await _uploadFile(XFile(path), 'audio');
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

  Future<void> _uploadFile(XFile file, String messageType) async {
    if (_sending) return;
    if (!kIsWeb) {
      try {
        if (await file.length() <= 0) {
          _showError('No se pudo leer el archivo');
          return;
        }
      } catch (_) {
        _showError('No se pudo leer el archivo');
        return;
      }
    }
    final showLocalPreview = messageType == 'imagen';
    if (showLocalPreview) {
      setState(() {
        _messages = [
          ..._messages,
          <String, dynamic>{
            'message_type': 'imagen',
            'content': file.path,
            '_local_preview': true,
            'user_id': widget.userId,
            'user_name': widget.userName,
            'created_at': DateTime.now().toIso8601String(),
          },
        ];
      });
      _scrollToBottom();
    }
    setState(() => _sending = true);
    final result = await _service.uploadFile(
      widget.consultaId,
      file,
      messageType: messageType,
    );
    if (!mounted) return;
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      setState(() {
        final withoutPreview = showLocalPreview
            ? _messages
                .where((m) => (m as Map)['_local_preview'] != true)
                .toList()
            : _messages;
        _messages = [...withoutPreview, result['data']];
      });
      _scrollToBottom();
    } else {
      if (showLocalPreview) {
        setState(() {
          _messages = _messages
              .where((m) => (m as Map)['_local_preview'] != true)
              .toList();
        });
      }
      _showError(result['message']?.toString() ?? 'Error');
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
          _buildInputBar(context),
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
                    message: 'Resumen para el médico:\n${_motivosResumen!.trim()}',
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
    final cs = Theme.of(context).colorScheme;
    return AssistantChatComposerBar(
      controller: _textController,
      onSend: _sendText,
      isSending: _sending,
      hintText: 'Escribí el motivo o enviá audio/foto…',
      leading: [
        IconButton(
          icon: const Icon(Icons.image_outlined),
          color: cs.onSurfaceVariant,
          onPressed: _sending ? null : _pickImage,
        ),
        IconButton(
          icon: Icon(_isRecording ? Icons.stop_circle : Icons.mic_none),
          color: _isRecording ? cs.error : cs.onSurfaceVariant,
          onPressed: _sending ? null : _recordAndSendAudio,
        ),
      ],
    );
  }
}
