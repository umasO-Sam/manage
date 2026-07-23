import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

window.Alpine = Alpine;

/**
 * lucideのアイコンはDOMContentLoaded時に一括変換されるため、Alpineのx-forなどで
 * 実行時に追加された要素のdata-lucide属性は自動では変換されない。そうした箇所は
 * 追加・削除のたびにこれを呼び直してアイコンを反映させる。
 */
window.refreshIcons = () => createIcons({ icons });

Alpine.start();

document.addEventListener('DOMContentLoaded', () => createIcons({ icons }));

/**
 * 二重送信防止。ダブルクリックや連打で依頼・コメント・注番等が
 * 重複登録されるのを防ぐため、送信ボタンを送信直後に無効化する。
 * サーバー側の検証エラーで同じページが再描画されればボタンは元に戻る。
 */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'POST') {
        return;
    }

    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        if (button.disabled) return;
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
    });
});
