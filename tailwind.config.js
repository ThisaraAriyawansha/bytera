/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                milonga: ['"Open Sans"', 'sans-serif'],
                prata:   ['Montserrat', 'sans-serif'],   // used for headings, always font-weight 700
                poppins: ['Poppins', 'sans-serif'],      // default body font
            },
            colors: {
                black: '#0a0a0a',
                brand: { DEFAULT: '#e30613', dark: '#b8050f', light: '#fff1f1', logo: '#ff0607' },
                ink: '#0a0a0a',
                white: '#ffffff',
                gray: { 50:'#fafafa',100:'#f4f4f5',200:'#e4e4e7',300:'#d4d4d8',400:'#a1a1aa',500:'#71717a',600:'#52525b',700:'#3f3f46',800:'#27272a',900:'#18181b' },
            },
            borderRadius: { none:'0', sm:'2px', DEFAULT:'4px', md:'6px', lg:'8px', xl:'12px' },
            keyframes: {
                fadeIn:  { from:{opacity:'0',transform:'translateY(6px)'}, to:{opacity:'1',transform:'translateY(0)'} },
                slideIn: { from:{transform:'translateX(-100%)'}, to:{transform:'translateX(0)'} },
            },
            animation: { fadeIn:'fadeIn 0.2s ease-out', slideIn:'slideIn 0.25s ease-out' },
        },
    },
    plugins: [],
};
