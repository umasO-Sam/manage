<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 休日マスタ管理(祝日・会社休日・有給休暇取得推奨日)。資材管理担当者のみが
 * 登録・編集・削除できる(routes/web.phpのprocurement.managerミドルウェアでアクセス制御)。
 * 本人カレンダー・全社休日一覧画面(フェーズ3以降の別項目)で参照する想定。
 */
class HolidayController extends Controller
{
    public function index(): View
    {
        return view('holidays.index', [
            'holidays' => Holiday::orderBy('date')->get(),
        ]);
    }

    public function create(): View
    {
        return view('holidays.create');
    }

    public function store(Request $request): RedirectResponse
    {
        // lang/ja/validation.phpのattributesは'name'=>'氏名'(担当者管理)・'type'=>'申請種別'
        // (休暇・勤務申請)で既に使われているため、ここでは休日マスタ用に上書きする。
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Holiday::TYPES))],
        ], [
            'date.unique' => 'この日付は既に休日マスタに登録されています。',
        ], [
            'date' => '日付', 'name' => '名称', 'type' => '種別',
        ]);

        // アプリ側のunique検証と登録の間に別リクエストが割り込む競合状態に備え、
        // DB側の一意制約違反も500エラーにせず通常の入力エラーとして扱う。
        try {
            Holiday::create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['date' => 'この日付は既に休日マスタに登録されています。']);
        }

        return redirect()->route('holidays.index')->with('status', 'holiday-created');
    }

    public function edit(Holiday $holiday): View
    {
        return view('holidays.edit', ['holiday' => $holiday]);
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($holiday->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Holiday::TYPES))],
        ], [
            'date.unique' => 'この日付は既に休日マスタに登録されています。',
        ], [
            'date' => '日付', 'name' => '名称', 'type' => '種別',
        ]);

        try {
            $holiday->update($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['date' => 'この日付は既に休日マスタに登録されています。']);
        }

        return redirect()->route('holidays.index')->with('status', 'holiday-updated');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->route('holidays.index')->with('status', 'holiday-deleted');
    }
}
