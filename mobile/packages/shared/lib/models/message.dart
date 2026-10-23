class Message {
  final String id;
  final String senderId;
  final String senderName;
  final String content;
  final DateTime timestamp;
  bool isResent;

  Message({
    required this.senderId,
    required this.senderName,
    required this.content,
    DateTime? timestamp,
    this.isResent = false,
    String? id,
  }) : id = id ?? DateTime.now().millisecondsSinceEpoch.toString(),
       timestamp = timestamp ?? DateTime.now();

  Map<String, dynamic> toJson() => {
    'id': id,
    'senderId': senderId,
    'senderName': senderName,
    'content': content,
    'timestamp': timestamp.toIso8601String(),
    'isResent': isResent,
  };

  factory Message.fromJson(Map<String, dynamic> json) => Message(
    id: json['id'],
    senderId: json['senderId'],
    senderName: json['senderName'],
    content: json['content'],
    timestamp: DateTime.parse(json['timestamp']),
    isResent: json['isResent'] ?? false,
  );
}
