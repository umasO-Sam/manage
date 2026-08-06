<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="hash" class="w-5 h-5 text-blue-600"></i>
            <span>注番を追加</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-base">新規注番</h3>
                </div>
                {{-- 見積番号台帳に完全一致する注番があれば、工事名を引っ張ってくる。 --}}
                <form method="POST" action="{{ route('order-numbers.store') }}" class="p-6 space-y-5"
                      x-data="{
                          bypass: {{ old('bypass_format_check') ? 'true' : 'false' }},
                          lookupMessage: null,
                          async lookup() {
                              const code = this.$refs.code.value.trim();
                              if (! code) return;
                              const response = await fetch('{{ route('quote-numbers.lookup') }}?no=' + encodeURIComponent(code), {
                                  headers: { 'Accept': 'application/json' },
                              });
                              const data = await response.json();
                              if (! data.found) {
                                  this.lookupMessage = { ok: false, text: '見積番号台帳に一致する注番がありません。' };
                                  return;
                              }
                              if (data.project_name) this.$refs.projectName.value = data.project_name;
                              this.lookupMessage = { ok: true, text: '「' + (data.project_name || '（工事名なし）') + '」を反映しました。' };
                          },
                      }">
                    @csrf
                    <div>
                        <x-input-label for="code" value="注番" />
                        <div class="mt-1 flex gap-2">
                            <x-text-input id="code" name="code" type="text" class="block w-full"
                                x-ref="code"
                                :value="old('code')" required autofocus
                                x-bind:class="bypass ? '' : 'font-mono'"
                                x-bind:placeholder="bypass ? '例: 〇〇工事現場支給品' : '例: ZZ999-N99T99'" />
                            <button type="button" @click="lookup()"
                                    class="shrink-0 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm font-bold hover:bg-slate-50">
                                検索
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400" x-show="! bypass">英数1〜8文字 - 英数2〜12文字</p>
                        <p class="mt-1 text-[11px] text-amber-600" x-show="bypass" x-cloak>形式チェックを解除しています。日本語を含む自由な文字列で登録できます。</p>
                        <template x-if="lookupMessage">
                            <p class="mt-1 text-[11px] font-bold" :class="lookupMessage.ok ? 'text-emerald-700' : 'text-amber-600'" x-text="lookupMessage.text"></p>
                        </template>
                        <x-input-error class="mt-2" :messages="$errors->get('code')" />
                    </div>

                    <div>
                        <x-input-label for="project_name" value="工事名（任意）" />
                        <x-text-input id="project_name" name="project_name" type="text" class="mt-1 block w-full"
                            x-ref="projectName" :value="old('project_name')" />
                        <x-input-error class="mt-2" :messages="$errors->get('project_name')" />
                    </div>

                    <div class="flex items-start gap-2 bg-amber-50 border border-amber-100 rounded-lg p-3">
                        <input id="bypass_format_check" name="bypass_format_check" type="checkbox" value="1"
                            class="mt-0.5 rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500"
                            x-model="bypass"
                            @checked(old('bypass_format_check')) />
                        <label for="bypass_format_check" class="text-xs text-slate-700">
                            <span class="font-semibold">形式チェックを解除する</span><br>
                            通常のフォーマットに合わない注番（日本語表記など）を登録する場合にチェックしてください。
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <a href="{{ route('order-numbers.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            キャンセル
                        </a>
                        <x-primary-button>登録する</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
