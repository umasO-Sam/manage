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
        $isPurchase = $this->route('card')?->workflowType?->slug === 'purchase';

        return [
            'order_number_id' => ['required', 'integer', 'exists:order_numbers,id'],
            'machine_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
            'item_name' => ['required', 'string', 'max:255'],
            'model_number' => ['required', 'string', 'max:255'],
            'manufacturer' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:20'],
            'due_date_type' => $isPurchase ? ['required', 'in:asap,normal,specific'] : ['nullable', 'in:asap,normal,specific'],
            // 既存カードの希望納期が既に過去日の場合でも他項目を修正できるよう、
            // 作成時と異なり「今日以降」の制約は課さない。
            'due_date' => $isPurchase
                ? ['nullable', 'required_if:due_date_type,specific', 'date']
                : ['required', 'date'],
            'attachments' => ['array'],
            // FDC(CAD等の独自拡張子)はMIMEタイプの自動判定に乗らないため、拡張子ベースのextensionsルールを使う
            'attachments.*' => ['file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,fdc'], // 10MB (KB単位)
            'remove_attachments' => ['array'],
            'remove_attachments.*' => ['integer'],
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
            'attachments.*.max' => '添付ファイルは1ファイルあたり10MBまでです。',
            'attachments.*.extensions' => '添付ファイルはPDF・画像・Office文書・FDCのみアップロードできます。',
        ];
    }
}
