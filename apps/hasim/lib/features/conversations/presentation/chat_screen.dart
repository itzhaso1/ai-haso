
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/widgets/async_body.dart';
import 'package:hasim/features/conversations/providers/conversations_controller.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart' hide TextDirection;

class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({super.key, required this.conversationId});
  final int conversationId;

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen> {
  final _controller = TextEditingController();
  final _scroll = ScrollController();

  @override
  void dispose() {
    _controller.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _controller.text;
    _controller.clear();
    await ref.read(chatControllerProvider(widget.conversationId).notifier).send(text);
    await Future<void>.delayed(const Duration(milliseconds: 50));
    if (_scroll.hasClients) {
      _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
    }
  }

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final file = await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (file == null) return;
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('ارفع المرفق بعد إرسال رسالة نصية أولاً عبر واجهة المرفقات المرتبطة برسالة (API: /messages/{id}/attachments).')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(chatControllerProvider(widget.conversationId));
    final title = state.conversation?.title ?? 'محادثة';

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
            if (state.conversation != null)
              Text(state.conversation!.channelLabel, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: AsyncBody(
              loading: state.loading && state.messages.isEmpty,
              error: state.error,
              isEmpty: !state.loading && state.messages.isEmpty,
              emptyTitle: 'ابدأ المحادثة',
              emptySubtitle: 'لا توجد رسائل بعد.',
              onRetry: () => ref.read(chatControllerProvider(widget.conversationId).notifier).refresh(),
              child: ListView.builder(
                controller: _scroll,
                padding: const EdgeInsets.all(12),
                itemCount: state.messages.length,
                itemBuilder: (context, index) {
                  if (index == 0 && state.nextCursor != null) {
                    // load older hint
                    WidgetsBinding.instance.addPostFrameCallback((_) {
                      // no-op auto; user can pull
                    });
                  }
                  final m = state.messages[index];
                  return _Bubble(message: m);
                },
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 4, 8, 8),
              child: Row(
                children: [
                  IconButton(onPressed: _pickImage, icon: const Icon(Icons.attach_file)),
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      minLines: 1,
                      maxLines: 4,
                      decoration: const InputDecoration(hintText: 'اكتب رسالة...'),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  const SizedBox(width: 6),
                  IconButton.filled(
                    onPressed: state.sending ? null : _send,
                    icon: const Icon(Icons.send),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message});
  final MessageModel message;

  @override
  Widget build(BuildContext context) {
    final mine = message.isOutbound;
    final bg = mine ? Theme.of(context).colorScheme.primary : Colors.white;
    final fg = mine ? Colors.white : const Color(0xFF0F172A);
    // RTL: outbound on the visual start (right) side.
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(16),
          border: mine ? null : Border.all(color: Colors.grey.shade200),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(message.content, style: TextStyle(color: fg, height: 1.35)),
            for (final a in message.attachments)
              if (a.kind == 'image' && a.downloadUrl != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: CachedNetworkImage(imageUrl: a.downloadUrl!, height: 160, fit: BoxFit.cover),
                  ),
                ),
            const SizedBox(height: 4),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  message.createdAt == null ? '' : DateFormat('HH:mm').format(message.createdAt!.toLocal()),
                  style: TextStyle(color: fg.withValues(alpha: 0.75), fontSize: 11),
                ),
                if (message.localPending) ...[
                  const SizedBox(width: 6),
                  SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 1.5, color: fg)),
                ],
                if (message.localFailed) ...[
                  const SizedBox(width: 6),
                  Icon(Icons.error_outline, size: 14, color: Colors.red.shade200),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
