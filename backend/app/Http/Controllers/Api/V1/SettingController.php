<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Setting::class);

        return SettingResource::collection(Setting::query()->orderBy('group')->orderBy('id')->get());
    }

    public function update(UpdateSettingsRequest $request): AnonymousResourceCollection
    {
        $this->settings->update($request->validated('settings'), $request->user());

        return SettingResource::collection(Setting::query()->orderBy('group')->orderBy('id')->get())
            ->additional(['message' => 'Pengaturan berhasil disimpan.']);
    }
}
