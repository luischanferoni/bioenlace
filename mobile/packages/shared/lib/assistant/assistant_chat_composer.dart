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
    this.maxLines = 6,
    this.leading,
    this.focusNode,
  });

  final TextEditingController controller;
  final VoidCallback onSend;
  final bool isSending;
  final String hintText;
  final int maxLines;
  /// Acciones opcionales a la izquierda del campo (p. ej. imagen / audio en motivos).
  final List<Widget>? leading;
  final FocusNode? focusNode;

  @override
  State<AssistantChatComposerBar> createState() => _AssistantChatComposerBarState();
}

class _AssistantChatComposerBarState extends State<AssistantChatComposerBar> {
  @override
  void didUpdateWidget(covariant AssistantChatComposerBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.hintText != widget.hintText) {
      setState(() {});
    }
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
                suffixIcon: widget.isSending
                    ? Padding(
                        padding: const EdgeInsets.all(12.0),
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: cs.primary,
                        ),
                      )
                    : null,
              ),
              onSubmitted: (_) => widget.onSend(),
              enabled: !widget.isSending,
            ),
          ),
          const SizedBox(width: 8),
          Container(
            decoration: BoxDecoration(
              color: cs.primary,
              shape: BoxShape.circle,
            ),
            child: IconButton(
              icon: Icon(Icons.send, color: cs.onPrimary),
              onPressed: widget.isSending ? null : widget.onSend,
            ),
          ),
        ],
      ),
    );
  }
}
