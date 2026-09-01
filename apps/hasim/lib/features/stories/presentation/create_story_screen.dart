import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/network/api_exception.dart';
import 'package:hasim/core/theme/app_theme.dart';
import 'package:hasim/features/stories/providers/stories_controller.dart';
import 'package:image_picker/image_picker.dart';

/// Create story. Visibility defaults to `workspace`.
/// `selected` / `hidden` user targeting is deferred — no workspace members list API on mobile yet.
class CreateStoryScreen extends ConsumerStatefulWidget {
  const CreateStoryScreen({super.key});

  @override
  ConsumerState<CreateStoryScreen> createState() => _CreateStoryScreenState();
}

class _CreateStoryScreenState extends ConsumerState<CreateStoryScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  final _text = TextEditingController();
  final _caption = TextEditingController();
  Color _bg = const Color(0xFF067E6B);
  XFile? _media;
  bool _isVideo = false;
  bool _publishing = false;
  double _uploadProgress = 0;
  static const _expiresInHours = 24;

  static const _colors = [
    Color(0xFF067E6B),
    Color(0xFF0F172A),
    Color(0xFF1D4ED8),
    Color(0xFFB45309),
    Color(0xFFBE123C),
    Color(0xFF7C3AED),
    Color(0xFF0E7490),
  ];

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabs.dispose();
    _text.dispose();
    _caption.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    final file = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (file == null) return;
    setState(() {
      _media = file;
      _isVideo = false;
    });
  }

  Future<void> _pickVideo() async {
    final file = await ImagePicker().pickVideo(source: ImageSource.gallery);
    if (file == null) return;
    setState(() {
      _media = file;
      _isVideo = true;
    });
  }

  String _hex(Color c) {
    final v = c.toARGB32().toRadixString(16).padLeft(8, '0');
    return '#${v.substring(2)}';
  }

  Future<void> _publish() async {
    final tab = _tabs.index;
    final type = tab == 0 ? 'text' : (tab == 1 ? 'image' : 'video');
    if (type == 'text' && _text.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('اكتب نص القصة.')));
      return;
    }
    if (type != 'text' && _media == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('اختر ملفًا للقصة.')));
      return;
    }

    setState(() {
      _publishing = true;
      _uploadProgress = 0;
    });
    try {
      await ref.read(storyRepositoryProvider).create(
            type: type,
            bodyText: type == 'text' ? _text.text.trim() : null,
            caption: _caption.text.trim().isEmpty ? null : _caption.text.trim(),
            backgroundColor: type == 'text' ? _hex(_bg) : null,
            visibility: 'workspace',
            expiresInHours: _expiresInHours,
            file: _media == null ? null : File(_media!.path),
            onSendProgress: (sent, total) {
              if (!mounted || total <= 0) return;
              setState(() => _uploadProgress = sent / total);
            },
          );
      await ref.read(storiesControllerProvider.notifier).refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم نشر القصة')));
        context.pop();
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تعذر نشر القصة')));
    } finally {
      if (mounted) setState(() => _publishing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('قصة جديدة'),
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'نص'),
            Tab(text: 'صورة'),
            Tab(text: 'فيديو'),
          ],
        ),
        actions: [
          TextButton(onPressed: _publishing ? null : _publish, child: Text(_publishing ? '...' : 'نشر')),
        ],
      ),
      body: Column(
        children: [
          if (_publishing) LinearProgressIndicator(value: _uploadProgress > 0 ? _uploadProgress : null),
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: [
                _TextTab(
                  controller: _text,
                  bg: _bg,
                  colors: _colors,
                  onColor: (c) => setState(() => _bg = c),
                ),
                _MediaTab(
                  label: 'اختر صورة',
                  media: !_isVideo ? _media : null,
                  onPick: _pickImage,
                  caption: _caption,
                ),
                _MediaTab(
                  label: 'اختر فيديو',
                  media: _isVideo ? _media : null,
                  onPick: _pickVideo,
                  caption: _caption,
                  isVideo: true,
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                InputDecorator(
                  decoration: const InputDecoration(labelText: 'الظهور'),
                  child: Text(
                    'مساحة العمل فقط · تنتهي خلال $_expiresInHours ساعة',
                    style: TextStyle(color: Colors.grey.shade700),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'تحديد مستخدمين محددين/مخفيين غير متاح حالياً (لا توجد واجهة أعضاء للمساحة على الموبايل).',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600, height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TextTab extends StatelessWidget {
  const _TextTab({
    required this.controller,
    required this.bg,
    required this.colors,
    required this.onColor,
  });

  final TextEditingController controller;
  final Color bg;
  final List<Color> colors;
  final ValueChanged<Color> onColor;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          height: 280,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(16)),
          child: TextField(
            controller: controller,
            maxLines: null,
            expands: true,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800),
            decoration: const InputDecoration(
              border: InputBorder.none,
              filled: false,
              hintText: 'اكتب هنا...',
              hintStyle: TextStyle(color: Colors.white54),
            ),
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 10,
          children: [
            for (final c in colors)
              GestureDetector(
                onTap: () => onColor(c),
                child: Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: c,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: c == bg ? AppTheme.brand : Colors.transparent,
                      width: 2.5,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _MediaTab extends StatelessWidget {
  const _MediaTab({
    required this.label,
    required this.onPick,
    required this.caption,
    this.media,
    this.isVideo = false,
  });

  final String label;
  final VoidCallback onPick;
  final TextEditingController caption;
  final XFile? media;
  final bool isVideo;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        AspectRatio(
          aspectRatio: 9 / 12,
          child: Material(
            color: Colors.black12,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              onTap: onPick,
              borderRadius: BorderRadius.circular(16),
              child: media == null
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(isVideo ? Icons.videocam_outlined : Icons.image_outlined, size: 40),
                          const SizedBox(height: 8),
                          Text(label),
                        ],
                      ),
                    )
                  : isVideo
                      ? Center(child: Text(media!.name, textAlign: TextAlign.center))
                      : ClipRRect(
                          borderRadius: BorderRadius.circular(16),
                          child: Image.file(File(media!.path), fit: BoxFit.cover),
                        ),
            ),
          ),
        ),
        const SizedBox(height: 12),
        TextField(controller: caption, decoration: const InputDecoration(labelText: 'تعليق (اختياري)')),
      ],
    );
  }
}
