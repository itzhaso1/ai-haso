import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/features/stories/providers/stories_controller.dart';

class StoryViewerArgs {
  const StoryViewerArgs({
    required this.buckets,
    this.bucketIndex = 0,
    this.storyIndex = 0,
  });

  final List<StoryBucket> buckets;
  final int bucketIndex;
  final int storyIndex;
}

class StoryViewerScreen extends ConsumerStatefulWidget {
  const StoryViewerScreen({super.key, required this.args});

  final StoryViewerArgs args;

  @override
  ConsumerState<StoryViewerScreen> createState() => _StoryViewerScreenState();
}

class _StoryViewerScreenState extends ConsumerState<StoryViewerScreen> {
  late int _bucketIndex;
  late int _storyIndex;
  Timer? _timer;
  double _progress = 0;
  bool _paused = false;
  final _reply = TextEditingController();
  final Set<int> _viewed = {};

  List<StoryBucket> get _buckets => widget.args.buckets;
  StoryBucket get _bucket => _buckets[_bucketIndex];
  StoryModel get _story => _bucket.stories[_storyIndex];

  Duration get _duration {
    if (_story.isVideo) return const Duration(seconds: 15);
    return const Duration(seconds: 5);
  }

  @override
  void initState() {
    super.initState();
    _bucketIndex = widget.args.bucketIndex.clamp(0, widget.args.buckets.length - 1);
    _storyIndex = widget.args.storyIndex.clamp(0, _bucket.stories.length - 1);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _markViewed();
      _startTimer();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _reply.dispose();
    super.dispose();
  }

  Future<void> _markViewed() async {
    final id = _story.id;
    if (_viewed.contains(id) || _story.isMine) return;
    _viewed.add(id);
    try {
      await ref.read(storyRepositoryProvider).markViewed(id);
    } catch (_) {}
  }

  void _startTimer() {
    _timer?.cancel();
    _progress = 0;
    final totalMs = _duration.inMilliseconds;
    const tick = 50;
    _timer = Timer.periodic(const Duration(milliseconds: tick), (t) {
      if (!mounted || _paused) return;
      setState(() => _progress += tick / totalMs);
      if (_progress >= 1) {
        t.cancel();
        _next();
      }
    });
  }

  void _next() {
    if (_storyIndex < _bucket.stories.length - 1) {
      setState(() => _storyIndex++);
      _markViewed();
      _startTimer();
      return;
    }
    if (_bucketIndex < _buckets.length - 1) {
      setState(() {
        _bucketIndex++;
        _storyIndex = 0;
      });
      _markViewed();
      _startTimer();
      return;
    }
    if (mounted) context.pop();
  }

  void _prev() {
    if (_storyIndex > 0) {
      setState(() => _storyIndex--);
      _markViewed();
      _startTimer();
      return;
    }
    if (_bucketIndex > 0) {
      setState(() {
        _bucketIndex--;
        _storyIndex = _bucket.stories.length - 1;
      });
      _markViewed();
      _startTimer();
    } else {
      _startTimer();
    }
  }

  Color _parseBg(String? raw) {
    if (raw == null || raw.isEmpty) return const Color(0xFF067E6B);
    var s = raw.trim();
    if (s.startsWith('#')) s = s.substring(1);
    if (s.length == 6) {
      final v = int.tryParse(s, radix: 16);
      if (v != null) return Color(0xFF000000 | v);
    }
    return const Color(0xFF067E6B);
  }

  void _onReply() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('الرد عبر المحادثة قريبًا')),
    );
    _reply.clear();
  }

  @override
  Widget build(BuildContext context) {
    final story = _story;
    final bg = story.isText ? _parseBg(story.backgroundColor) : Colors.black;

    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onLongPressStart: (_) => setState(() => _paused = true),
        onLongPressEnd: (_) => setState(() => _paused = false),
        onTapUp: (d) {
          final w = MediaQuery.sizeOf(context).width;
          if (d.localPosition.dx < w * 0.35) {
            _prev();
          } else {
            _next();
          }
        },
        child: Stack(
          fit: StackFit.expand,
          children: [
            ColoredBox(color: bg),
            if (story.isImage && story.mediaUrl != null)
              CachedNetworkImage(imageUrl: story.mediaUrl!, fit: BoxFit.contain)
            else if (story.isVideo && story.mediaUrl != null)
              Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (story.thumbnailUrl != null)
                      CachedNetworkImage(imageUrl: story.thumbnailUrl!, height: 220, fit: BoxFit.cover)
                    else
                      const Icon(Icons.play_circle_outline, size: 72, color: Colors.white70),
                    const SizedBox(height: 12),
                    Text(story.caption ?? 'فيديو', style: const TextStyle(color: Colors.white70)),
                  ],
                ),
              )
            else if (story.isText)
              Center(
                child: Padding(
                  padding: const EdgeInsets.all(28),
                  child: Text(
                    story.bodyText ?? story.caption ?? '',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w800, height: 1.35),
                  ),
                ),
              ),
            if (story.caption != null && story.caption!.isNotEmpty && !story.isText)
              Positioned(
                left: 16,
                right: 16,
                bottom: 100,
                child: Text(
                  story.caption!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
                ),
              ),
            SafeArea(
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(10, 8, 10, 0),
                    child: Row(
                      children: [
                        for (var i = 0; i < _bucket.stories.length; i++) ...[
                          if (i > 0) const SizedBox(width: 4),
                          Expanded(
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(4),
                              child: LinearProgressIndicator(
                                value: i < _storyIndex
                                    ? 1
                                    : i == _storyIndex
                                        ? _progress.clamp(0, 1)
                                        : 0,
                                minHeight: 3,
                                backgroundColor: Colors.white24,
                                color: Colors.white,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 10, 4, 0),
                    child: Row(
                      children: [
                        CircleAvatar(
                          radius: 16,
                          backgroundColor: Colors.white24,
                          child: Text(
                            _bucket.authorName.isNotEmpty ? _bucket.authorName[0] : '?',
                            style: const TextStyle(color: Colors.white, fontSize: 13),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _bucket.authorName,
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
                          ),
                        ),
                        if (story.isMine)
                          Text(
                            '${story.viewsCount} مشاهدة',
                            style: const TextStyle(color: Colors.white70, fontSize: 12),
                          ),
                        IconButton(
                          onPressed: () => context.pop(),
                          icon: const Icon(Icons.close, color: Colors.white),
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),
                  if (!story.isMine)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                      child: Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _reply,
                              style: const TextStyle(color: Colors.white),
                              decoration: InputDecoration(
                                hintText: 'رد...',
                                hintStyle: const TextStyle(color: Colors.white54),
                                filled: true,
                                fillColor: Colors.white12,
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(24),
                                  borderSide: BorderSide.none,
                                ),
                                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                              ),
                              onSubmitted: (_) => _onReply(),
                            ),
                          ),
                          IconButton(
                            onPressed: _onReply,
                            icon: const Icon(Icons.send, color: Colors.white),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
