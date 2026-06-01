import 'package:flutter/material.dart';

import '../config/api_config.dart';
import '../theme/tokens/tokens.dart';
import 'local_chat_image.dart';
import 'media_url.dart';

/// Imagen de chat (motivos / consulta): URL remota, ruta local o preview tras subir.
class ChatMediaImage extends StatelessWidget {
  final String source;
  final String? bearerToken;
  final double? width;
  final double? height;
  final BoxFit fit;
  final bool enableFullscreenOnTap;
  final Color? placeholderColor;

  const ChatMediaImage({
    super.key,
    required this.source,
    this.bearerToken,
    this.width = 220,
    this.height,
    this.fit = BoxFit.cover,
    this.enableFullscreenOnTap = true,
    this.placeholderColor,
  });

  String get _resolvedRemote {
    if (isLocalMediaFilePath(source)) return '';
    return resolveMediaContentUrl(source);
  }

  Map<String, String>? get _imageHeaders {
    if (bearerToken == null || bearerToken!.isEmpty) return null;
    return AppConfig.jsonHeaders(bearerToken: bearerToken, appClient: 'bioenlace-flutter')
      ..remove('Content-Type');
  }

  void _openFullscreen(BuildContext context, ImageProvider provider) {
    if (!enableFullscreenOnTap) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        fullscreenDialog: true,
        builder: (ctx) => _FullscreenImagePage(provider: provider),
      ),
    );
  }

  Widget _errorPlaceholder(BuildContext context) {
    final muted = placeholderColor ?? context.bio.textMuted;
    return SizedBox(
      width: width,
      height: height ?? 120,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.broken_image_outlined, color: muted, size: 40),
          const SizedBox(height: 6),
          Text(
            'No se pudo cargar la imagen',
            textAlign: TextAlign.center,
            style: BioTypography.caption.copyWith(color: muted),
          ),
        ],
      ),
    );
  }

  Widget _loadingPlaceholder(BuildContext context) {
    return SizedBox(
      width: width,
      height: height ?? 120,
      child: const Center(
        child: SizedBox(
          width: 28,
          height: 28,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (source.trim().isEmpty) {
      return _errorPlaceholder(context);
    }

    final Widget imageWidget;
    late final ImageProvider fullscreenProvider;

    if (isLocalMediaFilePath(source)) {
      try {
        fullscreenProvider = localChatImageProvider(source);
      } catch (_) {
        return _errorPlaceholder(context);
      }
      imageWidget = buildLocalChatImage(
        source: source,
        width: width,
        height: height,
        fit: fit,
        onError: () => _errorPlaceholder(context),
      );
    } else {
      final url = _resolvedRemote;
      if (url.isEmpty) {
        return _errorPlaceholder(context);
      }
      fullscreenProvider = NetworkImage(url, headers: _imageHeaders);
      imageWidget = Image.network(
        url,
        width: width,
        height: height,
        fit: fit,
        headers: _imageHeaders,
        loadingBuilder: (ctx, child, progress) {
          if (progress == null) return child;
          return _loadingPlaceholder(ctx);
        },
        errorBuilder: (_, __, ___) => _errorPlaceholder(context),
      );
    }

    final clipped = ClipRRect(
      borderRadius: BorderRadius.circular(BioRadius.sm),
      child: imageWidget,
    );

    if (!enableFullscreenOnTap) {
      return clipped;
    }

    return GestureDetector(
      onTap: () => _openFullscreen(context, fullscreenProvider),
      child: clipped,
    );
  }
}

class _FullscreenImagePage extends StatelessWidget {
  final ImageProvider provider;

  const _FullscreenImagePage({required this.provider});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Center(
        child: InteractiveViewer(
          minScale: 0.5,
          maxScale: 4,
          child: Image(image: provider, fit: BoxFit.contain),
        ),
      ),
    );
  }
}
