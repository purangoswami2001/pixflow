import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './app/View/**/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './public/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                primary: '#004ac6',
                'primary-container': '#2563eb',
                'on-primary': '#ffffff',
                'on-surface': '#131b2e',
                'on-surface-variant': '#434655',
                outline: '#737686',
                'outline-variant': '#c3c6d7',
                'surface-container-low': '#f2f3ff',
                'surface-container-lowest': '#ffffff',
                error: '#ba1a1a',
                'error-container': '#ffdad6',
                secondary: '#4648d4',
                'secondary-container': '#6063ee',
            },
            fontFamily: {
                display: ['Hanken Grotesk', ...defaultTheme.fontFamily.sans],
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [forms],
};
