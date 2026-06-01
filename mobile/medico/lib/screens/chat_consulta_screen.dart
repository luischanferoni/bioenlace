import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:image_picker/image_picker.dart';
import '../services/consulta_chat_service.dart';

/// Pantalla de chat con el paciente para una consulta (app médico).
class ChatConsultaScreen extends StatefulWidget {
  final int consultaId;
  final String? authToken;
  final String userId;
  final String? userName;
  final String titulo;

  const ChatConsultaScreen({
    Key? key,
    required this.consultaId,
    required this.authToken,
    required this.userId,
    this.userName,
    this.titulo = 'Chat con paciente',
  }) : super(key: key);

  @override
  State<ChatConsultaScreen> createState() => _ChatConsultaScreenState();
}

class _ChatConsultaScreenState extends State<ChatConsultaScreen> {
  late ConsultaChatService _chatService;
  final TextEditingController _textController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  List<dynamic> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;

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
    await _uploadFile(file, 'imagen');
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
            'user_role': 'medico',
            'created_at': DateTime.now().toIso8601String(),
          },
        ];
      });
      _scrollToBottom();
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
    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(
        vertical: BioSpacing.sm,
        horizontal: BioSpacing.md,
      ),
      itemCount: _messages.length,
      itemBuilder: (context, i) {
        final m = _messages[i] as Map<String, dynamic>;
        final isMe = m['user_role'] == 'medico' ||
            m['user_id'].toString() == widget.userId;
        return isMe ? _buildBubbleMe(context, m) : _buildBubbleOther(context, m);
      },
    );
  }

  Widget _buildBubbleMe(BuildContext context, Map<String, dynamic> m) {
    final palette = IntentPalette.of(UiIntent.primary);
    final type = m['message_type'] as String? ?? 'texto';
    final content = m['content']?.toString() ?? '';
    final softColor = palette.onBase.withValues(alpha: 0.78);

    Widget child = _buildContent(
      type,
      content,
      textColor: palette.onBase,
      mutedColor: softColor,
    );

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

  Widget _buildBubbleOther(BuildContext context, Map<String, dynamic> m) {
    final tokens = context.bio;
    final type = m['message_type'] as String? ?? 'texto';
    final content = m['content']?.toString() ?? '';

    Widget child = _buildContent(
      type,
      content,
      textColor: tokens.textTitle,
      mutedColor: tokens.textMuted,
    );

    return Align(
      alignment: Alignment.centerLeft,
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
          color: tokens.paperSurfaceSunken,
          borderRadius: BorderRadius.circular(BioRadius.md),
          border: Border.all(
            color: tokens.paperBorderDefault,
            width: BorderWidth.thin,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            child,
            BioSpacing.gapH(BioSpacing.xs),
            Text(
              _formatCreatedAt(m['created_at']),
              style: BioTypography.caption.copyWith(
                color: tokens.textMuted,
                fontSize: 10,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContent(
    String type,
    String content, {
    required Color textColor,
    required Color mutedColor,
  }) {
    if (type == 'texto') {
      return Text(
        content,
        style: BioTypography.body.copyWith(color: textColor),
      );
    }
    if (isImageMessageType(type) && content.isNotEmpty) {
      return ChatMediaImage(
        source: content,
        bearerToken: widget.authToken,
        width: 220,
        fit: BoxFit.cover,
        placeholderColor: mutedColor,
      );
    }
    if (type == 'audio') {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.mic, color: mutedColor, size: 18),
          BioSpacing.gapW(BioSpacing.sm),
          Text('Audio', style: BioTypography.bodySm.copyWith(color: mutedColor)),
        ],
      );
    }
    if (type == 'video') {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.videocam, color: mutedColor, size: 18),
          BioSpacing.gapW(BioSpacing.sm),
          Text('Video', style: BioTypography.bodySm.copyWith(color: mutedColor)),
        ],
      );
    }
    return Text(
      content,
      style: BioTypography.bodySm.copyWith(color: mutedColor),
    );
  }

  String _formatCreatedAt(dynamic v) {
    final s = v?.toString() ?? '';
    return s.length >= 16 ? s.substring(0, 16) : s;
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

  @override
  void dispose() {
    _textController.dispose();
    _scrollController.dispose();
    super.dispose();
  }
}
