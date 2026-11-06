<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Konten landing (group "landing") butuh landing.manage; pengaturan lain butuh setting.update.
     * Satu permintaan yang mencampur keduanya butuh kedua izin.
     */
    public function authorize(): bool
    {
        $keys = array_keys((array) $this->input('settings', []));
        $groups = Setting::query()->whereIn('key', $keys)->pluck('group')->unique();

        if ($groups->contains('landing') && ! $this->user()->can(Permissions::LANDING_MANAGE)) {
            return false;
        }
        if ($groups->isEmpty() || $groups->contains(fn ($g) => $g !== 'landing')) {
            return $this->user()->can('update', Setting::class);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
        ];
    }
}
