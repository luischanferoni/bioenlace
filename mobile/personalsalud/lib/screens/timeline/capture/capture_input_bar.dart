import 'package:flutter/material.dart';
import 'package:shared/shared.dart';


/// Barra de entrada de la captura: texto, dictado y estado de transcripción.
class CaptureInputBar extends StatelessWidget {
  const CaptureInputBar({
    super.key,
    required this.controller,
    required this.focusNode,
    required this.onSend,
    required this.busy,
    required this.canCapture,
    required this.isEpisode,
    required this.sttStatus,
    required this.dictating,
    this.onVoice,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final VoidCallback onSend;
  final bool busy;
  final bool canCapture;
  final bool isEpisode;
  final String sttStatus;
  final bool dictating;
  final VoidCallback? onVoice;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (sttStatus.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(
              left: BioSpacing.sm,
              right: BioSpacing.sm,
              bottom: BioSpacing.xs,
            ),
            child: Text(
              sttStatus,
              style: BioTypography.caption.copyWith(color: cs.primary),
            ),
          ),
        AssistantChatComposerBar(
          controller: controller,
          focusNode: focusNode,
          onSend: onSend,
          isSending: busy,
          sendIcon: Icons.playlist_add,
          hintText: canCapture
              ? (isEpisode ? 'Dictar o escribir la evolución…' : 'Dictar o escribir la consulta…')
              : (isEpisode ? 'Escribir evolución…' : 'Escribir consulta…'),
          maxLines: 6,
          onVoice: onVoice,
          voiceActive: dictating,
        ),
      ],
    );
  }
}