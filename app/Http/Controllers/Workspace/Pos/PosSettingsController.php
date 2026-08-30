<?php

namespace App\Http\Controllers\Workspace\Pos;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PosSettingsController extends PosBaseController
{
    public function updateMenuSlider(Request $request): RedirectResponse
    {
        $this->authorizePos($request, 'menu.manage');
        $workspace = $this->currentWorkspace();

        $validated = $request->validate([
            'slider_images' => ['nullable', 'array', 'max:8'],
            'slider_images.*' => ['nullable', 'image', 'max:5120'],
            'remove_slider_images' => ['nullable', 'array'],
            'remove_slider_images.*' => ['string'],
        ]);

        $settings = (array) ($workspace->settings ?? []);
        $existingImages = collect(data_get($settings, 'pos.menu_slider_images', []))
            ->filter(fn ($path): bool => is_string($path) && $path !== '')
            ->values();

        $removeImages = collect($validated['remove_slider_images'] ?? [])
            ->filter(fn ($path): bool => is_string($path) && $path !== '')
            ->values();

        $imagesToKeep = $existingImages
            ->reject(fn (string $path): bool => $removeImages->contains($path))
            ->values();

        $prefix = 'workspaces/'.$workspace->id.'/pos-slider/';
        foreach ($removeImages as $removePath) {
            if (str_starts_with($removePath, $prefix)) {
                Storage::disk('public')->delete($removePath);
            }
        }

        $uploadedPaths = collect();
        foreach (($request->file('slider_images') ?? []) as $file) {
            if (! $file) {
                continue;
            }

            $uploadedPaths->push($file->store($prefix, 'public'));
        }

        $maxImages = 8;
        $allowedUploadCount = max(0, $maxImages - $imagesToKeep->count());
        $acceptedUploads = $uploadedPaths->take($allowedUploadCount)->values();
        $overflowUploads = $uploadedPaths->slice($allowedUploadCount)->values();
        foreach ($overflowUploads as $overflowPath) {
            Storage::disk('public')->delete($overflowPath);
        }

        $finalImages = $imagesToKeep
            ->merge($acceptedUploads)
            ->filter()
            ->unique()
            ->take($maxImages)
            ->values()
            ->all();

        data_set($settings, 'pos.menu_slider_images', $finalImages);
        $workspace->update(['settings' => $settings]);

        return back()->with('success', 'تم تحديث سلايدر المنيو بنجاح.');
    }
}
