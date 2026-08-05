<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'OdontoSuite') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div id="app">
        <div class="min-h-screen bg-primary-500 flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-lg">
                <h1 class="text-2xl font-bold text-gray-800 mb-4">OdontoSuite</h1>
                <p class="text-gray-600">Cargando aplicación...</p>
                <div class="mt-4">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fallback si Vue.js no carga
        setTimeout(function() {
            const app = document.getElementById('app');
            if (app && app.innerHTML.includes('Cargando aplicación...')) {
                app.innerHTML = `
                    <div class="min-h-screen bg-error-500 flex items-center justify-center">
                        <div class="bg-white p-8 rounded-lg shadow-lg">
                            <h1 class="text-2xl font-bold text-gray-800 mb-4">Error de Carga</h1>
                            <p class="text-gray-600">Vue.js no se pudo cargar. Verifica la consola del navegador.</p>
                            <button onclick="location.reload()" class="mt-4 bg-primary-500 text-white px-4 py-2 rounded">
                                Recargar Página
                            </button>
                        </div>
                    </div>
                `;
            }
        }, 5000);
    </script>
</body>
</html>
