import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';

import '../services/consulta_chat_service.dart';

/// Chat con el médico: mensajes de texto, imagen, audio y video.
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
      if (result['success'] != true) _error = result['message'] as String?;
    });
    _scrollToBottom();
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
      setState(() => _messages = [..._messages, result['data']]);
      _scrollToBottom();
    } else {
      _showError(result['message']?.toString() ?? 'Error');
    }
  }

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final XFile? file = await picker.pickImage(source: ImageSource.gallery);
    if (file == null || !mounted) return;
    await _uploadFile(File(file.path), 'imagen');
  }

  Future<void> _pickVideo() async {
    final picker = ImagePicker();
    final XFile? file = await picker.pickVideo(source: ImageSource.gallery);
    if (file == null || !mounted) return;
    await _uploadFile(File(file.path), 'video');
  }

  Future<void> _recordAndSendAudio() async {
    if (_isRecording) {
      final path = await _recorder.stop();
      setState(() => _isRecording = false);
      if (path != null && path.isNotEmpty) {
        await _uploadFile(File(path), 'audio');
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

  Future<void> _uploadFile(File file, String messageType) async {
    if (!file.existsSync() || _sending) return;
    setState(() => _sending = true);
    final result = await _chatService.uploadFile(
      widget.consultaId,
      file,
      messageType: messageType,
    );
    if (!mounted) return;
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      setState(() => _messages = [..._messages, result['data']]);
      _scrollToBottom();
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
          _buildInputBar(context),
        ],
      ),
    );
  }

  Widget _buildBubble(BuildContext context, Map<String, dynamic> m) {
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
    if (type == 'texto') {
      contenido = Text(content, style: BioTypography.body.copyWith(color: fg));
    } else if (type == 'imagen' && content.isNotEmpty) {
      contenido = ClipRRect(
        borderRadius: BorderRadius.circular(BioRadius.sm),
        child: Image.network(
          content,
          fit: BoxFit.cover,
          loadingBuilder: (_, child, progress) => progress == null
              ? child
              : SizedBox(
                  height: 80,
                  width: 80,
                  child: Center(
                    child: CircularProgressIndicator(color: fgMuted),
                  ),
                ),
          errorBuilder: (_, __, ___) =>
              Icon(Icons.broken_image, color: fgMuted, size: 48),
        ),
      );
    } else if (type == 'audio') {
      contenido = Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(isMe ? Icons.mic : Icons.audiotrack, color: fgMuted, size: 18),
          BioSpacing.gapW(BioSpacing.sm),
          Text('Audio', style: BioTypography.bodySm.copyWith(color: fgMuted)),
        ],
      );
    } else if (type == 'video') {
      contenido = Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.videocam_outlined, color: fgMuted, size: 18),
          BioSpacing.gapW(BioSpacing.sm),
          Text('Video', style: BioTypography.bodySm.copyWith(color: fgMuted)),
        ],
      );
    } else {
      contenido = Text(content, style: BioTypography.bodySm.copyWith(color: fgMuted));
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
    final tokens = context.bio;
    return Container(
      decoration: BoxDecoration(
        color: tokens.paperSurface,
        border: BioBorder.top(BorderWidth.thin, tokens.paperBorderDefault),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: BioSpacing.sm,
            vertical: BioSpacing.sm,
          ),
          child: Row(
            children: [
              IconButton(
                icon: const Icon(Icons.image_outlined),
                color: tokens.textBody,
                onPressed: _sending ? null : _pickImage,
              ),
              IconButton(
                icon: const Icon(Icons.videocam_outlined),
                color: tokens.textBody,
                onPressed: _sending ? null : _pickVideo,
              ),
              IconButton(
                icon: Icon(_isRecording ? Icons.stop_circle : Icons.mic_none),
                color: _isRecording
                    ? IntentPalette.of(UiIntent.danger).base
                    : tokens.textBody,
                onPressed: _sending ? null : _recordAndSendAudio,
              ),
              Expanded(
                child: TextField(
                  controller: _textController,
                  enabled: !_sending,
                  decoration: const InputDecoration(
                    hintText: 'Escribí un mensaje…',
                    isDense: true,
                  ),
                  onSubmitted: (_) => _sendText(),
                ),
              ),
              BioSpacing.gapW(BioSpacing.xs),
              IconButton(
                icon: _sending
                    ? const SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send),
                color: IntentPalette.of(UiIntent.primary).base,
                onPressed: _sending ? null : _sendText,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
