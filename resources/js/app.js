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
 * 仕入管理データの「直接編集」「まとめて削除」(検索・注文書発行・買掛明細書発行画面で共用)。
 * 以前は検索画面(purchasing/index.blade.php)のインラインscriptにしか定義されておらず、
 * 他の画面でx-data="bulkEditor()"を使うと"bulkEditor is not defined"で表示が壊れる不具合が
 * あったため、共通のapp.jsへ移動した。
 */
Alpine.data('bulkEditor', () => ({
    editMode: false,
    showConfirm: false,
    changes: [],
    deleteMode: false,
    selectedIds: [],
    showDeleteConfirm: false,
    deleteTargets: [],

    /** 編集欄(data-original を持つ入力欄)を、フォームの外に置いた分も含めて列挙する。 */
    editableFields() {
        const form = document.getElementById('bulk-edit-form');
        if (! form) return [];

        return Array.from(form.elements).filter((el) => el.dataset.original !== undefined);
    },

    toggleEditMode() {
        if (this.editMode) {
            this.cancelEdit();
            return;
        }

        this.editMode = true;
        this.deleteMode = false;
        this.selectedIds = [];
    },

    /**
     * 編集をやめたら入力欄を元の値へ戻す。画面から隠すだけだと、直したつもりで
     * やめた値がフォームに残ったままになり、次に別の行を直して保存したときに
     * 一緒に書き込まれてしまう。
     */
    cancelEdit() {
        this.editableFields().forEach((el) => {
            if (el.type === 'checkbox') {
                el.checked = el.dataset.original === '1';
            } else {
                el.value = el.dataset.original ?? '';
            }
        });

        this.changes = [];
        this.showConfirm = false;
        this.editMode = false;
    },

    toggleDeleteMode() {
        this.deleteMode = ! this.deleteMode;
        if (this.deleteMode) {
            this.cancelEdit();
        } else {
            this.selectedIds = [];
        }
    },

    toggleSelect(id, checked) {
        if (checked) {
            if (! this.selectedIds.includes(id)) {
                this.selectedIds.push(id);
            }
        } else {
            this.selectedIds = this.selectedIds.filter((existingId) => existingId !== id);
        }
    },

    reviewDelete() {
        if (this.selectedIds.length === 0) return;

        this.deleteTargets = this.selectedIds.map((id) => {
            const checkbox = document.querySelector(`.delete-target-checkbox[data-id="${id}"]`);
            return {
                id,
                itemCode: checkbox?.dataset.rowItemCode ?? '',
                itemName: checkbox?.dataset.rowItemName ?? '',
            };
        });
        this.showDeleteConfirm = true;
    },

    confirmDelete() {
        document.getElementById('bulk-delete-form').submit();
    },

    reviewChanges() {
        const rows = {};

        // 走査は editableFields()(= form.elements)で行う。`#bulk-edit-form [data-original]` のような
        // 子孫セレクタだと、注文書発行・買掛明細書発行のように入力欄がフォームの外にあり
        // form="bulk-edit-form" 属性で紐づけている画面で1件も拾えず、常に「変更はありません。」に
        // なる(送信自体は成立するため気づきにくい)。form.elements は属性で紐づけた要素も含む。
        this.editableFields().forEach((el) => {
            const isCheckbox = el.type === 'checkbox';
            const current = isCheckbox ? (el.checked ? '1' : '0') : el.value;
            const original = el.dataset.original ?? '';
            if (current === original) return;

            const tr = el.closest('tr');
            const id = tr.dataset.rowId;
            if (!rows[id]) {
                rows[id] = { id, itemCode: tr.dataset.rowItemCode, fields: [] };
            }

            let oldValue = original;
            let newValue = current;
            if (isCheckbox) {
                oldValue = original === '1' ? 'はい' : 'いいえ';
                newValue = current === '1' ? 'はい' : 'いいえ';
            } else if (el.tagName === 'SELECT') {
                const originalOption = Array.from(el.options).find((o) => o.value === original);
                oldValue = originalOption ? originalOption.text : '(未設定)';
                newValue = el.options[el.selectedIndex]?.text ?? '(未設定)';
            }

            rows[id].fields.push({
                label: el.dataset.label,
                oldValue: oldValue === '' ? '(空欄)' : oldValue,
                newValue: newValue === '' ? '(空欄)' : newValue,
            });
        });

        this.changes = Object.values(rows);
        if (this.changes.length === 0) {
            alert('変更はありません。');
            return;
        }
        this.showConfirm = true;
    },

    confirmSave() {
        document.getElementById('bulk-edit-form').submit();
    },
}));

/**
 * 一括登録画面の貼り付け欄(仕入管理データ入力・社内人工日報入力で共用)。
 * エクセルからのコピー&ペーストをセル単位のテーブルとして受け取り、
 * 送信時にタブ区切りテキスト(隠しtextarea)へ直列化してサーバーへ渡す。
 * サーバー側のパース処理(タブ区切り・見出し行スキップ)はそのまま流用するため、
 * ここで作る文字列フォーマットは従来の貼り付けテキストと同一にする。
 */
Alpine.data('bulkPasteGrid', (initialText, columns, maxRows = 200) => ({
    columns,
    rows: [],
    maxRows,

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

    /**
     * 上下矢印とEnterで、同じ列の隣の行へ移動する。エクセルを見ながら手で直すときに
     * マウスへ持ち替えずに済むようにするため。移動先は中身を選択した状態にして、
     * エクセルと同じくそのまま打てば上書きになるようにする。
     *
     * 左右矢印には割り当てない。入力欄は常に編集中のため、左右をセル移動にすると
     * 打ち間違いを直すのにカーソルを文字の途中へ戻せなくなる。横移動はTabに任せる。
     *
     * 日本語入力の変換中(isComposing)は何もしない。品名・商社名・メーカーは日本語を
     * 打つ列で、変換候補の選択に上下、確定にEnterを使うため、ここを見落とすと
     * 変換を確定しただけでセルが飛ぶ。
     *
     * 移動先を探すのは $el ではなく $root。イベントハンドラの中の $el は
     * 「発火した要素」＝入力欄そのものを指すため、その中を探しても隣のセルは
     * 見つからない(キーは効くが移動しないという症状になる)。
     */
    moveFocus(event, r, c, delta) {
        if (event.isComposing) {
            return;
        }

        event.preventDefault();

        const target = this.$root.querySelector(`[data-cell="${r + delta}-${c}"]`);
        if (target) {
            target.focus();
            target.select();
        }
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
 *
 * 実際にクリックされたボタン(event.submitter)は、このイベントハンドラの中で
 * 同期的にdisabledにしてしまうと、ブラウザがフォームデータを組み立てる時点で
 * そのボタン自身のname/valueが送信対象から除外されてしまう(disabled要素はフォーム
 * 送信に含まれないため)。休暇・勤務申請の承認/却下のように、複数の送信ボタンを
 * 同じname(例: action)で区別している画面で「操作を入力してください」という
 * 検証エラーになる不具合の原因だったため、クリックされたボタンだけは次のtickまで
 * 無効化を遅らせ、送信データの組み立てに影響しないようにする。
 */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'POST' || event.defaultPrevented) {
        return;
    }

    const disable = (button) => {
        if (button.disabled) return;
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
    };

    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        if (button === event.submitter) return;
        disable(button);
    });

    if (event.submitter) {
        setTimeout(() => disable(event.submitter), 0);
    }
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
