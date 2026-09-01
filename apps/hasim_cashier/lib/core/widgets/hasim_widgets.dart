import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../theme/hasim_colors.dart';
import '../theme/hasim_radius.dart';
import '../theme/hasim_spacing.dart';

class HsCard extends StatelessWidget {
  const HsCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(HasimSpacing.md),
    this.color = HasimColors.surface,
    this.borderColor = HasimColors.border,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color color;
  final Color borderColor;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(HasimRadius.lg),
        border: Border.all(color: borderColor),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 8,
            offset: Offset(0, 1),
          ),
        ],
      ),
      child: Padding(padding: padding, child: child),
    );
  }
}

class HsPrimaryButton extends StatelessWidget {
  const HsPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: loading ? null : onPressed,
      child: loading
          ? const SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
            )
          : Text(label),
    );
  }
}

class HsOutlineButton extends StatelessWidget {
  const HsOutlineButton({
    super.key,
    required this.label,
    required this.onPressed,
  });

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(onPressed: onPressed, child: Text(label));
  }
}

class HsEmpty extends StatelessWidget {
  const HsEmpty({
    super.key,
    required this.title,
    this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String? subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return HsCard(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: HasimSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            if (subtitle != null) ...[
              const SizedBox(height: HasimSpacing.sm),
              Text(
                subtitle!,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: HasimSpacing.md),
              TextButton(
                onPressed: onAction,
                child: Text(
                  actionLabel!,
                  style: const TextStyle(
                    color: HasimColors.brand,
                    fontWeight: FontWeight.w700,
                    decoration: TextDecoration.underline,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class HsBadge extends StatelessWidget {
  const HsBadge({
    super.key,
    required this.label,
    this.background = HasimColors.navIdleBg,
    this.foreground = HasimColors.ink,
  });

  final String label;
  final Color background;
  final Color foreground;

  factory HsBadge.occupied(String label) => HsBadge(
        label: label,
        background: HasimColors.occupiedSoft,
        foreground: HasimColors.occupied,
      );

  factory HsBadge.available(String label) => HsBadge(
        label: label,
        background: HasimColors.availableSoft,
        foreground: HasimColors.available,
      );

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(HasimRadius.pill),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w700,
          color: foreground,
        ),
      ),
    );
  }
}

class HsNavPill extends StatelessWidget {
  const HsNavPill({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? HasimColors.navActiveBg : HasimColors.navIdleBg,
      borderRadius: BorderRadius.circular(HasimRadius.sm),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(HasimRadius.sm),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: selected ? Colors.white : HasimColors.ink,
            ),
          ),
        ),
      ),
    );
  }
}

class HsCategoryTile extends StatelessWidget {
  const HsCategoryTile({
    super.key,
    required this.label,
    required this.count,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Material(
        color: selected ? HasimColors.brand : HasimColors.surface,
        borderRadius: BorderRadius.circular(HasimRadius.md),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(HasimRadius.md),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(HasimRadius.md),
              border: Border.all(
                color: selected ? HasimColors.brand : HasimColors.border,
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    label,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: selected ? Colors.white : HasimColors.ink,
                    ),
                  ),
                ),
                Text(
                  '$count',
                  style: TextStyle(
                    fontSize: 11,
                    color: selected
                        ? Colors.white.withValues(alpha: 0.85)
                        : HasimColors.muted,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class ProductCard extends StatelessWidget {
  const ProductCard({
    super.key,
    required this.name,
    required this.priceLabel,
    required this.currency,
    required this.onAdd,
    this.imageUrl,
  });

  final String name;
  final String priceLabel;
  final String currency;
  final String? imageUrl;
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: HasimColors.surface,
      borderRadius: BorderRadius.circular(HasimRadius.md),
      child: InkWell(
        onTap: onAdd,
        borderRadius: BorderRadius.circular(HasimRadius.md),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(HasimRadius.md),
            border: Border.all(color: HasimColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AspectRatio(
                aspectRatio: 5 / 3,
                child: ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(HasimRadius.md),
                  ),
                  child: imageUrl == null || imageUrl!.isEmpty
                      ? Container(
                          color: HasimColors.surfaceSoft,
                          child: const Icon(
                            Icons.image_outlined,
                            color: Color(0xFFCBD5E1),
                          ),
                        )
                      : CachedNetworkImage(
                          imageUrl: imageUrl!,
                          fit: BoxFit.cover,
                          placeholder: (_, _) => Container(
                            color: HasimColors.surfaceSoft,
                          ),
                          errorWidget: (_, _, _) => Container(
                            color: HasimColors.surfaceSoft,
                            child: const Icon(
                              Icons.broken_image_outlined,
                              color: Color(0xFFCBD5E1),
                            ),
                          ),
                        ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        height: 1.25,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(
                            text: priceLabel,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          TextSpan(
                            text: ' $currency',
                            style: const TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w500,
                              color: HasimColors.muted,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 6),
                    Container(
                      alignment: Alignment.center,
                      padding: const EdgeInsets.symmetric(vertical: 5),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(HasimRadius.sm),
                        border: Border.all(color: HasimColors.cta),
                      ),
                      child: const Text(
                        '+ إضافة',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: HasimColors.ctaDark,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class ConnectionBanner extends StatelessWidget {
  const ConnectionBanner({
    super.key,
    required this.online,
    this.pendingCount = 0,
  });

  final bool online;
  final int pendingCount;

  @override
  Widget build(BuildContext context) {
    if (online && pendingCount == 0) {
      return const SizedBox.shrink();
    }
    return Container(
      width: double.infinity,
      color: online ? HasimColors.brandSoft : HasimColors.warningSoft,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: Text(
        online
            ? '$pendingCount عمليات بانتظار المزامنة'
            : 'أنت تعمل دون اتصال — سيتم مزامنة الطلبات عند عودة الإنترنت.',
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: online ? HasimColors.brandDark : HasimColors.warning,
        ),
      ),
    );
  }
}
