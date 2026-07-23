<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCardRequest extends FormRequest
{
    /**
     * Authorization is handled by CardPolicy::update in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_number_id' => ['required', 'integer', 'exists:order_numbers,id'],
            'item_name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:20'],
            // 既存カードの希望納期が既に過去日の場合でも他項目を修正できるよう、
            // 作成時と異なり「今日以降」の制約は課さない。
            'due_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_number_id.required' => '注番を選択してください。',
            'order_number_id.exists' => '選択された注番が見つかりません。',
        ];
    }
}
