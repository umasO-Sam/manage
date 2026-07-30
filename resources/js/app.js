import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

window.Alpine = Alpine;

/**
 * lucideのアイコンはDOMContentLoaded時に一括変換されるため、Alpineのx-forなどで
 * 実行時に追加された要素のdata-lucide属性は自動では変換されない。そうした箇所は
 * 追加・削除のたびにこれを呼び直してアイコンを反映させる。
 */
window.refreshIcons = () => createIcons({ icons });

/**
 * 仕入管理データ入力「エクセル一括登録」の貼り付け欄。
 * エクセルからのコピー&ペーストをセル単位のテーブルとして受け取り、
 * 送信時にタブ区切りテキスト(隠しtextarea `paste_data`)へ直列化してサーバーへ渡す。
 * サーバー側のパース処理(タブ区切り・見出し行スキップ)はそのまま流用するため、
 * ここで作る文字列フォーマットは従来の貼り付けテキストと同一にする。
 */
Alpine.data('bulkPasteGrid', (initialText) => ({
    columns: ['品名', '機械装置No', '分類', '型式', '数量', '単価', '商社名', 'メーカー'],
    rows: [],

    maxRows: 200,

    init() {
        this.rows = this.parseText(initialText);
        this.ensureRows(this.maxRows);
    },

    emptyRow() {
        return this.columns.map(() => '');
    },

    parseText(text) {
        const lines = text.split(/\r\n|\r|\n/).filter((line) => line.trim() !== '');

        return lines.map((line) => {
            const cols = line.split('\t');

            return this.columns.map((_, i) => (cols[i] ?? '').trim());
        });
    },

    ensureRows(min) {
        while (this.rows.length < min) {
            this.rows.push(this.emptyRow());
        }
    },

    handlePaste(event, r, c) {
        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData).getData('text');
        let lines = text.split(/\r\n|\r|\n/);
        while (lines.length > 1 && lines[lines.length - 1] === '') {
            lines.pop();
        }

        lines.forEach((line, li) => {
            const targetRow = r + li;
            this.ensureRows(targetRow + 1);
            const cols = line.split('\t');
            cols.forEach((value, ci) => {
                const targetCol = c + ci;
                if (targetCol < this.columns.length) {
                    this.rows[targetRow][targetCol] = value.trim();
                }
            });
        });
    },

    serialized() {
        return this.rows
            .filter((row) => row.some((value) => value !== ''))
            .map((row) => row.join('\t'))
            .join('\n');
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => createIcons({ icons }));

/**
 * 二重送信防止。ダブルクリックや連打で依頼・コメント・注番等が
 * 重複登録されるのを防ぐため、送信ボタンを送信直後に無効化する。
 * サーバー側の検証エラーで同じページが再描画されればボタンは元に戻る。
 *
 * onsubmit="return confirm(...)" でキャンセルされた場合、submitイベント自体は
 * preventDefault()された状態でdocumentまでバブリングしてくる（実際には送信されない）。
 * defaultPreventedを見ずに無効化すると、キャンセルしただけでボタンが操作不能のまま
 * 固まってしまうため、ここで除外する。
 */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'POST' || event.defaultPrevented) {
        return;
    }

    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        if (button.disabled) return;
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
    });
});

/**
 * パスワードポリシー(20文字以上・大文字小文字・数字を必須)を必ず満たす安全な
 * パスワードを暗号学的乱数(crypto.getRandomValues)で生成するボタン。
 * data-generate-password="対象input要素のid" を指定した<button>から使う。
 * 紛らわしい文字(0/O、1/l/I)は誤読・誤入力を防ぐため文字集合から除外している。
 */
function generateSecurePassword(length = 20) {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnpqrstuvwxyz';
    const digits = '23456789';
    const symbols = '!@#$%^&*-_=+';
    const all = upper + lower + digits + symbols;

    const randomIndex = (max) => {
        const buffer = new Uint32Array(1);
        window.crypto.getRandomValues(buffer);
        return buffer[0] % max;
    };

    let password;
    do {
        password = Array.from({ length }, () => all[randomIndex(all.length)]).join('');
    } while (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password));

    return password;
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-generate-password]');
    if (!button) return;

    event.preventDefault();

    const password = generateSecurePassword(20);
    const targetIds = button.dataset.generatePassword.split(',').map((id) => id.trim());

    targetIds.forEach((id) => {
        const field = document.getElementById(id);
        if (!field) return;
        field.type = 'text';
        field.value = password;
        field.dispatchEvent(new Event('input', { bubbles: true }));
    });
});
