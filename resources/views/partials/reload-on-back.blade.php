{{--
    ブラウザの「戻る」で復元されたページを読み込み直す。

    戻ったときのページはブラウザのキャッシュ(bfcache)から復元されるため、
    その間に変わった内容が反映されない。カードを進めたあとに戻ると、
    すでに動いたカードが元の枠に残って見えてしまい、そこからもう一度
    操作すると意図しないステージへ進んでしまう。

    入力途中の内容を失わないよう、操作が中心の画面(ボードなど)にだけ入れる。
--}}
<script>
    window.addEventListener('pageshow', (event) => {
        // persisted はbfcacheから復元されたときだけ真になる。
        // 一部のブラウザは persisted を立てないため、ナビゲーションの種別でも判定する。
        const navigatedBack = window.performance
            ?.getEntriesByType?.('navigation')?.[0]?.type === 'back_forward';

        if (event.persisted || navigatedBack) {
            window.location.reload();
        }
    });
</script>
