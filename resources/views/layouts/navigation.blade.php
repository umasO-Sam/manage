<nav x-data="{ open: false }" class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="flex items-center">
                <!-- Logo -->
                <a href="{{ route('cards.index', 'purchase') }}" class="flex items-center gap-3 shrink-0">
                    <div class="p-2 bg-blue-600 rounded-lg text-white">
                        <i data-lucide="package" class="w-5 h-5"></i>
                    </div>
                    <span class="font-bold text-lg tracking-tight text-slate-900 hidden sm:inline">{{ config('app.name') }}</span>
                </a>

                {{-- 横並びメニューはlg(1024px)以上でのみ表示する。md(768px)まで粘ると
                     メニュー項目が右端で見切れるため、見切れる前にバーガーへ切り替える。 --}}
                <!-- Navigation Links -->
                <div class="hidden lg:flex space-x-1 ml-8">
                    @php
                        $viewer = Auth::user();
                        // 権限区分。上長(is_supervisor)はロールとは独立したフラグで、
                        // 経理資材担当と同じく他社員の勤怠・原価をまとめて閲覧できる。
                        $isManager = $viewer->is_procurement_manager;
                        // ＩＤ管理は権限付与のために役員・資金管理者も使う。
                        $canManageStaff = $viewer->canManageStaff();
                        $isSupervisorOrManager = $viewer->isSupervisorOrManager();
                        $isGeneral = $viewer->role === \App\Models\Staff::ROLE_GENERAL && ! $viewer->is_supervisor;

                        $totalUnread = array_sum($unreadCardCountsByWorkflow ?? []);
                        $boardsActive = request()->routeIs('cards.*') || request()->routeIs('archive.*');
                        $attendanceActive = request()->routeIs('my-calendar.*') || request()->routeIs('daily-reports.*')
                            || request()->routeIs('leave-requests.*') || request()->routeIs('work-status.*') || request()->routeIs('holidays.*')
                            || request()->routeIs('operation-logs.*') || request()->routeIs('labor-records.*');
                        $systemActive = request()->routeIs('staff.*') || request()->routeIs('order-numbers.*');
                        $attendancePending = $pendingApprovalsCount + $pendingDailyReportReviewCount;
                    @endphp

                    {{-- 調達ボード: 一般社員は各ボードを直接表示し、それ以外はドロップダウンにまとめる --}}
                    @if ($isGeneral)
                        @php
                            $procurementBoard = \App\Models\WorkflowType::where('name', '購入手配')->first();
                            $estimateBoard = \App\Models\WorkflowType::where('name', '見積依頼')->first();
                        @endphp
                        @if ($procurementBoard)
                            <a href="{{ route('cards.index', $procurementBoard) }}"
                               class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 shrink-0 whitespace-nowrap transition-colors {{ request()->route('workflow')?->is($procurementBoard) ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                                <span>購入手配ボード</span>
                                @if (($unreadCardCountsByWorkflow[$procurementBoard->id] ?? 0) > 0)
                                    <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                                        {{ $unreadCardCountsByWorkflow[$procurementBoard->id] }}
                                    </span>
                                @endif
                            </a>
                        @endif
                        @if ($estimateBoard)
                            <a href="{{ route('cards.index', $estimateBoard) }}"
                               class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 shrink-0 whitespace-nowrap transition-colors {{ request()->route('workflow')?->is($estimateBoard) ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                                <i data-lucide="file-text" class="w-4 h-4"></i>
                                <span>見積依頼ボード</span>
                                @if (($unreadCardCountsByWorkflow[$estimateBoard->id] ?? 0) > 0)
                                    <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                                        {{ $unreadCardCountsByWorkflow[$estimateBoard->id] }}
                                    </span>
                                @endif
                            </a>
                        @endif
                        <a href="{{ route('archive.index') }}"
                           class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 shrink-0 whitespace-nowrap transition-colors {{ request()->routeIs('archive.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                            <i data-lucide="archive" class="w-4 h-4"></i>
                            <span>履歴</span>
                        </a>
                    @else
                        <x-dropdown align="left" width="56">
                            <x-slot name="trigger">
                                <button class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 whitespace-nowrap transition-colors {{ $boardsActive ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                                    <span>調達ボード</span>
                                    @if ($totalUnread > 0)
                                        <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                                            {{ $totalUnread }}
                                        </span>
                                    @endif
                                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                @foreach (\App\Models\WorkflowType::orderBy('id')->get() as $nav)
                                    <x-dropdown-link :href="route('cards.index', $nav)">
                                        <i data-lucide="{{ $nav->icon }}" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> {{ $nav->name }}ボード
                                        @if (($unreadCardCountsByWorkflow[$nav->id] ?? 0) > 0)
                                            <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none align-text-bottom">
                                                {{ $unreadCardCountsByWorkflow[$nav->id] }}
                                            </span>
                                        @endif
                                    </x-dropdown-link>
                                @endforeach
                                <x-dropdown-link :href="route('archive.index')">
                                    <i data-lucide="archive" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 履歴
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    {{-- 物件管理: 経理資材担当・役員・営業担当・資金管理者。
                         物件履歴は調達ボードの「履歴」とは別で、取引先一覧もこの配下に置く。 --}}
                    @if ($viewer->canUseProjectBoard())
                        <x-dropdown align="left" width="56">
                            <x-slot name="trigger">
                                <button class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 whitespace-nowrap transition-colors {{ request()->routeIs('projects.*') || request()->routeIs('business-partners.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <i data-lucide="building-2" class="w-4 h-4"></i>
                                    <span>物件管理</span>
                                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('projects.index')">
                                    <i data-lucide="building-2" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 物件ボード
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('projects.history')">
                                    <i data-lucide="history" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 物件履歴
                                </x-dropdown-link>
                                @if ($viewer->canManageBusinessPartners())
                                    <x-dropdown-link :href="route('business-partners.index')">
                                        <i data-lucide="handshake" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 取引先一覧
                                    </x-dropdown-link>
                                @endif
                            </x-slot>
                        </x-dropdown>
                    @endif

                    {{-- 勤怠管理: 全員が利用でき、項目ごとに権限で出し分ける --}}
                    <x-dropdown align="left" width="56">
                        <x-slot name="trigger">
                            <button class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 whitespace-nowrap transition-colors {{ $attendanceActive ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                                <i data-lucide="calendar-days" class="w-4 h-4"></i>
                                <span>勤怠管理</span>
                                @if ($attendancePending > 0)
                                    <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                                        {{ $attendancePending }}
                                    </span>
                                @endif
                                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('my-calendar.show')">
                                <i data-lucide="calendar" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 個人カレンダー
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('daily-reports.show')">
                                <i data-lucide="clipboard-list" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 作業日報
                            </x-dropdown-link>
                            {{-- 作業日報確認は人工データの確定操作を伴うため経理資材担当のみ --}}
                            @if ($isManager)
                                <x-dropdown-link :href="route('daily-reports.review.index')">
                                    <i data-lucide="clipboard-check" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 作業日報確認
                                    @if ($pendingDailyReportReviewCount > 0)
                                        <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none align-text-bottom">
                                            {{ $pendingDailyReportReviewCount }}
                                        </span>
                                    @endif
                                </x-dropdown-link>
                            @endif
                            @if ($isSupervisorOrManager)
                                <x-dropdown-link :href="route('daily-reports.list.index')">
                                    <i data-lucide="list-checks" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 作業日報一覧
                                </x-dropdown-link>
                            @endif
                            @if ($isManager)
                                <x-dropdown-link :href="route('labor-records.index')">
                                    <i data-lucide="table-2" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 人工レコード
                                </x-dropdown-link>
                            @endif
                            <x-dropdown-link :href="route('leave-requests.index')">
                                <i data-lucide="calendar-check" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 休暇・休出申請
                            </x-dropdown-link>
                            @if ($isSupervisorOrManager)
                                <x-dropdown-link :href="route('leave-requests.approvals')">
                                    <i data-lucide="check-check" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 申請承認
                                    @if ($pendingApprovalsCount > 0)
                                        <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none align-text-bottom">
                                            {{ $pendingApprovalsCount }}
                                        </span>
                                    @endif
                                </x-dropdown-link>
                            @endif
                            <x-dropdown-link :href="route('work-status.index')">
                                <i data-lucide="users-round" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 勤務状況一覧
                            </x-dropdown-link>
                            @if ($isManager)
                                <x-dropdown-link :href="route('holidays.index')">
                                    <i data-lucide="calendar-range" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 休日マスタ
                                </x-dropdown-link>
                            @endif
                            @if ($isSupervisorOrManager)
                                <x-dropdown-link :href="route('operation-logs.index')">
                                    <i data-lucide="scroll-text" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 操作ログ
                                </x-dropdown-link>
                            @endif
                        </x-slot>
                    </x-dropdown>

                    {{-- 仕入管理: 経理資材担当・上長・営業担当 --}}
                    @if ($viewer->canAccessPurchasing())
                        <x-dropdown align="left" width="56">
                            <x-slot name="trigger">
                                <button class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 whitespace-nowrap transition-colors {{ request()->routeIs('purchasing.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <i data-lucide="warehouse" class="w-4 h-4"></i>
                                    <span>仕入管理</span>
                                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                @if ($isManager)
                                    <x-dropdown-link :href="route('purchasing.input')">
                                        <i data-lucide="pencil-line" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> データ入力
                                    </x-dropdown-link>
                                @endif
                                <x-dropdown-link :href="route('purchasing.index')">
                                    <i data-lucide="search" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 検索
                                </x-dropdown-link>
                                @if ($isManager)
                                    <x-dropdown-link :href="route('purchasing.orders.index')">
                                        <i data-lucide="file-text" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 注文書発行
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('purchasing.invoices.index')">
                                        <i data-lucide="receipt" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 明細書発行
                                    </x-dropdown-link>
                                @endif
                                <x-dropdown-link :href="route('purchasing.estimate.index')">
                                    <i data-lucide="calculator" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 見積補助
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('purchasing.cost.index')">
                                    <i data-lucide="bar-chart-3" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 原価計算
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('purchasing.labor.index')">
                                    <i data-lucide="clock" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 人工計算
                                </x-dropdown-link>
                                @if ($isSupervisorOrManager)
                                    <x-dropdown-link :href="route('purchasing.cost-report.index')">
                                        <i data-lucide="table" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 原価一覧
                                    </x-dropdown-link>
                                @endif
                            </x-slot>
                        </x-dropdown>
                    @endif

                    {{-- システム管理: ＩＤ管理は経理資材担当・役員・資金管理者、注番管理は経理資材担当のみ --}}
                    @if ($canManageStaff || $isManager)
                        <x-dropdown align="left" width="56">
                            <x-slot name="trigger">
                                <button class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 whitespace-nowrap transition-colors {{ $systemActive ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                    <span>システム管理</span>
                                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                @if ($canManageStaff)
                                    <x-dropdown-link :href="route('staff.index')">
                                        <i data-lucide="users" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> ＩＤ管理
                                    </x-dropdown-link>
                                @endif
                                @if ($isManager)
                                    <x-dropdown-link :href="route('order-numbers.index')">
                                        <i data-lucide="hash" class="w-3.5 h-3.5 inline-block align-text-bottom mr-1"></i> 注番管理
                                    </x-dropdown-link>
                                @endif
                            </x-slot>
                        </x-dropdown>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-slate-200 text-sm font-medium rounded-lg text-slate-700 bg-white hover:bg-slate-50 focus:outline-none transition ease-in-out duration-150">
                            <i data-lucide="user-circle" class="w-4 h-4 text-slate-400"></i>
                            <div>{{ Auth::user()->name }}</div>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            プロフィール
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                ログアウト
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center lg:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-slate-500 hover:bg-slate-100 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- 展開したメニュー自体を画面高からヘッダー分を引いた高さで区切り、メニュー内で
         直接スクロールできるようにする(背後のページをスクロールしないと下の項目に
         届かない状態を解消するため)。overscroll-containで背後への伝播も止める。 --}}
    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}"
         class="hidden lg:hidden border-t border-slate-200 max-h-[calc(100vh-4rem)] overflow-y-auto overscroll-contain">
        <div class="pt-2 pb-3 space-y-1 px-2">
            {{-- 調達ボード: 一般社員は各ボードを直接表示し、それ以外は見出し付きでまとめる --}}
            @if ($isGeneral)
                @php
                    $procurementBoard ??= \App\Models\WorkflowType::where('name', '購入手配')->first();
                    $estimateBoard ??= \App\Models\WorkflowType::where('name', '見積依頼')->first();
                @endphp
                @if ($procurementBoard)
                    <a href="{{ route('cards.index', $procurementBoard) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->route('workflow')?->is($procurementBoard) ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                        <span>購入手配ボード</span>
                        @if (($unreadCardCountsByWorkflow[$procurementBoard->id] ?? 0) > 0)
                            <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                                {{ $unreadCardCountsByWorkflow[$procurementBoard->id] }}
                            </span>
                        @endif
                    </a>
                @endif
                @if ($estimateBoard)
                    <a href="{{ route('cards.index', $estimateBoard) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->route('workflow')?->is($estimateBoard) ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                        <span>見積依頼ボード</span>
                        @if (($unreadCardCountsByWorkflow[$estimateBoard->id] ?? 0) > 0)
                            <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                                {{ $unreadCardCountsByWorkflow[$estimateBoard->id] }}
                            </span>
                        @endif
                    </a>
                @endif
                <a href="{{ route('archive.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('archive.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    履歴
                </a>
            @else
                <div class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">調達ボード</div>
                @foreach (\App\Models\WorkflowType::orderBy('id')->get() as $nav)
                    <a href="{{ route('cards.index', $nav) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->route('workflow')?->is($nav) ? $nav->accentClasses()['nav_active'] : 'text-slate-600' }}">
                        <span>{{ $nav->name }}ボード</span>
                        @if (($unreadCardCountsByWorkflow[$nav->id] ?? 0) > 0)
                            <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                                {{ $unreadCardCountsByWorkflow[$nav->id] }}
                            </span>
                        @endif
                    </a>
                @endforeach
                <a href="{{ route('archive.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('archive.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    履歴
                </a>
            @endif

            @if ($viewer->canUseProjectBoard())
                <div class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">物件管理</div>
                <a href="{{ route('projects.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('projects.index') || request()->routeIs('projects.show') || request()->routeIs('projects.create') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    物件ボード
                </a>
                <a href="{{ route('projects.history') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('projects.history') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    物件履歴
                </a>
                @if ($viewer->canManageBusinessPartners())
                    <a href="{{ route('business-partners.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('business-partners.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                        取引先一覧
                    </a>
                @endif
            @endif

            {{-- 勤怠管理: 全員 --}}
            <div class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">勤怠管理</div>
            <a href="{{ route('my-calendar.show') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('my-calendar.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                個人カレンダー
            </a>
            <a href="{{ route('daily-reports.show') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('daily-reports.show') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                作業日報
            </a>
            @if ($isManager)
                <a href="{{ route('daily-reports.review.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('daily-reports.review.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    <span>作業日報確認</span>
                    @if ($pendingDailyReportReviewCount > 0)
                        <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                            {{ $pendingDailyReportReviewCount }}
                        </span>
                    @endif
                </a>
            @endif
            @if ($isSupervisorOrManager)
                <a href="{{ route('daily-reports.list.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('daily-reports.list.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    作業日報一覧
                </a>
            @endif
            @if ($isManager)
                <a href="{{ route('labor-records.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('labor-records.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    人工レコード
                </a>
            @endif
            <a href="{{ route('leave-requests.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('leave-requests.index') || request()->routeIs('leave-requests.create') || request()->routeIs('leave-requests.show') || request()->routeIs('leave-requests.withdraw') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                休暇・休出申請
            </a>
            @if ($isSupervisorOrManager)
                <a href="{{ route('leave-requests.approvals') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('leave-requests.approvals') || request()->routeIs('leave-requests.decide') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    <span>申請承認</span>
                    @if ($pendingApprovalsCount > 0)
                        <span class="inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                            {{ $pendingApprovalsCount }}
                        </span>
                    @endif
                </a>
            @endif
            <a href="{{ route('work-status.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('work-status.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                勤務状況一覧
            </a>
            @if ($isManager)
                <a href="{{ route('holidays.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('holidays.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    休日マスタ
                </a>
            @endif
            @if ($isSupervisorOrManager)
                <a href="{{ route('operation-logs.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('operation-logs.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    操作ログ
                </a>
            @endif

            {{-- 仕入管理: 経理資材担当・上長・営業担当 --}}
            @if ($viewer->canAccessPurchasing())
                <div class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">仕入管理</div>
                @if ($isManager)
                    <a href="{{ route('purchasing.input') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('purchasing.input') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                        データ入力
                    </a>
                @endif
                <a href="{{ route('purchasing.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('purchasing.index') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    検索
                </a>
                @if ($isManager)
                    <a href="{{ route('purchasing.orders.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('purchasing.orders.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                        注文書発行
                    </a>
                    <a href="{{ route('purchasing.invoices.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('purchasing.invoices.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                        明細書発行
                    </a>
                @endif
                <a href="{{ route('purchasing.estimate.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('purchasing.estimate.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    見積補助
                </a>
                <a href="{{ route('purchasing.cost.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('purchasing.cost.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    原価計算
                </a>
                <a href="{{ route('purchasing.labor.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('purchasing.labor.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                    人工計算
                </a>
                @if ($isSupervisorOrManager)
                    <a href="{{ route('purchasing.cost-report.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('purchasing.cost-report.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                        原価一覧
                    </a>
                @endif
            @endif

            {{-- システム管理: ＩＤ管理は経理資材担当・役員・資金管理者、注番管理は経理資材担当のみ --}}
            @if ($canManageStaff || $isManager)
                <div class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">システム管理</div>
                @if ($canManageStaff)
                    <a href="{{ route('staff.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('staff.*') ? 'bg-slate-100 text-blue-600' : 'text-slate-600' }}">
                        ＩＤ管理
                    </a>
                @endif
                @if ($isManager)
                    <a href="{{ route('order-numbers.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->routeIs('order-numbers.*') ? 'bg-slate-100 text-blue-600' : 'text-slate-600' }}">
                        注番管理
                    </a>
                @endif
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-3 border-t border-slate-200">
            <div class="px-4">
                <div class="font-medium text-base text-slate-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-slate-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1 px-2">
                <x-responsive-nav-link :href="route('profile.edit')">
                    プロフィール
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        ログアウト
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
