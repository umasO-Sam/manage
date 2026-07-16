import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // BoardAccent がワークフローごとの色クラスを完全な文字列として保持しているため、
        // Tailwindのスキャン対象にappディレクトリも含める（動的な文字列連結はしていない）。
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Noto Sans JP', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
