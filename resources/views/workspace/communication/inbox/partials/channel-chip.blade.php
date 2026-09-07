@php
    use App\Support\Communication\ChannelPresentation;
    $channel = $channel ?? 'manual';
    $comingSoon = $comingSoon ?? ChannelPresentation::isComingSoon($channel);
@endphp

<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ ChannelPresentation::badgeClasses($channel) }}">
    <span>{{ ChannelPresentation::label($channel) }}</span>
    @if($comingSoon)
        <span class="rounded-full bg-white/70 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">Coming Soon</span>
    @endif
</span>
