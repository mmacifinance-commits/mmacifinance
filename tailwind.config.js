/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      colors: {
        navy: {
          DEFAULT: '#243352',
          dark: '#1a2744',
          light: '#2c3e5e',
        },
        mustard: {
          DEFAULT: '#d4a843',
          light: '#e0be6a',
          dark: '#b8922f',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
