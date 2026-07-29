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
        'name' => '名前',
    ],

];
