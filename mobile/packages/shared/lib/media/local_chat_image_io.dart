import 'dart:io';

import 'package:flutter/material.dart';

Widget buildLocalChatImage({
  required String source,
  double? width,
  double? height,
  BoxFit fit = BoxFit.cover,
  required Widget Function() onError,
}) {
  final file = File(source);
  if (!file.existsSync()) {
    return onError();
  }
  return Image.file(
    file,
    width: width,
    height: height,
    fit: fit,
    errorBuilder: (_, __, ___) => onError(),
  );
}

ImageProvider localChatImageProvider(String source) => FileImage(File(source));
