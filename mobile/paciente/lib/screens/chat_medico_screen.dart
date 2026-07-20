import 'package:cross_file/cross_file.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';

import '../services/consulta_chat_service.dart';
import '../services/consulta_async_api.dart';

/// Chat con el médico: texto e imágenes (paciente); audio/PDF según policy.
class ChatMedicoScreen extends StatefulWidget {
  final int consultaId;
  final String? authToken;
  final String userId;
  final String userName;
  final String titulo;

  const ChatMedicoScreen({
    Key? key,
    required this.consultaId,
    required this.authToken,
    required this.userId,
    required this.userName,
    this.titulo = 'Chat con médico',
  }) : super(key: key);

  @override
  State<ChatMedicoScreen> createState() => _ChatMedicoScreenState();
}

class _ChatMedicoScreenState extends State<ChatMedicoScreen> {
  late ConsultaChatService _chatService;
  final TextEditingController _textController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  List<dynamic> _messages = [];
  AsyncConsultaChatPolicy _chatPolicy = AsyncConsultaChatPolicy.fromApi(null);
  late ConsultaAsyncApi _asyncApi;
  bool _loading = true;
  bool _sending = false;
  String? _error;
  final AudioRecorder _recorder = AudioRecorder();
  bool _isRecording = false;

  @override
  void initState() {
    super.initState();
    _chatService = ConsultaChatService(
      userId: widget.userId,
      authToken: widget.authToken,
      userName: widget.userName,
    );
    _asyncApi = ConsultaAsyncApi(authToken: widget.authToken);
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
    final result = await _chatService.getMessages(widget.consultaId);
    if (!mounted) return;
    setState(() {
      _loading = false;
      _messages = result['messages'] ?? [];
      final data = result['data'];
      if (data is Map) {
        final policyRaw = data['chat_policy'];
        _chatPolicy = AsyncConsultaChatPolicy.fromApi(
          policyRaw is Map ? Map<String, dynamic>.from(policyRaw) : null,
        );
      }
      if (result['success'] != true) _error = result['message'] as String?;
    });
    _scrollToBottom();
  }

  Future<void> _cancelarSolicitud() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Retirar solicitud'),
        content: const Text(
          '¿Querés retirar esta solicitud? Solo podés hacerlo mientras el equipo aún no la atiende.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('No')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Retirar')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final res = await _asyncApi.cancelarComoPaciente(widget.consultaId);
    if (!mounted) return;
    if (res['success'] == true) {
      await _loadMessages();
    } else {
      _showError(res['message']?.toString() ?? 'No se pudo cancelar');
    }
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
    final text = _textController.text.trim();
    if (text.isEmpty || _sending) return;
    _textController.clear();
    setState(() => _sending = true);

    final result = await _chatService.sendMessage(widget.consultaId, text);
    if (!mounted) return;
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      await _loadMessages();
    } else {
      _showError(result['message']?.toString() ?? 'Error');
    }
  }

  Future<void> _pickImage() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
      maxWidth: 2048,
    );
    if (picked == null || !mounted) return;
    await _uploadFile(picked, 'imagen');
  }

  Future<void> _recordAndSendAudio() async {
    if (_isRecording) {
      final path = await _recorder.stop();
      setState(() => _isRecording = false);
      if (path != null && path.isNotEmpty) {
        await _uploadFile(XFile(path), 'audio');
      }
      return;
    }
    if (!await _recorder.hasPermission()) return;
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
    setState(() => _sending = true);
    final result = await _chatService.uploadFile(
      widget.consultaId,
      file,
      messageType: messageType,
    );
    if (!mounted) return;
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      await _loadMessages();
    } else {
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
      appBar: BioAppBar(
        title: widget.titulo,
        actions: [
          if (_chatPolicy.canCancel)
            IconButton(
              icon: const Icon(Icons.cancel_outlined),
              tooltip: 'Retirar solicitud',
              onPressed: _loading ? null : _cancelarSolicitud,
            ),
        ],
      ),
      body: Column(
        children: [
          if (_chatPolicy.hint.isNotEmpty)
            Padding(
              padding: BioSpacing.pageHorizontal.copyWith(top: BioSpacing.sm),
              child: BioAlert.info(message: _chatPolicy.hint),
            ),
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
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.symmetric(
                          vertical: BioSpacing.sm,
                          horizontal: BioSpacing.md,
                        ),
                        itemCount: _messages.length,
                        itemBuilder: (context, i) => _buildBubble(
                          context,
                          _messages[i] as Map<String, dynamic>,
                        ),
                      ),
          ),
          if (_chatPolicy.composerEnabled) _buildInputBar(context),
        ],
      ),
    );
  }

  Widget _buildBubble(BuildContext context, Map<String, dynamic> m) {
    if (asyncChatMessageIsSystem(m)) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: BioSpacing.xs),
        child: Center(
          child: Text(
            m['content']?.toString() ?? '',
            textAlign: TextAlign.center,
            style: asyncChatMessageTextStyle(context, m),
          ),
        ),
      );
    }

    final tokens = context.bio;
    final isMe =
        m['user_role'] == 'paciente' || m['user_id'].toString() == widget.userId;
    final type = m['message_type'] as String? ?? 'texto';
    final content = m['content']?.toString() ?? '';

    final palette = IntentPalette.of(UiIntent.primary);
    final bg = isMe ? palette.base : tokens.paperSurfaceSunken;
    final fg = isMe ? palette.onBase : tokens.textBody;
    final fgMuted = isMe
        ? palette.onBase.withValues(alpha: 0.72)
        : tokens.textMuted;

    Widget contenido;
    if (type == 'texto' || type.startsWith('solicitud_')) {
      contenido = Text(
        content,
        style: asyncChatMessageTextStyle(context, m).copyWith(color: fg),
      );
    } else if (type == 'audio' || type == 'documento' || type == 'imagen') {
      final IconData icon;
      if (type == 'audio') {
        icon = Icons.mic;
      } else if (type == 'imagen') {
        icon = Icons.image_outlined;
      } else {
        icon = Icons.picture_as_pdf_outlined;
      }
      contenido = Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: fgMuted, size: 18),
          BioSpacing.gapW(BioSpacing.sm),
          Text(
            asyncChatAttachmentLabel(type),
            style: BioTypography.bodySm.copyWith(color: fgMuted),
          ),
        ],
      );
    } else if (isImageMessageType(type) && content.isNotEmpty) {
      contenido = Text(
        asyncChatAttachmentLabel(type),
        style: BioTypography.bodySm.copyWith(color: fgMuted),
      );
    } else {
      contenido = Text(
        content.isNotEmpty ? content : asyncChatAttachmentLabel(type),
        style: BioTypography.bodySm.copyWith(color: fgMuted),
      );
    }

    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
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
          color: bg,
          borderRadius: BorderRadius.circular(BioRadius.md),
          border: isMe
              ? null
              : Border.all(
                  color: tokens.paperBorderDefault,
                  width: BorderWidth.thin,
                ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            contenido,
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              m['created_at']?.toString().substring(
                    0,
                    m['created_at']?.toString().length ?? 0,
                  ).padRight(16).substring(0, 16) ??
                  '',
              style: BioTypography.caption.copyWith(
                color: fgMuted,
                fontSize: 10,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInputBar(BuildContext context) {
    return AssistantChatComposerBar(
      controller: _textController,
      onSend: _sendText,
      isSending: _sending,
      hintText: 'Escribí un mensaje…',
      maxLines: 4,
      attachments: ChatComposerAttachments(
        onImage: _chatPolicy.canUploadImage ? _pickImage : null,
        onDocument: null,
        onAudio: _chatPolicy.canUploadAudio ? _recordAndSendAudio : null,
        audioActive: _isRecording,
      ),
    );
  }
}
