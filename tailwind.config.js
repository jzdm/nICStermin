/** @type {import('tailwindcss').Config} */
module.exports = {
  
  prefix: 'nit-',
  
  corePlugins: {
    preflight: false,
  },
  
  content: [
    './public/templates/**/*.html.twig'
  ],
  
  safelist: [
    'col-start-1', 'col-start-2', 'col-start-3', 'col-start-4', 'col-start-5', 'col-start-6', 'col-start-7',
  ],
  
  theme: {
    extend: {},
  },
  
  plugins: [],
  
  variants: {
    extend: {
      display: ["group-hover"],
    },
   },
}
