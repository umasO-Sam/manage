<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | フレームワーク組み込みの英語版(vendor/laravel/framework/.../lang/en/validation.php)を
    | 日本語化したもの。APP_LOCALE=jaのため、このファイルがあれば標準バリデーションルールの
    | エラーメッセージが日本語で表示される。各フォームで個別に->messages()を指定している箇所は
    | そちらが優先されるため、ここでは主にPassword::defaults()等アプリ側でカスタマイズしていない
    | 標準ルールのフォールバック文言を担う。
    |
    */

    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認してください。',
    'active_url' => ':attributeは有効なURLではありません。',
    'after' => ':attributeには:date以降の日付を指定してください。',
    'after_or_equal' => ':attributeには:date以降の日付を指定してください。',
    'alpha' => ':attributeは英字のみ使用できます。',
    'alpha_dash' => ':attributeは英数字とダッシュ(-)、アンダースコア(_)のみ使用できます。',
    'alpha_num' => ':attributeは英数字のみ使用できます。',
    'any_of' => ':attributeの値が正しくありません。',
    'array' => ':attributeは配列で指定してください。',
    'ascii' => ':attributeは半角英数字と記号のみ使用できます。',
    'before' => ':attributeには:date以前の日付を指定してください。',
    'before_or_equal' => ':attributeには:date以前の日付を指定してください。',
    'between' => [
        'array' => ':attributeは:min個から:max個の間で指定してください。',
        'file' => ':attributeは:minKBから:maxKBの間で指定してください。',
        'numeric' => ':attributeは:minから:maxの間で指定してください。',
        'string' => ':attributeは:min文字から:max文字の間で指定してください。',
    ],
    'boolean' => ':attributeはtrueかfalseを指定してください。',
    'can' => ':attributeに許可されていない値が含まれています。',
    'confirmed' => ':attributeが一致しません。',
    'contains' => ':attributeに必須の値が含まれていません。',
    'current_password' => 'パスワードが間違っています。',
    'date' => ':attributeには有効な日付を指定してください。',
    'date_equals' => ':attributeには:dateと同じ日付を指定してください。',
    'date_format' => ':attributeは:format形式で指定してください。',
    'decimal' => ':attributeは小数点以下:decimal桁で指定してください。',
    'declined' => ':attributeを拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否してください。',
    'different' => ':attributeと:otherには異なる値を指定してください。',
    'digits' => ':attributeは:digits桁で指定してください。',
    'digits_between' => ':attributeは:min桁から:max桁の間で指定してください。',
    'dimensions' => ':attributeの画像サイズが正しくありません。',
    'distinct' => ':attributeに重複した値があります。',
    'doesnt_contain' => ':attributeに次の値を含めないでください: :values',
    'doesnt_end_with' => ':attributeを次のいずれかで終わらせないでください: :values',
    'doesnt_start_with' => ':attributeを次のいずれかで始めないでください: :values',
    'email' => ':attributeには正しい形式のメールアドレスを指定してください。',
    'encoding' => ':attributeは:encoding形式でエンコードしてください。',
    'ends_with' => ':attributeは次のいずれかで終わる必要があります: :values',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは存在しません。',
    'extensions' => ':attributeは次のいずれかの拡張子である必要があります: :values',
    'file' => ':attributeにはファイルを指定してください。',
    'filled' => ':attributeに値を入力してください。',
    'gt' => [
        'array' => ':attributeは:value個より多く指定してください。',
        'file' => ':attributeは:valueKBより大きいサイズを指定してください。',
        'numeric' => ':attributeは:valueより大きい値を指定してください。',
        'string' => ':attributeは:value文字より多く指定してください。',
    ],
    'gte' => [
        'array' => ':attributeは:value個以上指定してください。',
        'file' => ':attributeは:valueKB以上のサイズを指定してください。',
        'numeric' => ':attributeは:value以上の値を指定してください。',
        'string' => ':attributeは:value文字以上で指定してください。',
    ],
    'hex_color' => ':attributeには正しいカラーコードを指定してください。',
    'image' => ':attributeには画像ファイルを指定してください。',
    'in' => '選択された:attributeは正しくありません。',
    'in_array' => ':attributeが:otherに存在しません。',
    'in_array_keys' => ':attributeには次のキーのうち少なくとも1つを含めてください: :values',
    'integer' => ':attributeは整数で指定してください。',
    'ip' => ':attributeには正しいIPアドレスを指定してください。',
    'ipv4' => ':attributeには正しいIPv4アドレスを指定してください。',
    'ipv6' => ':attributeには正しいIPv6アドレスを指定してください。',
    'json' => ':attributeには正しいJSON文字列を指定してください。',
    'list' => ':attributeはリスト形式で指定してください。',
    'lowercase' => ':attributeは小文字で指定してください。',
    'lt' => [
        'array' => ':attributeは:value個より少なく指定してください。',
        'file' => ':attributeは:valueKBより小さいサイズを指定してください。',
        'numeric' => ':attributeは:valueより小さい値を指定してください。',
        'string' => ':attributeは:value文字より少なく指定してください。',
    ],
    'lte' => [
        'array' => ':attributeは:value個以下で指定してください。',
        'file' => ':attributeは:valueKB以下のサイズを指定してください。',
        'numeric' => ':attributeは:value以下の値を指定してください。',
        'string' => ':attributeは:value文字以下で指定してください。',
    ],
    'mac_address' => ':attributeには正しいMACアドレスを指定してください。',
    'max' => [
        'array' => ':attributeは:max個以下にしてください。',
        'file' => ':attributeは:maxKB以下のサイズにしてください。',
        'numeric' => ':attributeは:max以下の値にしてください。',
        'string' => ':attributeは:max文字以下で指定してください。',
    ],
    'max_digits' => ':attributeは:max桁以下で指定してください。',
    'mimes' => ':attributeには:valuesタイプのファイルを指定してください。',
    'mimetypes' => ':attributeには:valuesタイプのファイルを指定してください。',
    'min' => [
        'array' => ':attributeは:min個以上指定してください。',
        'file' => ':attributeは:minKB以上のサイズを指定してください。',
        'numeric' => ':attributeは:min以上の値を指定してください。',
        'string' => ':attributeは:min文字以上で指定してください。',
    ],
    'min_digits' => ':attributeは:min桁以上で指定してください。',
    'missing' => ':attributeは存在しない必要があります。',
    'missing_if' => ':otherが:valueの場合、:attributeは存在しない必要があります。',
    'missing_unless' => ':otherが:valueでない限り、:attributeは存在しない必要があります。',
    'missing_with' => ':valuesが存在する場合、:attributeは存在しない必要があります。',
    'missing_with_all' => ':valuesが全て存在する場合、:attributeは存在しない必要があります。',
    'multiple_of' => ':attributeは:valueの倍数で指定してください。',
    'not_in' => '選択された:attributeは正しくありません。',
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => ':attributeは数値で指定してください。',
    'password' => [
        'letters' => ':attributeには英字を1文字以上含めてください。',
        'mixed' => ':attributeには大文字と小文字をそれぞれ1文字以上含めてください。',
        'numbers' => ':attributeには数字を1文字以上含めてください。',
        'symbols' => ':attributeには記号を1文字以上含めてください。',
        'uncompromised' => '入力された:attributeは漏えいしたパスワードの一覧に含まれています。別の:attributeを指定してください。',
    ],
    'present' => ':attributeが存在しません。',
    'present_if' => ':otherが:valueの場合、:attributeが存在する必要があります。',
    'present_unless' => ':otherが:valueでない限り、:attributeが存在する必要があります。',
    'present_with' => ':valuesが存在する場合、:attributeが存在する必要があります。',
    'present_with_all' => ':valuesが全て存在する場合、:attributeが存在する必要があります。',
    'prohibited' => ':attributeは許可されていません。',
    'prohibited_if' => ':otherが:valueの場合、:attributeは許可されていません。',
    'prohibited_if_accepted' => ':otherが承認されている場合、:attributeは許可されていません。',
    'prohibited_if_declined' => ':otherが拒否されている場合、:attributeは許可されていません。',
    'prohibited_unless' => ':otherが:values内にない限り、:attributeは許可されていません。',
    'prohibits' => ':attributeが存在する場合、:otherは指定できません。',
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeを入力してください。',
    'required_array_keys' => ':attributeには次のキーを含めてください: :values',
    'required_if' => ':otherが:valueの場合、:attributeを入力してください。',
    'required_if_accepted' => ':otherが承認されている場合、:attributeを入力してください。',
    'required_if_declined' => ':otherが拒否されている場合、:attributeを入力してください。',
    'required_unless' => ':otherが:values内にない場合、:attributeを入力してください。',
    'required_with' => ':valuesが指定されている場合、:attributeを入力してください。',
    'required_with_all' => ':valuesが全て指定されている場合、:attributeを入力してください。',
    'required_without' => ':valuesが指定されていない場合、:attributeを入力してください。',
    'required_without_all' => ':valuesが全て指定されていない場合、:attributeを入力してください。',
    'same' => ':attributeと:otherは同じ値を指定してください。',
    'size' => [
        'array' => ':attributeは:size個指定してください。',
        'file' => ':attributeのサイズは:sizeKBにしてください。',
        'numeric' => ':attributeは:sizeを指定してください。',
        'string' => ':attributeは:size文字で指定してください。',
    ],
    'starts_with' => ':attributeは次のいずれかで始まる必要があります: :values',
    'string' => ':attributeは文字列で指定してください。',
    'timezone' => ':attributeには正しいタイムゾーンを指定してください。',
    'unique' => ':attributeはすでに使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字で指定してください。',
    'url' => ':attributeには正しいURLを指定してください。',
    'ulid' => ':attributeには正しいULIDを指定してください。',
    'uuid' => ':attributeには正しいUUIDを指定してください。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認）',
        'current_password' => '現在のパスワード',
        'email' => 'メールアドレス',
        'name' => '氏名',

        // 担当者管理
        'department' => '部署',
        'login_id' => 'ログインID',
        'role' => '権限',

        // 注番管理
        'code' => '注番',

        // カード(購入部品手配・見積り依頼)
        'order_number_id' => '注番',
        'machine_number' => '機械装置番号',
        'model_number' => '型式',
        'quantity' => '数量',
        'due_date_type' => '希望納期の種別',
        'due_date' => '希望納期',

        // 仕入管理データ入力・編集・検索(purchase_details)
        'item_code' => '注番',
        'machine_no' => '機械装置No',
        'product_name' => '製品名',
        'category_id' => '分類',
        'manufacturer' => 'メーカー',
        'item_name' => '品名',
        'dimensions' => '形式/寸法',
        'remarks' => '備考',
        'required_qty' => '必要数量',
        'usage_purpose' => '使用用途',
        'order_qty' => '数量',
        'unit' => '単位',
        'unit_price' => '単価',
        'stock_qty' => '在庫',
        'supplier_name' => '商社名',
        'order_date' => '注文日',
        'arrival_date' => '受入日',
        'invoice_date' => '納品書日',
        'recipient' => '受注先',
        'order_received_date' => '受注日',
        'delivery_dest' => '納入先',
        'order_amount' => '受注金額',
        'sales_date' => '売上日',
        'supplier_invoice_no' => '商社納品書No',
        'paste_data' => '貼り付け欄',

        // 人工計算・日報入力(labor_costs)
        'work_date' => '作業日',
        'staff_id' => '担当者',
        'order_no' => '注番',
        'labor_machine_no' => '機械装置No',
        'labor_category_id' => '作業分類',
        'work_hours' => '時間',
        'work_minutes' => '分',
        'note' => '補足',

        // カードコメント
        'body' => 'コメント内容',

        // カードの添付ファイル
        'attachments' => '添付ファイル',
        'attachments.*' => '添付ファイル',
        'remove_attachments' => '削除する添付ファイル',
        'remove_attachments.*' => '削除する添付ファイル',

        // 注番管理
        'project_name' => '工事名',

        // 担当者管理
        'is_supervisor' => '上長',
        'paid_leave_granted_current_year' => '有給休暇 当年度付与日数',
        'paid_leave_granted_last_year' => '有給休暇 前年度繰越日数',

        // 作業日報(daily_reports/daily_report_entries)
        'entries' => '入力内容',
        'start_minute' => '開始時刻',
        'end_minute' => '終了時刻',
        'is_other' => 'その他',
        'free_text' => '自由記入',
        'is_break' => '休憩',

        // 休暇・勤務申請(leave_requests)
        'type' => '申請種別',
        'approver_id' => '承認者',
        'target_staff_id' => '代理で申請する対象者',
        'start_date' => '開始日',
        'end_date' => '終了日',
        'granularity' => '粒度',
        'reason_code' => '事由',
        'reason_detail' => '事由の詳細',
        'work_location' => '勤務地',
        'substitute_holiday_date' => '振替休日とする日',
        'no_substitute_needed' => '振り替えない',
        'actual_worked_hours' => '実際に勤務した時間',
        'compensatory_date' => '代休日',
        'funeral_venue_address' => '葬儀場住所',
        'funeral_venue_phone' => '葬儀場電話番号',
        'wake_datetime' => '通夜',
        'funeral_datetime' => '葬儀',
        'flowers_declined' => '花の辞退',
        'telegram_declined' => '電報の辞退',
        'action' => '操作',
        'rejection_reason' => '却下理由',
        'half_day_period' => '午前／午後',
        'cancel_reason' => '取消の理由',
        'amend_reason' => '変更の理由',
        'cancel_rejection_reason' => '差し戻しの理由',

        // 作業日報の1コマごとの項目。「entries.3.order_no」のような添字付きでも
        // 拾えるよう、ワイルドカードの形でも定義しておく。
        'entries.*.start_minute' => '開始時刻',
        'entries.*.end_minute' => '終了時刻',
        'entries.*.order_no' => '注番',
        'entries.*.category_id' => '作業分類',
        'entries.*.is_other' => 'その他',
        'entries.*.free_text' => '自由記入',
        'entries.*.is_break' => '休憩',
        'entries.*.is_leave' => '休暇',
        'entries.*.leave_type' => '休暇の種類',

        // 人工計算・仕入管理のデータ入力(続き)
        'is_overtime' => '時間外',
        'labor_paste_data' => '貼り付け欄',
        'ids' => '対象の選択',
        'ids.*' => '対象の選択',

        // 見積番号の採番
        'customer_code' => '客先番号',
        'mode' => '案件種別',
        'unit_no' => '通番',
        'base_no' => '元の見積番号',
        'full_no' => '注番',
        'company_name' => '客先会社名',
        'customer_contact' => '客先担当者',
        'completed_on' => '完了日',
        'note_no' => 'ノートNo',

        // 物件管理・受注登録
        'business_partner_id' => '受注先',
        'new_partner_name' => '新規取引先名',
        'is_direct_delivery_only' => '直送のみ',
        'invoice_confirmed' => '請求書の確認',
        'file' => '添付ファイル',

        // 取引先一覧
        'transaction_type' => '取引区分',
        'closing_day' => '締日',
        'payment_terms' => '支払条件',
        'bank' => '振込先',

        // 注文書・明細書
        'staff_name' => '担当者名',
        'staff_phone' => '担当者電話番号',
        'target_ids' => '対象の選択',
        'target_ids.*' => '対象の選択',
        'date_type' => '日付の種類',
        'date_from' => '開始日',
        'date_to' => '終了日',

        // 注番管理
        'show_in_dropdown' => 'プルダウンに表示',

        // 休日マスタ
        'date' => '日付',

        // 担当者管理・権限
        'sid' => 'SID',
        'display_order' => '表示順',
        'excluded_from_rosters' => '名簿に表示しない',
        'is_daily_report_reviewer' => '日報管理者',
        'is_attendance_manager' => '勤怠管理者',
        'is_executive' => '役員',
        'is_fund_manager' => '資金管理者',
        'is_administrator' => 'administrator',
    ],

];
