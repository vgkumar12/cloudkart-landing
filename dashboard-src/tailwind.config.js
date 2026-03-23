/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#6B46C1',
                    light: '#8B5CF6',
                    lighter: '#A78BFA',
                    dark: '#5B21B6',
                },
                secondary: '#7C3AED',
                accent: '#C084FC',
                background: '#f8fafc',
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
                '4xl': '2rem',
                '5xl': '2.5rem',
            },
            boxShadow: {
                'premium': '0 20px 40px rgba(0, 0, 0, 0.05)',
                'elevated': '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
            },
            animation: {
                'float': 'float 20s infinite linear',
            },
            keyframes: {
                float: {
                    '0%': { transform: 'translateY(0) rotate(0)' },
                    '50%': { transform: 'translateY(-100px) rotate(180deg)' },
                    '100%': { transform: 'translateY(0) rotate(360deg)' },
                }
            }
        },
    },
    plugins: [],
}
