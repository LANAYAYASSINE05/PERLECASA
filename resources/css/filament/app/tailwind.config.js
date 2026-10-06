import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                marine: {
                    700: '#143459',
                    800: '#0c2a4d',
                    900: '#0a1f3b',
                    950: '#06152a',
                },
                or: {
                    200: '#f0e5ce',
                    300: '#e2c27a',
                    400: '#d6b575',
                    500: '#c4953a',
                    600: '#a37a2c',
                    700: '#7f5f22',
                },
            },
            fontFamily: {
                serif: ['Fraunces', 'Georgia', 'serif'],
            },
        },
    },
}
