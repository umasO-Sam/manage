<?php

namespace App\Http\Requests;

use App\Models\WorkflowType;
use Closure;
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
        /** @var WorkflowType $workflow */
        $workflow = $this->route('workflow');

        return [
            'order_no' => ['required', 'string', $this->orderNoRule($workflow)],
            'item_name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:20'],
            'due_date' => ['required', 'date'],
            'attachments' => ['array'],
            'attachments.*' => ['file', 'max:10240'], // 10MB (KB単位)
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.*.max' => '添付ファイルは1ファイルあたり10MBまでです。',
        ];
    }

    /**
     * 注番は [英数5〜7文字]-[英数3〜10文字]。「参考」を許可するワークフロー
     * （見積り依頼など、注番を取得していない案件向け）では、その特例も通す。
     */
    private function orderNoRule(WorkflowType $workflow): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($workflow) {
            if ($workflow->allows_reference_order_no && $value === '参考') {
                return;
            }

            if (! preg_match('/^[A-Za-z0-9]{5,7}-[A-Za-z0-9]{3,10}$/', (string) $value)) {
                $fail($workflow->allows_reference_order_no
                    ? '注番は「英数5〜7文字-英数3〜10文字」の形式、または注番未取得の場合は「参考」を入力してください。'
                    : '注番は「英数5〜7文字-英数3〜10文字」の形式で入力してください（例: ZZ999-N99T99）。');
            }
        };
    }
}
