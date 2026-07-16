<?php

namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CardCommentController extends Controller
{
    use AuthorizesRequests;

    /**
     * カードにコメントを追加する。アーカイブ済み（論理削除済み）のカードは
     * ルートモデル結合の時点で withTrashed を付けていないため404になり、
     * コメント追加は自然にブロックされる。
     */
    public function store(Request $request, Card $card): RedirectResponse
    {
        $this->authorize('comment', $card);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'コメントを入力してください。',
        ]);

        $card->comments()->create([
            'author_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('status', 'comment-added');
    }
}
