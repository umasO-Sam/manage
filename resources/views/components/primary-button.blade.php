<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-5 py-2 bg-blue-600 border border-transparent rounded-xl font-semibold text-sm text-white shadow-sm hover:bg-blue-700 hover:shadow focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all']) }}>
    {{ $slot }}
</button>
