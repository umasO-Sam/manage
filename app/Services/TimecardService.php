<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 別システム「timecard-new」の出退勤打刻を参照専用で読み、作業日報の入力内容と
 * 突き合わせる。作業日報とタイムカードは紐づけず(日報の提出状態は打刻に影響しない)、
 * あくまで「打刻と日報の時刻が大きくずれていないか」の注意喚起と、労働時間集計の
 * 参考値として使う。
 *
 * timecardのテーブル構成(参照のみ・変更しない):
 *   card  … wid(担当者ID) / yyyymmdd(日付) / cometime(出勤打刻) / byetime(退勤打刻) ほかフラグ
 *   stuff … wid / wname(氏名) ほか表示設定
 *
 * 担当者の対応付けは staff.sid = card.wid で行う(本番実データで一致を確認済み)。
 *
 * 接続が未設定(TIMECARD_DB_DATABASEが空)の環境や、接続に失敗した場合は
 * 「連携なし」として静かに空を返す。タイムカード側の不調で作業日報の画面が
 * 500エラーになってはいけないため。
 */
class TimecardService
{
    /** これ以上ずれていたら注意喚起する分数。 */
    public const DIVERGENCE_THRESHOLD_MINUTES = 30;

    public function isEnabled(): bool
    {
        return (string) config('database.connections.timecard.database') !== '';
    }

    /**
     * 指定期間・指定担当者の打刻を staff_id => [Y-m-d => ['come' => 分, 'bye' => 分]] で返す。
     * 分は0:00からの経過分(作業日報のstart_minute/end_minuteと同じ単位)。
     *
     * @param  Collection<int, Staff>  $staffList
     * @return array<int, array<string, array{come: int|null, bye: int|null}>>
     */
    public function punchesFor(Collection $staffList, Carbon $from, Carbon $to): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        // タイムカードの担当者ID(wid)は manage の SID と同じ値を使う運用
        // (本番で突き合わせ済み。別列での二重管理はしない)。
        $staffByWid = $staffList->whereNotNull('sid')->keyBy('sid');

        if ($staffByWid->isEmpty()) {
            return [];
        }

        try {
            $rows = DB::connection('timecard')->table('card')
                ->select('wid', 'yyyymmdd', 'cometime', 'byetime')
                ->whereIn('wid', $staffByWid->keys()->all())
                ->whereBetween('yyyymmdd', [$from->toDateString(), $to->toDateString()])
                ->get();
        } catch (Throwable $e) {
            Log::warning('タイムカードDBの参照に失敗しました: '.$e->getMessage());

            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $staff = $staffByWid->get($row->wid);
            if (! $staff) {
                continue;
            }

            $result[$staff->id][Carbon::parse($row->yyyymmdd)->format('Y-m-d')] = [
                'come' => $this->toMinutes($row->cometime),
                'bye' => $this->toMinutes($row->byetime),
            ];
        }

        return $result;
    }

    /**
     * タイムカード側の担当者一覧(wid => 氏名)。ＩＤ管理での紐づけ候補表示に使う。
     *
     * @return array<int, string>
     */
    public function staffChoices(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        try {
            return DB::connection('timecard')->table('stuff')
                ->orderBy('odr')
                ->pluck('wname', 'wid')
                ->all();
        } catch (Throwable $e) {
            Log::warning('タイムカードDBの担当者一覧の取得に失敗しました: '.$e->getMessage());

            return [];
        }
    }

    /**
     * 日報の入力内容(休憩・休暇を除く実働の最初と最後)と打刻を比べ、
     * 閾値を超えてずれている場合に日本語の注意文を返す。ずれていなければnull。
     *
     * @param  array{come: int|null, bye: int|null}|null  $punch
     */
    public function divergenceWarning(?array $punch, ?int $reportStartMinute, ?int $reportEndMinute): ?string
    {
        if ($punch === null || ($reportStartMinute === null && $reportEndMinute === null)) {
            return null;
        }

        $messages = [];

        if ($punch['come'] !== null && $reportStartMinute !== null) {
            $diff = abs($punch['come'] - $reportStartMinute);
            if ($diff > self::DIVERGENCE_THRESHOLD_MINUTES) {
                $messages[] = sprintf(
                    '出勤打刻 %s に対し日報の開始が %s（%d分差）',
                    $this->formatMinutes($punch['come']),
                    $this->formatMinutes($reportStartMinute),
                    $diff
                );
            }
        }

        if ($punch['bye'] !== null && $reportEndMinute !== null) {
            $diff = abs($punch['bye'] - $reportEndMinute);
            if ($diff > self::DIVERGENCE_THRESHOLD_MINUTES) {
                $messages[] = sprintf(
                    '退勤打刻 %s に対し日報の終了が %s（%d分差）',
                    $this->formatMinutes($punch['bye']),
                    $this->formatMinutes($reportEndMinute),
                    $diff
                );
            }
        }

        return $messages === [] ? null : implode('／', $messages);
    }

    public function formatMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 打刻のdatetimeを0:00からの経過分に変換する。未打刻(null・ゼロ日付)はnull。
     */
    private function toMinutes(?string $datetime): ?int
    {
        if (! $datetime || str_starts_with($datetime, '0000')) {
            return null;
        }

        try {
            $time = Carbon::parse($datetime);
        } catch (Throwable) {
            return null;
        }

        return $time->hour * 60 + $time->minute;
    }
}
