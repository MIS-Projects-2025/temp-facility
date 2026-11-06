<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePmHistoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'done_date'    => ['required', 'date', 'before_or_equal:' . now('Asia/Manila')->toDateString()],
            'performed_by' => ['nullable', 'string', 'max:50'],
            'notes'        => ['nullable', 'string'],
        ];
    }
}
