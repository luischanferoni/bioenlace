import 'package:flutter/material.dart';

import 'local_chat_image_io.dart' if (dart.library.html) 'local_chat_image_web.dart'
    as local;

Widget buildLocalChatImage({
  required String source,
  double? width,
  double? height,
  BoxFit fit = BoxFit.cover,
  required Widget Function() onError,
}) {
  return local.buildLocalChatImage(
    source: source,
    width: width,
    height: height,
    fit: fit,
    onError: onError,
  );
}

ImageProvider localChatImageProvider(String source) =>
    local.localChatImageProvider(source);
