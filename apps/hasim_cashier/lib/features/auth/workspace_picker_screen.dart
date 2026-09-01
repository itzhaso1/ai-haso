import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/cashier_api.dart';
import '../../core/auth/auth_controller.dart';

class WorkspacePickerScreen extends ConsumerStatefulWidget {
  const WorkspacePickerScreen({super.key});

  @override
  ConsumerState<WorkspacePickerScreen> createState() =>
      _WorkspacePickerScreenState();
}

class _WorkspacePickerScreenState extends ConsumerState<WorkspacePickerScreen> {
  List<Map<String, dynamic>> _items = const [];
  var _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final session = ref.read(authControllerProvider).valueOrNull;
      if (session != null && session.workspaces.isNotEmpty) {
        setState(() {
          _items = session.workspaces;
          _loading = false;
        });
        return;
      }
      final data = await ref.read(cashierApiProvider).get('/workspaces');
      final list = <Map<String, dynamic>>[];
      final raw = data['workspaces'];
      if (raw is List) {
        for (final item in raw) {
          if (item is Map) {
            final map = Map<String, dynamic>.from(item);
            if (map['workspace'] is Map) {
              final ws = Map<String, dynamic>.from(map['workspace'] as Map);
              list.add({
                ...ws,
                'pos_enabled': map['pos_enabled'] == true,
              });
            } else {
              list.add(map);
            }
          }
        }
      }
      setState(() {
        _items = list;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'تعذر تحميل مساحات العمل.';
        _loading = false;
      });
    }
  }

  Future<void> _select(Map<String, dynamic> workspace) async {
    await ref.read(authControllerProvider.notifier).selectWorkspace(workspace);
    if (!mounted) return;
    if (workspace['pos_enabled'] == false) {
      context.go('/pos-blocked');
    } else {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('اختر مساحة العمل')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: _items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final ws = _items[index];
                    final posEnabled = ws['pos_enabled'] != false;
                    return ListTile(
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                        side: const BorderSide(color: Color(0xFFE2E8F0)),
                      ),
                      title: Text(
                        (ws['name'] as String?) ?? 'Workspace',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      subtitle: Text(
                        posEnabled ? 'الكاشير متاح' : 'الكاشير غير متاح في الباقة',
                      ),
                      trailing: const Icon(Icons.chevron_left),
                      onTap: () => _select(ws),
                    );
                  },
                ),
    );
  }
}
