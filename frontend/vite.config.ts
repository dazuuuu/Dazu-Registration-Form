import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  // Ampps Apache is usually port 80, not 8080. Override in frontend/.env.local if needed.
  const target = env.VITE_API_PROXY_TARGET || 'http://127.0.0.1';
  const basePath = (env.VITE_API_PROXY_BASE || '/Registration-System/public').replace(/\/$/, '');

  return {
    plugins: [react(), tailwindcss()],
    // Relative asset paths so the build works under Ampps subfolders (/Registration-System/public/)
    base: './',
    build: {
      outDir: '../public',
      emptyOutDir: false,
      assetsDir: 'assets/app',
    },
    server: {
      port: 5173,
      proxy: {
        '/api': {
          target,
          changeOrigin: true,
          secure: false,
          ...(basePath
            ? {
                rewrite: (path) => `${basePath}${path}`,
              }
            : {}),
          configure: (proxy) => {
            proxy.on('proxyRes', (proxyRes) => {
              const cookies = proxyRes.headers['set-cookie'];
              if (!cookies) return;
              proxyRes.headers['set-cookie'] = cookies.map((cookie) =>
                cookie
                  .replace(/;\s*Secure/gi, '')
                  .replace(/;\s*Domain=[^;]+/gi, '')
                  .replace(/;\s*Path=\/[^;]*/gi, '; Path=/')
              );
            });
          },
        },
      },
    },
  };
});
