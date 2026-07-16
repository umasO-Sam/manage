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

                <!-- Navigation Links -->
                <div class="hidden md:flex space-x-1 ml-8">
                    @foreach (\App\Models\WorkflowType::orderBy('id')->get() as $nav)
                        <a href="{{ route('cards.index', $nav) }}"
                           class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors {{ request()->route('workflow')?->is($nav) ? $nav->accentClasses()['nav_active'] : 'text-slate-600 hover:bg-slate-50' }}">
                            <i data-lucide="{{ $nav->icon }}" class="w-4 h-4"></i>
                            <span>{{ $nav->name }}ボード</span>
                        </a>
                    @endforeach
                    <a href="{{ route('archive.index') }}"
                       class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors {{ request()->routeIs('archive.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-50' }}">
                        <i data-lucide="archive" class="w-4 h-4"></i>
                        <span>履歴</span>
                    </a>
                    @if (Auth::user()->is_procurement_manager)
                        <a href="{{ route('staff.index') }}"
                           class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors {{ request()->routeIs('staff.*') ? 'bg-slate-100 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">
                            <i data-lucide="users" class="w-4 h-4"></i>
                            <span>担当者管理</span>
                        </a>
                        <a href="{{ route('order-numbers.index') }}"
                           class="px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors {{ request()->routeIs('order-numbers.*') ? 'bg-slate-100 text-blue-600' : 'text-slate-600 hover:bg-slate-50' }}">
                            <i data-lucide="hash" class="w-4 h-4"></i>
                            <span>注番管理</span>
                        </a>
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
            <div class="-me-2 flex items-center md:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-slate-500 hover:bg-slate-100 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden md:hidden border-t border-slate-200">
        <div class="pt-2 pb-3 space-y-1 px-2">
            @foreach (\App\Models\WorkflowType::orderBy('id')->get() as $nav)
                <a href="{{ route('cards.index', $nav) }}" class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->route('workflow')?->is($nav) ? $nav->accentClasses()['nav_active'] : 'text-slate-600' }}">
                    {{ $nav->name }}ボード
                </a>
            @endforeach
            <a href="{{ route('archive.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('archive.*') ? 'bg-slate-200 text-slate-800' : 'text-slate-600' }}">
                履歴
            </a>
            @if (Auth::user()->is_procurement_manager)
                <a href="{{ route('staff.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('staff.*') ? 'bg-slate-100 text-blue-600' : 'text-slate-600' }}">
                    担当者管理
                </a>
                <a href="{{ route('order-numbers.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('order-numbers.*') ? 'bg-slate-100 text-blue-600' : 'text-slate-600' }}">
                    注番管理
                </a>
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
