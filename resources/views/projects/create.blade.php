<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="building-2" class="text-slate-600 w-6 h-6"></i>
            <span>受注の登録</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('projects.store') }}"
                  class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4"
                  {{-- 注番の「検索」で見積番号台帳に完全一致があれば、件名・受注先・納入先と
                       注番を取得した社内担当者を引っ張ってくる。 --}}
                  x-data="{
                      isNewPartner: {{ old('is_new_partner') ? 'true' : 'false' }},
                      bypassFormat: {{ old('bypass_order_no_format') ? 'true' : 'false' }},
                      showAllStaff: {{ old('show_all_staff') ? 'true' : 'false' }},
                      lookupMessage: null,
                      async lookup() {
                          const no = this.$refs.orderNo.value.trim();
                          if (! no) return;
                          const response = await fetch('{{ route('quote-numbers.lookup') }}?no=' + encodeURIComponent(no), {
                              headers: { 'Accept': 'application/json' },
                          });
                          const data = await response.json();
                          if (! data.found) {
                              this.lookupMessage = { ok: false, text: '見積番号台帳に一致する注番がありません。' };
                              return;
                          }

                          if (data.project_name) this.$refs.productName.value = data.project_name;
                          if (data.delivery_dest) this.$refs.deliveryDest.value = data.delivery_dest;

                          // 受注先は取引先の選択肢から名前で探す。見つからなければ新規取引先として名前を入れる。
                          if (data.recipient) {
                              const select = this.$refs.partnerSelect;
                              const option = Array.from(select.options).find((o) => o.dataset.name === data.recipient);
                              if (option) {
                                  this.isNewPartner = false;
                                  select.value = option.value;
                              } else {
                                  this.isNewPartner = true;
                                  this.$nextTick(() => { this.$refs.newPartnerName.value = data.recipient; });
                              }
                          }

                          // 担当者は全員の一覧に切り替えてから選ぶ(役員・営業担当以外のこともあるため)。
                          if (data.staff_id) {
                              this.showAllStaff = true;
                              this.$nextTick(() => { this.$refs.staffSelect.value = String(data.staff_id); });
                          }

                          this.lookupMessage = { ok: true, text: '「' + (data.project_name || no) + '」を反映しました。' };
                      },
                  }">
                @csrf

                <div>
                    <x-input-label for="order_no" value="注番（必須）" />
                    <div class="mt-1 flex gap-2">
                        <x-text-input id="order_no" name="order_no" type="text" class="block w-full font-mono"
                                      x-ref="orderNo" :value="old('order_no')" required />
                        <button type="button" @click="lookup()"
                                class="shrink-0 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm font-bold hover:bg-slate-50">
                            検索
                        </button>
                    </div>
                    <template x-if="lookupMessage">
                        <p class="mt-1 text-[11px] font-bold" :class="lookupMessage.ok ? 'text-emerald-700' : 'text-amber-600'" x-text="lookupMessage.text"></p>
                    </template>
                    <label class="mt-1 flex items-center gap-1.5 text-xs text-slate-600">
                        <input type="checkbox" name="bypass_order_no_format" value="1" x-model="bypassFormat" @checked(old('bypass_order_no_format'))>
                        形式チェックを解除する
                    </label>
                    <p class="mt-0.5 text-[11px] text-slate-400" x-show="! bypassFormat">
                        「英数1〜8文字」-「英数2〜12文字」の形式。ここで入力した注番が注番管理にも新規登録されます。
                    </p>
                    <x-input-error class="mt-1" :messages="$errors->get('order_no')" />
                </div>

                <div>
                    <x-input-label for="product_name" value="件名（製品名）（必須）" />
                    <x-text-input id="product_name" name="product_name" type="text" class="mt-1 block w-full"
                                  x-ref="productName" :value="old('product_name')" required />
                    <p class="mt-0.5 text-[11px] text-slate-400">注番管理の工事名としても登録されます。</p>
                    <x-input-error class="mt-1" :messages="$errors->get('product_name')" />
                </div>

                <div>
                    <x-input-label value="受注先（必須）" />
                    <label class="mt-1 flex items-center gap-1.5 text-xs text-slate-600">
                        <input type="checkbox" name="is_new_partner" value="1" x-model="isNewPartner" @checked(old('is_new_partner'))>
                        新規取引先（選択肢にない場合）
                    </label>

                    {{-- data-name は検索で引いた受注先名と突き合わせるための素の会社名。 --}}
                    <select name="business_partner_id" x-ref="partnerSelect" x-show="! isNewPartner" :disabled="isNewPartner"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">選択してください</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}" data-name="{{ $partner->name }}" @selected(old('business_partner_id') == $partner->id)>{{ $partner->displayLabel() }}</option>
                        @endforeach
                    </select>

                    <input type="text" name="new_partner_name" x-ref="newPartnerName" x-show="isNewPartner" x-cloak :disabled="! isNewPartner"
                           value="{{ old('new_partner_name') }}" placeholder="新しい受注先の名称"
                           class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <p class="mt-0.5 text-[11px] text-amber-600" x-show="isNewPartner" x-cloak>
                        取引先一覧に仮登録され、資金管理者が取引条件を確定するまで「取引条件調整中」となり請求済へは進めません。
                    </p>
                    <x-input-error class="mt-1" :messages="$errors->get('business_partner_id')" />
                    <x-input-error class="mt-1" :messages="$errors->get('new_partner_name')" />
                </div>

                <div>
                    <x-input-label for="delivery_dest" value="納入先（必須）" />
                    <x-text-input id="delivery_dest" name="delivery_dest" type="text" class="mt-1 block w-full"
                                  x-ref="deliveryDest" :value="old('delivery_dest')" required />
                    <x-input-error class="mt-1" :messages="$errors->get('delivery_dest')" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="受注日（必須）" />
                        <x-date-text-input name="order_received_date" class="mt-1 block w-full" :value="old('order_received_date')" />
                        <x-input-error class="mt-1" :messages="$errors->get('order_received_date')" />
                    </div>
                    <div>
                        <x-input-label for="order_amount" value="受注金額（必須）" />
                        <x-text-input id="order_amount" name="order_amount" type="number" step="1" min="0" class="mt-1 block w-full" :value="old('order_amount')" required />
                        <x-input-error class="mt-1" :messages="$errors->get('order_amount')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="staff_id" value="社内担当者（必須）" />
                    <label class="mt-1 flex items-center gap-1.5 text-xs text-slate-600">
                        <input type="checkbox" name="show_all_staff" value="1" x-model="showAllStaff" @checked(old('show_all_staff'))>
                        表示拡張（役員・営業担当以外も選ぶ）
                    </label>
                    <select id="staff_id" name="staff_id" x-ref="staffSelect" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" required>
                        <option value="">選択してください</option>
                        <template x-if="! showAllStaff">
                            <optgroup label="役員・営業担当">
                                @foreach ($primaryStaff as $person)
                                    <option value="{{ $person->id }}" @selected(old('staff_id') == $person->id)>{{ $person->name }}</option>
                                @endforeach
                            </optgroup>
                        </template>
                        <template x-if="showAllStaff">
                            <optgroup label="全担当者">
                                @foreach ($allStaff as $person)
                                    <option value="{{ $person->id }}" @selected(old('staff_id') == $person->id)>{{ $person->name }}（{{ $person->department }}）</option>
                                @endforeach
                            </optgroup>
                        </template>
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('staff_id')" />
                </div>

                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_direct_delivery_only" value="1" @checked(old('is_direct_delivery_only'))>
                        直送部品のみ（社内の管理方法が変わる目印。進行には影響しません）
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('projects.index') }}" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-bold">戻る</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">登録する</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
