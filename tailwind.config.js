import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                ori: {
                    white: '#FFFFFF',
                    blue: {
                        50: '#EAF1FB',
                        100: '#D5E3F7',
                        200: '#A9C5EE',
                        300: '#6F98D8',
                        400: '#3B6FBC',
                        500: '#1E4D8C',
                        600: '#173F74',
                        700: '#12345F',
                        800: '#0C2748',
                        900: '#081A31',
                    },
                    yellow: {
                        50: '#FFF9E6',
                        100: '#FFF0BF',
                        200: '#FFE08A',
                        300: '#FFCF4D',
                        400: '#F5C518',
                        500: '#E6B800',
                        600: '#C99A00',
                    },
                },
            },
            fontFamily: {
                sans: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
                display: ['"Fraunces"', ...defaultTheme.fontFamily.serif],
            },
            boxShadow: {
                soft: '0 10px 30px -12px rgba(18, 52, 95, 0.25)',
            },
            backgroundImage: {
                'ori-hero':
                    'radial-gradient(circle at top right, rgba(245, 197, 24, 0.25), transparent 35%), linear-gradient(135deg, #081A31 0%, #1E4D8C 48%, #12345F 100%)',
                'ori-panel':
                    'linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(234,241,251,0.9) 100%)',
            },
        },
    },

    plugins: [forms],
};
