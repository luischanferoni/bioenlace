import 'package:cross_file/cross_file.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
import 'package:shared/shared.dart';
import '../services/consulta_chat_service.dart';
import '../services/consulta_async_api.dart';
import 'patient_timeline_screen.dart';

/// Pantalla de chat con el paciente para una consulta (app Personal de Salud).
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
  Map<String, dynamic>? _intakeContext;
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
    _asyncApi = ConsultaAsyncApi(authToken: widget.authToken, userId: widget.userId);
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
      final data = result['data'];
      if (data is Map) {
        final ctx = data['intake_context'];
        _intakeContext = ctx is Map ? Map<String, dynamic>.from(ctx) : null;
        final policyRaw = data['chat_policy'];
        _chatPolicy = AsyncConsultaChatPolicy.fromApi(
          policyRaw is Map ? Map<String, dynamic>.from(policyRaw) : null,
        );
      } else {
        _intakeContext = null;
      }
      if (result['success'] != true) _error = result['message'] as String?;
    });
    _scrollToBottom();
  }

  Future<void> _cerrarConsulta() async {
    if (_chatPolicy.resolutions.isEmpty) return;
    String? selected = _chatPolicy.resolutions.first.code;
    final noteController = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: const Text('Cerrar consulta'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                DropdownButtonFormField<String>(
                  value: selected,
                  decoration: const InputDecoration(labelText: 'Resolución'),
                  items: _chatPolicy.resolutions
                      .map(
                        (e) => DropdownMenuItem(value: e.code, child: Text(e.label)),
                      )
                      .toList(),
                  onChanged: (v) => setLocal(() => selected = v),
                ),
                BioSpacing.gapH(BioSpacing.sm),
                TextField(
                  controller: noteController,
                  decoration: const InputDecoration(
                    labelText: 'Nota para el paciente (opcional)',
                  ),
                  maxLines: 3,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
            TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Cerrar')),
          ],
        ),
      ),
    );
    if (ok != true || selected == null || !mounted) return;
    await _aplicarResolucion(selected!, note: noteController.text);
  }

  Future<void> _resolverConCodigo(AsyncConsultaResolution resolution) async {
    if (_sending) return;
    String note = '';
    if (resolution.requireNote) {
      final noteController = TextEditingController();
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(resolution.label),
          content: TextField(
            controller: noteController,
            decoration: const InputDecoration(
              labelText: 'Nota para el paciente (obligatoria)',
            ),
            maxLines: 3,
            autofocus: true,
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Volver')),
            TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirmar')),
          ],
        ),
      );
      if (ok != true || !mounted) return;
      note = noteController.text.trim();
      if (note.isEmpty) {
        _showError('Indicá una nota para el paciente.');
        return;
      }
    } else {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(resolution.label),
          content: const Text('¿Confirmás esta resolución?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Volver')),
            TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirmar')),
          ],
        ),
      );
      if (ok != true || !mounted) return;
    }
    await _aplicarResolucion(resolution.code, note: note);
  }

  Future<void> _aplicarResolucion(String code, {String note = ''}) async {
    setState(() => _sending = true);
    final res = await _asyncApi.cerrarComoStaff(
      widget.consultaId,
      code,
      note: note,
    );
    if (!mounted) return;
    setState(() => _sending = false);
    if (res['success'] == true) {
      await _loadMessages();
    } else {
      _showError(res['message']?.toString() ?? 'No se pudo cerrar');
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
      setState(() => _messages = [..._messages, result['data']]);
      _scrollToBottom();
    } else {
      _showError(result['message']?.toString() ?? 'Error');
    }
  }

  Future<void> _pickDocument() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
      withData: kIsWeb,
    );
    if (result == null || result.files.isEmpty || !mounted) return;
    final picked = result.files.single;
    if (kIsWeb) {
      if (picked.bytes == null || picked.bytes!.isEmpty) {
        _showError('No se pudo leer el PDF');
        return;
      }
      await _uploadBytes(picked.bytes!, picked.name, 'documento');
      return;
    }
    final path = picked.path;
    if (path == null || path.isEmpty) {
      _showError('No se pudo leer el PDF');
      return;
    }
    await _uploadFile(XFile(path), 'documento');
  }

  Future<void> _uploadBytes(List<int> bytes, String name, String messageType) async {
    if (_sending) return;
    setState(() => _sending = true);
    final result = await _chatService.uploadBytes(
      widget.consultaId,
      bytes,
      filename: name,
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
    final path = '${dir.path}/audio_${DateTime.now().millisecondsSinceEpoch}.m4a';
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
      appBar: BioAppBar(
        title: widget.titulo,
        actions: [
          if (_chatPolicy.canClose)
            IconButton(
              icon: const Icon(Icons.check_circle_outline),
              tooltip: 'Cerrar consulta',
              onPressed: _loading ? null : _cerrarConsulta,
            ),
        ],
      ),
      body: Column(
        children: [
          if (_intakeContext != null) _buildIntakeContextBanner(context),
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
          if (_chatPolicy.composerEnabled) _buildInputBar(context),
          if (_chatPolicy.showResolutionActions) _buildResolutionActions(context),
        ],
      ),
    );
  }

  Widget _buildResolutionActions(BuildContext context) {
    final hint = _chatPolicy.hint;
    final resolutions = _chatPolicy.resolutions;
    return Material(
      elevation: 4,
      color: context.bio.paperBackground,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            BioSpacing.md,
            BioSpacing.sm,
            BioSpacing.md,
            BioSpacing.md,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (hint.isNotEmpty) ...[
                Text(
                  hint,
                  style: BioTypography.caption.copyWith(color: context.bio.textMuted),
                ),
                BioSpacing.gapH(BioSpacing.sm),
              ],
              for (var i = 0; i < resolutions.length; i++) ...[
                if (i > 0) BioSpacing.gapH(BioSpacing.xs),
                i == 0
                    ? BioButton.primary(
                        label: resolutions[i].label,
                        onPressed: _sending ? null : () => _resolverConCodigo(resolutions[i]),
                      )
                    : BioButton.outlinePrimary(
                        label: resolutions[i].label,
                        onPressed: _sending ? null : () => _resolverConCodigo(resolutions[i]),
                      ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildIntakeContextBanner(BuildContext context) {
    final ctx = _intakeContext;
    if (ctx == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        BioSpacing.md,
        BioSpacing.sm,
        BioSpacing.md,
        0,
      ),
      child: AsyncIntakeContextPanel(
        intakeContext: ctx,
        compact: true,
        onReference: _onIntakeReference,
      ),
    );
  }

  void _onIntakeReference(Map<String, dynamic> reference) {
    final kind = reference['kind']?.toString().trim() ?? '';
    final personaId = int.tryParse(reference['subject_persona_id']?.toString() ?? '');
    if (kind == 'clinical_history') {
      if (personaId == null || personaId <= 0) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => PatientTimelineScreen(
            personaId: personaId,
            authToken: widget.authToken,
            soloVer: true,
          ),
        ),
      );
      return;
    }
    if (kind == 'reference_encounter') {
      final refEnc = _intakeContext?['reference_encounter'];
      final detail = refEnc is Map ? refEnc['detail'] : null;
      if (detail is Map) {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => AsyncReferenceEncounterDetailScreen(
              detail: Map<String, dynamic>.from(detail),
            ),
          ),
        );
        return;
      }
      if (personaId != null && personaId > 0) {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => PatientTimelineScreen(
              personaId: personaId,
              authToken: widget.authToken,
              soloVer: true,
            ),
          ),
        );
      }
    }
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
    if (type == 'audio' || type == 'documento') {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            type == 'audio' ? Icons.mic : Icons.picture_as_pdf_outlined,
            color: mutedColor,
            size: 18,
          ),
          BioSpacing.gapW(BioSpacing.sm),
          Text(
            asyncChatAttachmentLabel(type),
            style: BioTypography.bodySm.copyWith(color: mutedColor),
          ),
        ],
      );
    }
    if (isImageMessageType(type) && content.isNotEmpty) {
      return Text(
        asyncChatAttachmentLabel(type),
        style: BioTypography.bodySm.copyWith(color: mutedColor),
      );
    }
    return Text(
      content.isNotEmpty ? content : asyncChatAttachmentLabel(type),
      style: BioTypography.bodySm.copyWith(color: mutedColor),
    );
  }

  String _formatCreatedAt(dynamic v) {
    final s = v?.toString() ?? '';
    return s.length >= 16 ? s.substring(0, 16) : s;
  }

  Widget _buildInputBar(BuildContext context) {
    return AssistantChatComposerBar(
      controller: _textController,
      onSend: _sendText,
      isSending: _sending,
      hintText: 'Escribí un mensaje…',
      maxLines: 4,
      attachments: ChatComposerAttachments(
        onDocument: _chatPolicy.canUploadDocument ? _pickDocument : null,
      ),
      onVoice: _chatPolicy.canUploadAudio ? _recordAndSendAudio : null,
      voiceActive: _isRecording,
    );
  }

  @override
  void dispose() {
    _textController.dispose();
    _scrollController.dispose();
    _recorder.dispose();
    super.dispose();
  }
}
