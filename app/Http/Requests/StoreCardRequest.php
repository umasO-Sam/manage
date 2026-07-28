<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    /**
     * Any authenticated staff member may submit a request.
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
        $isPurchase = $this->route('workflow')?->slug === 'purchase';

        return [
            'order_number_id' => ['required', 'integer', 'exists:order_numbers,id'],
            'machine_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
            'item_name' => ['required', 'string', 'max:255'],
            'model_number' => [$isPurchase ? 'required' : 'nullable', 'string', 'max:255'],
            'manufacturer' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:20'],
            'due_date_type' => $isPurchase ? ['required', 'in:asap,normal,specific'] : ['nullable', 'in:asap,normal,specific'],
            'due_date' => $isPurchase
                ? ['nullable', 'required_if:due_date_type,specific', 'date', 'after_or_equal:today']
                : ['required', 'date', 'after_or_equal:today'],
            'attachments' => ['array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx'], // 10MB (KB単位)
        ];
    }

    public function messages(): array
    {
        return [
            'order_number_id.required' => '注番を選択してください。',
            'order_number_id.exists' => '選択された注番が見つかりません。',
            'machine_number.regex' => '機械装置番号は半角英数字とハイフン(-)で入力してください。',
            'model_number.required' => '型式を入力してください。',
            'due_date_type.required' => '希望納期を選択してください。',
            'due_date.required_if' => '日付指定の場合は希望納期の日付を入力してください。',
            'due_date.after_or_equal' => '希望納期・希望回答期限は今日以降の日付を指定してください。',
            'attachments.*.max' => '添付ファイルは1ファイルあたり10MBまでです。',
            'attachments.*.mimes' => '添付ファイルはPDF・画像・Office文書のみアップロードできます。',
        ];
    }
}
