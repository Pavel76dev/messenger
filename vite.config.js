import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const base = env.VITE_APP_BASE
    ? `${String(env.VITE_APP_BASE).replace(/\/+$/, '')}/`
    : '/';

  return {
    base,
    plugins: [
      laravel({
        input: ['resources/js/main.jsx'],
        refresh: true,
      }),
      react(),
    ],
    resolve: {
      alias: {
        '@': path.resolve('resources/js'),
      },
    },
    server: {
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
      hmr: {
        host: '127.0.0.1',
      },
    },
  };
});
