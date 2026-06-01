import 'package:flutter/material.dart';

Widget buildLocalChatImage({
  required String source,
  double? width,
  double? height,
  BoxFit fit = BoxFit.cover,
  required Widget Function() onError,
}) {
  if (source.startsWith('blob:')) {
    return Image.network(
      source,
      width: width,
      height: height,
      fit: fit,
      errorBuilder: (_, __, ___) => onError(),
    );
  }
  return onError();
}

ImageProvider localChatImageProvider(String source) {
  if (source.startsWith('blob:')) {
    return NetworkImage(source);
  }
  throw StateError('No es una URL local de vista previa: $source');
}
