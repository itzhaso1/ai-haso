@extends('layouts.appointments')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Customize Website</h2>
                <p class="text-sm text-slate-500">تحكم في الهوية، المحتوى، والأقسام مع معاينة مباشرة.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('workspace.appointments.website.preview', $website) }}" target="_blank" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Preview</a>
                <a href="{{ route('workspace.appointments.website.domains', $website) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Domains</a>
            </div>
        </div>

        <form method="POST" action="{{ route('workspace.appointments.website.customize.update', $website) }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Business Name</label>
                    <input type="text" name="business_name" value="{{ old('business_name', $settings['business_name'] ?? $website->name) }}" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Font</label>
                    <input type="text" name="font" value="{{ old('font', $theme['font'] ?? 'Cairo') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Primary Color</label>
                    <input type="text" name="primary_color" value="{{ old('primary_color', $theme['primary_color'] ?? '#0f766e') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Secondary Color</label>
                    <input type="text" name="secondary_color" value="{{ old('secondary_color', $theme['secondary_color'] ?? '#14b8a6') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Direction</label>
                    <select name="direction" class="w-full rounded-xl border-slate-300 text-sm">
                        <option value="rtl" @selected(old('direction', $theme['direction'] ?? 'rtl') === 'rtl')>RTL</option>
                        <option value="ltr" @selected(old('direction', $theme['direction'] ?? 'rtl') === 'ltr')>LTR</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">CTA Text</label>
                    <input type="text" name="cta_text" value="{{ old('cta_text', $settings['cta_text'] ?? '') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Hero Title</label>
                    <input type="text" name="hero_title" value="{{ old('hero_title', $settings['hero_title'] ?? '') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Hero Description</label>
                    <textarea name="hero_description" rows="3" class="w-full rounded-xl border-slate-300 text-sm">{{ old('hero_description', $settings['hero_description'] ?? '') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">About Text</label>
                    <textarea name="about_text" rows="4" class="w-full rounded-xl border-slate-300 text-sm">{{ old('about_text', $settings['about_text'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Contact Phone</label>
                    <input type="text" name="contact_phone" value="{{ old('contact_phone', $settings['contact_phone'] ?? '') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Contact Email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email'] ?? '') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Contact Address</label>
                    <input type="text" name="contact_address" value="{{ old('contact_address', $settings['contact_address'] ?? '') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">SEO Title</label>
                    <input type="text" name="seo_title" value="{{ old('seo_title', $settings['seo_title'] ?? '') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">SEO Description</label>
                    <input type="text" name="seo_description" value="{{ old('seo_description', $settings['seo_description'] ?? '') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Logo</label>
                    <input type="file" name="logo" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Hero Image</label>
                    <input type="file" name="hero_image" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Favicon</label>
                    <input type="file" name="favicon" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Footer Text</label>
                    <input type="text" name="footer_text" value="{{ old('footer_text', $settings['footer_text'] ?? '') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
            </div>

            <div class="mt-5">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save Customization</button>
            </div>
        </form>

        <form method="POST" action="{{ route('workspace.appointments.website.sections.update', $website) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <h3 class="text-base font-semibold text-slate-900">Sections</h3>
            <p class="mt-1 text-xs text-slate-500">فعّل/عطّل الأقسام ورتّبها وأضف إعداداتها بصيغة JSON.</p>

            <div class="mt-4 space-y-4">
                @foreach($sections as $index => $section)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="grid gap-3 md:grid-cols-4">
                            <input type="hidden" name="sections[{{ $index }}][id]" value="{{ $section->id }}">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Section</label>
                                <input type="text" value="{{ $section->section_key }}" disabled class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Component</label>
                                <input type="text" value="{{ $section->component_key }}" disabled class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Position</label>
                                <input type="number" min="0" max="1000" name="sections[{{ $index }}][position]" value="{{ old("sections.$index.position", $section->position) }}" class="w-full rounded-xl border-slate-300 text-sm">
                            </div>
                            <div class="flex items-end">
                                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <input type="hidden" name="sections[{{ $index }}][is_enabled]" value="0">
                                    <input type="checkbox" name="sections[{{ $index }}][is_enabled]" value="1" @checked(old("sections.$index.is_enabled", $section->is_enabled)) class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                    Enabled
                                </label>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Config (JSON)</label>
                            <textarea name="sections[{{ $index }}][config]" rows="4" class="w-full rounded-xl border-slate-300 font-mono text-xs">{{ json_encode(old("sections.$index.config", $section->config ?? []), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</textarea>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save Sections</button>
            </div>
        </form>
    </div>

@endsection
