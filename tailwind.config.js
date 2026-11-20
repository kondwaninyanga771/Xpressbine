import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.jsx',
    ],
    theme: {
        extend: {
            colors: {
                sand: '#F6F1E9',
                ink: '#0E1B2C',
                zambezi: '#0B6B4F',
                copper: '#E8762D',
                mint: '#2FD39A',
                cloud: '#E7ECEF',
                slate: '#5A6573',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Space Grotesk', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                card: '0 4px 20px -2px rgba(14, 27, 44, 0.05)',
                pop: '0 12px 32px -4px rgba(14, 27, 44, 0.12)',
            },
        },
    },
    plugins: [],
};
