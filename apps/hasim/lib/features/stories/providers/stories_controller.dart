import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_exception.dart';

class StoryBucket {
  const StoryBucket({
    required this.authorKey,
    required this.authorName,
    required this.isMine,
    required this.stories,
    this.avatarPath,
  });

  final String authorKey;
  final String authorName;
  final bool isMine;
  final String? avatarPath;
  final List<StoryModel> stories;
}

class StoriesState {
  const StoriesState({
    this.buckets = const [],
    this.loading = false,
    this.error,
  });

  final List<StoryBucket> buckets;
  final bool loading;
  final String? error;

  StoriesState copyWith({
    List<StoryBucket>? buckets,
    bool? loading,
    String? error,
    bool clearError = false,
  }) {
    return StoriesState(
      buckets: buckets ?? this.buckets,
      loading: loading ?? this.loading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class StoriesController extends StateNotifier<StoriesState> {
  StoriesController(this._ref) : super(const StoriesState()) {
    refresh();
  }

  final Ref _ref;

  Future<void> refresh() async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      final stories = await _ref.read(storyRepositoryProvider).list();
      state = state.copyWith(buckets: _group(stories), loading: false, clearError: true);
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
    } catch (_) {
      state = state.copyWith(loading: false, error: 'تعذر تحميل القصص.');
    }
  }

  List<StoryBucket> _group(List<StoryModel> stories) {
    final order = <String>[];
    final map = <String, List<StoryModel>>{};
    final meta = <String, ({String name, bool isMine, String? avatar})>{};

    for (final s in stories) {
      final key = s.isMine
          ? 'mine'
          : 'u-${s.author?.id ?? s.id}';
      if (!map.containsKey(key)) {
        order.add(key);
        meta[key] = (
          name: s.isMine ? 'قصتي' : (s.author?.name ?? 'عضو'),
          isMine: s.isMine,
          avatar: s.author?.avatarPath,
        );
      }
      map.putIfAbsent(key, () => []).add(s);
    }

    // Ensure "my story" create affordance even with zero stories.
    if (!map.containsKey('mine')) {
      order.insert(0, 'mine');
      meta['mine'] = (name: 'قصتي', isMine: true, avatar: null);
      map['mine'] = [];
    } else {
      order.remove('mine');
      order.insert(0, 'mine');
    }

    return [
      for (final key in order)
        StoryBucket(
          authorKey: key,
          authorName: meta[key]!.name,
          isMine: meta[key]!.isMine,
          avatarPath: meta[key]!.avatar,
          stories: map[key]!,
        ),
    ];
  }
}

final storiesControllerProvider =
    StateNotifierProvider<StoriesController, StoriesState>((ref) => StoriesController(ref));
