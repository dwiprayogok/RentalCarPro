<?php

namespace App\Http\Requests\Owner;

use App\Models\OwnerSettlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OwnerSettlement::class);
    }

    public function rules(): array
    {
        return [
            'owner_id' => ['required', 'integer', Rule::exists('car_owners', 'id')->whereNull('deleted_at')],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $tz = config('app.display_timezone');
            $from = Carbon::parse($this->input('from'), $tz);
            $to = Carbon::parse($this->input('to'), $tz);
            if ($to->toDateString() > Carbon::now($tz)->toDateString()) {
                $v->errors()->add('to', 'Periode setoran tidak boleh melewati hari ini.');
            }
            if ($from->diffInDays($to) > 366) {
                $v->errors()->add('to', 'Periode setoran maksimal 1 tahun.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['owner_id' => 'pemilik', 'from' => 'tanggal awal', 'to' => 'tanggal akhir', 'notes' => 'catatan'];
    }
}
