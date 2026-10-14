/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    '../../application/**/*.php',
    '../../application/views/**/*.php',
    '../../application/modules/**/views/**/*.php',
    '../../public_html/**/*.js',
    '../../public_html/**/*.html',
  ],
  theme: {
    extend: {
      colors: {
        primary: '#3b82f6',
        dark: '#0f172a',
      },
    },
  },
  plugins: [],
};
