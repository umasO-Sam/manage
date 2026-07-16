<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">担当者一覧管理</h2>
            <a href="{{ route('staff.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                担当者を追加
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('status') === 'staff-created')
                <div class="mb-4 p-3 rounded-md bg-green-50 text-green-800 text-sm">担当者を登録しました。</div>
            @endif
            @if (session('status') === 'staff-updated')
                <div class="mb-4 p-3 rounded-md bg-green-50 text-green-800 text-sm">担当者情報を更新しました。</div>
            @endif

            <div class="bg-white shadow sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">氏名</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">部署</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">ログインID</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">メールアドレス</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">資材管理担当</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($staffList as $staff)
                            <tr>
                                <td class="px-4 py-2 text-gray-800">{{ $staff->name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $staff->department }}</td>
                                <td class="px-4 py-2 font-mono text-gray-600">{{ $staff->login_id }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $staff->email }}</td>
                                <td class="px-4 py-2">
                                    @if ($staff->is_procurement_manager)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">担当</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('staff.edit', $staff) }}" class="text-teal-700 hover:underline">編集</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
