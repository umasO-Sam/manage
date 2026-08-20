<?php

namespace App\Http\Requests;

use App\Rules\UploadedFileMatchesImageExtension;
use Illuminate\Foundation\Http\FormRequest;

/**
 * カード詳細から添付資料だけを足すときの入力。
 * 取得した見積PDFなどを、修正画面を開かずにその場で足せるようにするためのもの。
 * ルールはカードの新規作成・修正と揃えている。
 */
class StoreCardAttachmentsRequest extends FormRequest
{
    /** 認可はコントローラのauthorize()で行う。 */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'min:1'],
            // FDC(CAD等の独自拡張子)はMIMEタイプの自動判定に乗らないため、拡張子ベースのextensionsルールを使う
            'attachments.*' => ['file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,fdc', new UploadedFileMatchesImageExtension], // 10MB (KB単位)
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachments.required' => '添付するファイルを選んでください。',
            'attachments.*.max' => '添付ファイルは1ファイルあたり10MBまでです。',
            'attachments.*.extensions' => '添付ファイルはPDF・画像・Office文書・FDCのみアップロードできます。',
        ];
    }
}
