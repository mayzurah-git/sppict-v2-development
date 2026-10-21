import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './vendor/livewire/livewire/src/Livewire/features/SupportFileUploads/TemporaryUploadedFile.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Identiti SPPICT: hijau gelap (panel/bar navigasi) dan latar halaman.
                brand: {
                    dark: '#0c2a1f',
                    soft: '#d1e7dc',
                    muted: '#b5d3c4',
                    canvas: '#f4f6f5',
                },
            },
        },
    },

    plugins: [forms],
};
