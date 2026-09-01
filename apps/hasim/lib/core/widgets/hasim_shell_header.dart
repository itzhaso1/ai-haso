import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/widgets/hasim_more_menu.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

/// رأس التطبيق الموحد (RTL):
/// يمين الشاشة = الحساب / هوية حاسم
/// يسار الشاشة = ⋯ المزيد
class HasimShellHeader extends ConsumerWidget implements PreferredSizeWidget {
  const HasimShellHeader({
    super.key,
    this.showBrand = true,
    this.extraActions = const [],
  });

  final bool showBrand;
  final List<Widget> extraActions;

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final user = auth.user;
    final theme = Theme.of(context);
    final initial = (user?.name.isNotEmpty == true) ? user!.name[0] : 'ح';

    return AppBar(
      automaticallyImplyLeading: false,
      centerTitle: false,
      titleSpacing: 4,
      // في RTL: leading يظهر يمين الشاشة → الحساب
      leadingWidth: 56,
      leading: Semantics(
        button: true,
        label: 'حسابي',
        child: IconButton(
          tooltip: 'حسابي',
          onPressed: () => context.push('/profile'),
          icon: CircleAvatar(
            radius: 16,
            backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.15),
            backgroundImage:
                user?.avatarUrl != null ? CachedNetworkImageProvider(user!.avatarUrl!) : null,
            child: user?.avatarUrl == null
                ? Text(
                    initial,
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                      color: theme.colorScheme.primary,
                    ),
                  )
                : null,
          ),
        ),
      ),
      title: showBrand
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'حاسم',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: theme.colorScheme.primary,
                  ),
                ),
                if (auth.workspace != null && (auth.workspaces.length > 1))
                  Text(
                    auth.workspace!.name,
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
              ],
            )
          : null,
      // في RTL: actions تظهر يسار الشاشة → ⋯
      actions: [
        ...extraActions,
        Semantics(
          button: true,
          label: 'المزيد',
          child: IconButton(
            tooltip: 'المزيد',
            onPressed: () => showHasimMoreMenu(context, ref),
            icon: const Icon(Icons.more_horiz),
          ),
        ),
      ],
    );
  }
}
