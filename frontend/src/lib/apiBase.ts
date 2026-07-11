/** Default Ampps path — override with VITE_API_PROXY_BASE in frontend/.env.local */
const DEFAULT_AMPPS_PUBLIC = '/Registration-System/public';

/**
 * Resolve the API base URL for production (Ampps subfolder) and Vite dev.
 *
 * Dev (localhost:5173): calls PHP directly on Apache with CORS
 *   → http://127.0.0.1/Registration-System/public/api
 *
 * Production (Ampps): auto-detects /.../public in the browser URL
 *   → /Registration-System/public/api
 */
export function getApiBase(): string {
  const explicit = import.meta.env.VITE_API_BASE?.replace(/\/$/, '');
  if (explicit) {
    return explicit.endsWith('/api') ? explicit : `${explicit}/api`;
  }

  // Vite dev server — pathname has no /public, so hit Apache directly
  if (import.meta.env.DEV) {
    const host = (import.meta.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1').replace(/\/$/, '');
    const base = (import.meta.env.VITE_API_PROXY_BASE || DEFAULT_AMPPS_PUBLIC).replace(/\/$/, '');
    if (base) {
      return `${host}${base}/api`;
    }
    return '/api';
  }

  // Production build served from Ampps subfolder
  if (typeof window !== 'undefined') {
    const subfolder = window.location.pathname.match(/^(.*\/public)(?=\/|$)/);
    if (subfolder) {
      return `${subfolder[1]}/api`;
    }
  }

  return '/api';
}

/** Web root prefix for PHP admin links (e.g. /Registration-System/public or "") */
export function getWebRoot(): string {
  const explicit = import.meta.env.VITE_API_BASE?.replace(/\/$/, '');
  if (explicit) {
    return explicit.endsWith('/api') ? explicit.slice(0, -4) : explicit;
  }

  if (import.meta.env.DEV) {
    return (import.meta.env.VITE_API_PROXY_BASE || '').replace(/\/$/, '');
  }

  if (typeof window !== 'undefined') {
    const subfolder = window.location.pathname.match(/^(.*\/public)(?=\/|$)/);
    if (subfolder) {
      return subfolder[1];
    }
  }

  return '';
}
