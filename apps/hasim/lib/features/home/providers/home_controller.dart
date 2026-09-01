
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_exception.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

class HomeState {
  const HomeState({this.loading = false, this.snapshot, this.error});
  final bool loading;
  final HomeSnapshot? snapshot;
  final String? error;
  HomeState copyWith({bool? loading, HomeSnapshot? snapshot, String? error}) => HomeState(
        loading: loading ?? this.loading,
        snapshot: snapshot ?? this.snapshot,
        error: error,
      );
}

class HomeController extends StateNotifier<HomeState> {
  HomeController(this._ref) : super(const HomeState()) {
    refresh();
  }
  final Ref _ref;

  Future<void> refresh() async {
    if (!_ref.read(authControllerProvider).isAuthenticated) return;
    state = state.copyWith(loading: true, error: null);
    try {
      final snap = await _ref.read(homeRepositoryProvider).home();
      state = HomeState(loading: false, snapshot: snap);
    } on ApiException catch (e) {
      state = HomeState(loading: false, snapshot: state.snapshot, error: e.message);
    } catch (_) {
      state = HomeState(loading: false, snapshot: state.snapshot, error: 'تعذر تحديث الرئيسية.');
    }
  }
}

final homeControllerProvider = StateNotifierProvider<HomeController, HomeState>((ref) => HomeController(ref));
