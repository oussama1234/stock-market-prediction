<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarketNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'sometimes|string|min:1|max:100',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from',
            'date' => 'sometimes|string|in:today,last_7d,last_30d',
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'important_first' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $datePreset = $this->input('date');
        $from = $this->input('from');
        $to = $this->input('to');

        // Handle date-only inputs (YYYY-MM-DD) from UI by expanding to full-day range
        $isDateOnly = function ($val) {
            return is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val);
        };
        if ($isDateOnly($from)) {
            $from = $from . ' 00:00:00';
        }
        if ($isDateOnly($to)) {
            $to = $to . ' 23:59:59';
        }

        if ($datePreset && (!$from || !$to)) {
            switch ($datePreset) {
                case 'today':
                    // Use rolling 24-hour window for "today" to capture items from the last day
                    $from = now()->subHours(24)->toDateTimeString();
                    $to = now()->toDateTimeString();
                    break;
                case 'last_7d':
                    // Rolling 7-day window in hours
                    $from = now()->subHours(24 * 7)->toDateTimeString();
                    $to = now()->toDateTimeString();
                    break;
                case 'last_30d':
                    // Rolling 30-day window in hours
                    $from = now()->subHours(24 * 30)->toDateTimeString();
                    $to = now()->toDateTimeString();
                    break;
            }
        }

        $this->merge([
            'from' => $from,
            'to' => $to,
            'limit' => $this->input('limit', 20),
            'page' => $this->input('page', 1),
            'important_first' => filter_var($this->input('important_first', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}