<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    /**
     * Any authenticated staff member may submit a purchase request.
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
            // [英数5〜7文字]-[英数3〜10文字]。購入部品手配では「参考」は不可（実在の注番が必須）。
            'order_no' => ['required', 'string', 'regex:/^[A-Za-z0-9]{5,7}-[A-Za-z0-9]{3,10}$/'],
            'item_name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'due_date' => ['required', 'date'],
            'attachments' => ['array'],
            'attachments.*' => ['file', 'max:10240'], // 10MB (KB単位)
        ];
    }

    public function messages(): array
    {
        return [
            'order_no.regex' => '注番は「英数5〜7文字-英数3〜10文字」の形式で入力してください（例: ZZ999-N99T99）。',
            'attachments.*.max' => '添付ファイルは1ファイルあたり10MBまでです。',
        ];
    }
}
