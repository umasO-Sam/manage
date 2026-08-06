<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 客先番号 => 客先会社名。過去の注文番号管理台帳の見出し行(例「D,,大幸」)から取り込む。
 *
 * 会社名は取引先一覧(business_partners.customer_code)に同じ客先番号があればそちらを優先する。
 * 取引先一覧は取引条件(銀行・締め日など)を持つ正式な取引先マスタで、こちらは
 * 見積番号を採番するときの名称引き当て用の対応表という位置づけ。
 */
#[Fillable(['code', 'company_name'])]
class CustomerCode extends Model
{
    public function quoteNumbers(): HasMany
    {
        return $this->hasMany(QuoteNumber::class, 'customer_code', 'code');
    }

    public function businessPartner(): HasOne
    {
        return $this->hasOne(BusinessPartner::class, 'customer_code', 'code');
    }

    /** 取引先一覧に登録があればその会社名、無ければ台帳由来の会社名。 */
    public function resolvedCompanyName(): string
    {
        return $this->businessPartner?->name ?? $this->company_name;
    }
}
