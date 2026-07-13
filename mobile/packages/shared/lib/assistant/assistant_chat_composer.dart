import 'package:flutter/material.dart';

import 'assistant_composer_capture.dart';

/// Barra inferior del asistente: campo multilínea que crece con el texto.
class AssistantChatComposerBar extends StatefulWidget {
  const AssistantChatComposerBar({
    super.key,
    required this.controller,
    required this.onSend,
    required this.isSending,
    this.hintText = kAssistantComposerDefaultHint,
    this.maxLines = 2,
    this.leading,
    this.focusNode,
    this.onVoice,
    this.voiceActive = false,
    this.sendIcon = Icons.send,
  });

  final TextEditingController controller;
  final VoidCallback onSend;
  final bool isSending;
  final String hintText;
  final int maxLines;
  /// Acciones opcionales a la izquierda del campo (p. ej. imagen / audio en motivos).
  final List<Widget>? leading;
  final FocusNode? focusNode;
  /// Si se define, el botón derecho muestra micrófono con el campo vacío y envío al escribir.
  final VoidCallback? onVoice;
  final bool voiceActive;
  final IconData sendIcon;

  @override
  State<AssistantChatComposerBar> createState() => _AssistantChatComposerBarState();
}

class _AssistantChatComposerBarState extends State<AssistantChatComposerBar> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_onTextChanged);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_onTextChanged);
    super.dispose();
  }

  void _onTextChanged() {
    if (mounted) setState(() {});
  }

  @override
  void didUpdateWidget(covariant AssistantChatComposerBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_onTextChanged);
      widget.controller.addListener(_onTextChanged);
    }
    if (oldWidget.hintText != widget.hintText ||
        oldWidget.voiceActive != widget.voiceActive) {
      setState(() {});
    }
  }

  Widget _buildTrailingAction(ColorScheme cs) {
    if (widget.isSending) {
      return Padding(
        padding: const EdgeInsets.all(12.0),
        child: CircularProgressIndicator(
          strokeWidth: 2,
          color: cs.primary,
        ),
      );
    }

    final hasText = widget.controller.text.trim().isNotEmpty;
    final useVoice = widget.onVoice != null &&
        (widget.voiceActive || !hasText);

    final IconData icon;
    final VoidCallback? onPressed;
    Color background;
    Color foreground;

    if (useVoice) {
      icon = widget.voiceActive ? Icons.stop_circle : Icons.mic_none;
      onPressed = widget.onVoice;
      background = widget.voiceActive ? cs.error : cs.primary;
      foreground = widget.voiceActive ? cs.onError : cs.onPrimary;
    } else {
      icon = widget.sendIcon;
      onPressed = widget.onSend;
      background = cs.primary;
      foreground = cs.onPrimary;
    }

    return Container(
      decoration: BoxDecoration(
        color: background,
        shape: BoxShape.circle,
      ),
      child: IconButton(
        icon: Icon(icon, color: foreground),
        onPressed: onPressed,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final tt = Theme.of(context).textTheme;

    return Container(
      padding: const EdgeInsets.all(16.0),
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        boxShadow: [
          BoxShadow(
            color: cs.shadow.withValues(alpha: 0.12),
            blurRadius: 4,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (widget.leading != null) ...widget.leading!,
          Expanded(
            child: TextField(
              key: ValueKey(widget.hintText),
              controller: widget.controller,
              focusNode: widget.focusNode,
              minLines: 1,
              maxLines: widget.maxLines,
              keyboardType: TextInputType.multiline,
              textInputAction: TextInputAction.send,
              decoration: InputDecoration(
                hintText: widget.hintText,
                hintStyle: tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: BorderSide(
                    color: cs.primary.withValues(alpha: 0.3),
                  ),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: BorderSide(
                    color: cs.primary.withValues(alpha: 0.3),
                  ),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: BorderSide(
                    color: cs.primary,
                    width: 2,
                  ),
                ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 12,
                ),
              ),
              onSubmitted: (_) {
                if (widget.controller.text.trim().isNotEmpty) {
                  widget.onSend();
                } else if (widget.onVoice != null) {
                  widget.onVoice!();
                }
              },
              enabled: !widget.isSending,
            ),
          ),
          const SizedBox(width: 8),
          Tooltip(
            message: widget.sendIcon == Icons.fact_check_outlined
                ? 'Analizar consulta'
                : 'Enviar',
            child: _buildTrailingAction(cs),
          ),
        ],
      ),
    );
  }
}
