
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

class WorkspacePickerScreen extends ConsumerWidget {
  const WorkspacePickerScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('اختر مساحة العمل')),
      body: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: auth.workspaces.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final ws = auth.workspaces[index];
          final selected = auth.workspace?.id == ws.id;
          return ListTile(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: BorderSide(color: selected ? Theme.of(context).colorScheme.primary : Colors.grey.shade300)),
            title: Text(ws.name),
            subtitle: Text(ws.type ?? ''),
            trailing: selected ? Icon(Icons.check_circle, color: Theme.of(context).colorScheme.primary) : null,
            onTap: () async {
              await ref.read(authControllerProvider.notifier).switchWorkspace(ws);
              if (context.mounted) context.go('/home');
            },
          );
        },
      ),
    );
  }
}
