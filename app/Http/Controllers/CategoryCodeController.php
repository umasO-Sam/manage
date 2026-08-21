<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 分類コード(category_codes)のうち、画面から直せるようにした項目を扱う。
 *
 * 今のところ対象は説明(item_name)だけ。作業日報の「選択中」の下に出る内訳
 * (例: 59:機械製缶 なら「切出・製缶・塗装・部品製作」)で、何をその分類に付けるかの
 * 目安として全員が見る。仕入管理の人工データ一覧にも同じ値が出る。
 */
class CategoryCodeController extends Controller
{
    /**
     * 説明を書き換える。作業日報の入力途中に押せる場所にあるため、
     * 画面を再読み込みさせないよう(下書きが消えないよう)JSONで返す。
     */
    public function updateItemName(Request $request, CategoryCode $categoryCode): JsonResponse
    {
        abort_unless(
            $request->user()->canEditCategoryItemName(),
            403,
            '分類の説明を変更できるのは日報管理者・勤怠管理者・役員・資金管理者・administratorだけです。'
        );

        $data = $request->validate([
            'item_name' => ['nullable', 'string', 'max:255'],
        ]);

        $itemName = trim((string) ($data['item_name'] ?? '')) ?: null;
        $before = $categoryCode->item_name;

        if ($itemName === $before) {
            return response()->json(['item_name' => $itemName, 'changed' => false]);
        }

        // 全員の画面に出る共通の表記なので、誰が何をどう変えたかを操作ログに残す。
        DB::transaction(function () use ($categoryCode, $itemName, $before) {
            $categoryCode->update(['item_name' => $itemName]);

            OperationLog::record(
                OperationLog::ACTION_CATEGORY_ITEM_NAME_UPDATE,
                $categoryCode,
                Auth::id(),
                $categoryCode->code.':'.($categoryCode->sub_category ?: $categoryCode->major_category)
                    .'「'.($before ?? '（未設定）').'」→「'.($itemName ?? '（未設定）').'」'
            );
        });

        return response()->json(['item_name' => $itemName, 'changed' => true]);
    }
}
