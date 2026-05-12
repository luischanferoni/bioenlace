import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:record/record.dart';
import 'package:path_provider/path_provider.dart';

import '../services/motivos_consulta_service.dart';
import '../theme/paciente_theme_extensions.dart';

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

  static const String _welcomeMessage =
      'Contanos en pocas palabras por qué pediste este turno (por ejemplo: dolor de cabeza, control, resultado de análisis). Podés escribir, enviar audios o fotos. El médico lo verá antes de la consulta.';

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

  Future<void> _loadMessages() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _service.getMessages(widget.consultaId);
    setState(() {
      _loading = false;
      _messages = result['messages'] ?? [];
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
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _sendText() async {
    final text = _textController.text.trim();
    if (text.isEmpty || _sending) return;
    _textController.clear();
    setState(() => _sending = true);

    final result = await _service.sendMessage(widget.consultaId, text);
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      setState(() => _messages = [..._messages, result['data']]);
      _scrollToBottom();
    } else {
      if (mounted) {
        final cs = context.pacienteColors;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message']?.toString() ?? 'Error'),
            backgroundColor: cs.error,
          ),
        );
      }
    }
  }

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final XFile? file = await picker.pickImage(source: ImageSource.gallery);
    if (file == null || !mounted) return;
    await _uploadFile(File(file.path), 'imagen');
  }

  Future<void> _recordAndSendAudio() async {
    if (_isRecording) {
      final path = await _recorder.stop();
      setState(() => _isRecording = false);
      if (path != null && path.isNotEmpty) await _uploadFile(File(path), 'audio');
      return;
    }
    if (!await _recorder.hasPermission()) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Se necesita permiso de micrófono')),
        );
      }
      return;
    }
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/audio_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(const RecordConfig(encoder: AudioEncoder.aacLc, sampleRate: 44100), path: path);
    setState(() => _isRecording = true);
  }

  Future<void> _uploadFile(File file, String messageType) async {
    if (!file.existsSync() || _sending) return;
    setState(() => _sending = true);
    final result = await _service.uploadFile(widget.consultaId, file, messageType: messageType);
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      setState(() => _messages = [..._messages, result['data']]);
      _scrollToBottom();
    } else {
      if (mounted) {
        final cs = context.pacienteColors;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message']?.toString() ?? 'Error'),
            backgroundColor: cs.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.pacienteColors;
    final tt = context.pacienteTextTheme;
    final onBubbleSecondary = cs.onPrimary.withValues(alpha: 0.72);
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.titulo),
        backgroundColor: cs.primary,
        foregroundColor: cs.onPrimary,
        elevation: 0,
      ),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24.0),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(_error!, textAlign: TextAlign.center),
                              const SizedBox(height: 16),
                              ElevatedButton(onPressed: _loadMessages, child: const Text('Reintentar')),
                            ],
                          ),
                        ),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
                        itemCount: _messages.length + 1,
                        itemBuilder: (context, index) {
                          if (index == 0) {
                            return Padding(
                              padding: const EdgeInsets.fromLTRB(12, 8, 12, 16),
                              child: Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: cs.primary.withValues(alpha: 0.08),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: cs.primary.withValues(alpha: 0.2)),
                                ),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Icon(Icons.info_outline, color: cs.primary, size: 22),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        _welcomeMessage,
                                        style: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          }
                          final m = _messages[index - 1] as Map<String, dynamic>;
                          final type = m['message_type'] as String? ?? 'texto';
                          final content = m['content']?.toString() ?? '';

                          return Align(
                            alignment: Alignment.centerRight,
                            child: Container(
                              margin: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
                              padding: const EdgeInsets.all(12),
                              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.8),
                              decoration: BoxDecoration(
                                color: cs.primary,
                                borderRadius: BorderRadius.circular(16),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (type == 'texto')
                                    Text(
                                      content,
                                      style: tt.bodyMedium?.copyWith(color: cs.onPrimary),
                                    )
                                  else if (type == 'imagen' && content.isNotEmpty)
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(8),
                                      child: Image.network(
                                        content,
                                        fit: BoxFit.cover,
                                        width: 200,
                                        loadingBuilder: (_, child, progress) => progress == null
                                            ? child
                                            : SizedBox(
                                                height: 80,
                                                width: 80,
                                                child: Center(
                                                  child: CircularProgressIndicator(color: onBubbleSecondary),
                                                ),
                                              ),
                                        errorBuilder: (_, __, ___) =>
                                            Icon(Icons.broken_image, color: onBubbleSecondary, size: 48),
                                      ),
                                    )
                                  else if (type == 'audio')
                                    Row(
                                      children: [
                                        Icon(Icons.mic, color: onBubbleSecondary),
                                        const SizedBox(width: 8),
                                        Text(
                                          'Audio',
                                          style: tt.labelSmall?.copyWith(color: onBubbleSecondary),
                                        ),
                                      ],
                                    )
                                  else
                                    Text(
                                      content,
                                      style: tt.labelSmall?.copyWith(color: onBubbleSecondary),
                                    ),
                                  const SizedBox(height: 4),
                                  Text(
                                    _formatCreatedAt(m['created_at']),
                                    style: tt.labelSmall?.copyWith(
                                      color: onBubbleSecondary,
                                      fontSize: 10,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          ),
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(color: Theme.of(context).scaffoldBackgroundColor),
            child: SafeArea(
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.image),
                    onPressed: _sending ? null : _pickImage,
                  ),
                  IconButton(
                    icon: Icon(_isRecording ? Icons.stop : Icons.mic),
                    onPressed: _sending ? null : _recordAndSendAudio,
                  ),
                  Expanded(
                    child: TextField(
                      controller: _textController,
                      decoration: const InputDecoration(
                        hintText: 'Escribí el motivo o enviá audio/foto...',
                        border: OutlineInputBorder(),
                        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      ),
                      onSubmitted: (_) => _sendText(),
                      enabled: !_sending,
                    ),
                  ),
                  IconButton(
                    icon: _sending
                        ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.send),
                    onPressed: _sending ? null : _sendText,
                  ),
                ],
              ),
            ),
          ),
        ],
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
