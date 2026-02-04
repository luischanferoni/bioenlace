import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';
import 'package:image_picker/image_picker.dart';
import 'package:record/record.dart';
import 'package:path_provider/path_provider.dart';
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

  Future<void> _loadMessages() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await _chatService.getMessages(widget.consultaId);
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

    final result = await _chatService.sendMessage(widget.consultaId, text);
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      setState(() => _messages = [..._messages, result['data']]);
      _scrollToBottom();
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message']?.toString() ?? 'Error'), backgroundColor: Colors.red),
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
      if (path != null && path.isNotEmpty) await _uploadFile(File(path), 'audio');
      return;
    }
    if (!await _recorder.hasPermission()) return;
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/audio_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(const RecordConfig(encoder: AudioEncoder.aacLc, sampleRate: 44100), path: path);
    setState(() => _isRecording = true);
  }

  Future<void> _uploadFile(File file, String messageType) async {
    if (!file.existsSync() || _sending) return;
    setState(() => _sending = true);
    final result = await _chatService.uploadFile(widget.consultaId, file, messageType: messageType);
    setState(() => _sending = false);
    if (result['success'] == true && result['data'] != null) {
      setState(() => _messages = [..._messages, result['data']]);
      _scrollToBottom();
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message']?.toString() ?? 'Error'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.titulo, style: AppTheme.h2Style.copyWith(color: Colors.white)),
        backgroundColor: Theme.of(context).primaryColor,
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
                        itemCount: _messages.length,
                        itemBuilder: (context, i) {
                          final m = _messages[i] as Map<String, dynamic>;
                          final isMe = m['user_role'] == 'paciente' || m['user_id'].toString() == widget.userId;
                          final type = m['message_type'] as String? ?? 'texto';
                          final content = m['content']?.toString() ?? '';

                          return Align(
                            alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
                            child: Container(
                              margin: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
                              padding: const EdgeInsets.all(12),
                              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.8),
                              decoration: BoxDecoration(
                                color: isMe ? Theme.of(context).primaryColor : Colors.grey[200],
                                borderRadius: BorderRadius.circular(16),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (type == 'texto')
                                    Text(
                                      content,
                                      style: TextStyle(color: isMe ? Colors.white : Colors.black87, fontSize: 14),
                                    )
                                  else if (type == 'imagen' && content.isNotEmpty)
                                    InkWell(
                                      onTap: () {
                                        // Opcional: abrir imagen a pantalla completa o en navegador
                                      },
                                      child: Image.network(content, fit: BoxFit.cover, loadingBuilder: (_, child, progress) =>
                                          progress == null ? child : const SizedBox(height: 80, width: 80, child: Center(child: CircularProgressIndicator()))),
                                    )
                                  else if (type == 'audio')
                                    Row(
                                      children: [
                                        Icon(isMe ? Icons.mic : Icons.audiotrack, color: isMe ? Colors.white70 : Colors.grey[700]),
                                        const SizedBox(width: 8),
                                        Text('Audio', style: TextStyle(color: isMe ? Colors.white70 : Colors.grey[700], fontSize: 12)),
                                      ],
                                    )
                                  else if (type == 'video')
                                    Row(
                                      children: [
                                        Icon(Icons.videocam, color: isMe ? Colors.white70 : Colors.grey[700]),
                                        const SizedBox(width: 8),
                                        Text('Video', style: TextStyle(color: isMe ? Colors.white70 : Colors.grey[700], fontSize: 12)),
                                      ],
                                    )
                                  else
                                    Text(content, style: TextStyle(color: isMe ? Colors.white70 : Colors.grey[700], fontSize: 12)),
                                  const SizedBox(height: 4),
                                  Text(
                                    m['created_at']?.toString().substring(0, 16) ?? '',
                                    style: TextStyle(color: isMe ? Colors.white70 : Colors.grey[600], fontSize: 10),
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
                    icon: const Icon(Icons.videocam),
                    onPressed: _sending ? null : _pickVideo,
                  ),
                  IconButton(
                    icon: Icon(_isRecording ? Icons.stop : Icons.mic),
                    onPressed: _sending ? null : _recordAndSendAudio,
                  ),
                  Expanded(
                    child: TextField(
                      controller: _textController,
                      decoration: const InputDecoration(
                        hintText: 'Escribí un mensaje...',
                        border: OutlineInputBorder(),
                        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      ),
                      onSubmitted: (_) => _sendText(),
                      enabled: !_sending,
                    ),
                  ),
                  IconButton(
                    icon: _sending ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.send),
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
