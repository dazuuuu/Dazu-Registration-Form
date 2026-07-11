/** Default Ampps path — override with VITE_API_PROXY_BASE in frontend/.env.local */
const DEFAULT_AMPPS_PUBLIC = '/Registration-System/public';

/**
 * Web root prefix when the site is served from a subfolder (e.g. /Registration-System/public).
 * Empty when public/ is the document root.
 */
export function getWebRoot(): string {
  const explicit = import.meta.env.VITE_API_BASE?.replace(/\/$/, '');
  if (explicit) {
    return explicit.endsWith('/api') ? explicit.slice(0, -4) : explicit;
  }

  if (typeof window !== 'undefined') {
    const subfolder = window.location.pathname.match(/^(.*\/public)(?=\/|$)/);
    if (subfolder) {
      return subfolder[1];
    }
  }

  if (import.meta.env.DEV) {
    return (import.meta.env.VITE_API_PROXY_BASE || DEFAULT_AMPPS_PUBLIC).replace(/\/$/, '');
  }

  return '';
}

/** React Router basename — matches Ampps subfolder installs */
export function getBasename(): string {
  return getWebRoot();
}

/**
 * API base URL.
 * Dev: same-origin /api via Vite proxy (session cookies work).
 * Production: /{subfolder}/public/api or /api at docroot.
 */
export function getApiBase(): string {
  const explicit = import.meta.env.VITE_API_BASE?.replace(/\/$/, '');
  if (explicit) {
    return explicit.endsWith('/api') ? explicit : `${explicit}/api`;
  }

  if (import.meta.env.DEV) {
    return '/api';
  }

  const root = getWebRoot();
  if (root) {
    return `${root}/api`;
  }

  return '/api';
}

/** Absolute URL to a PHP page (admin portal, dashboard, etc.) */
export function getPhpUrl(path: string): string {
  const segment = path.startsWith('/') ? path : `/${path}`;
  const root = getWebRoot();

  if (import.meta.env.DEV) {
    const host = (import.meta.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1').replace(/\/$/, '');
    return `${host}${root}${segment}`;
  }

  if (typeof window !== 'undefined') {
    return `${window.location.origin}${root}${segment}`;
  }

  return `${root}${segment}`;
}

/** Strip web-root prefix from backend redirect paths for React Router */
export function toSpaPath(pathOrUrl: string): string {
  const root = getWebRoot();
  let path = pathOrUrl;

  try {
    if (/^https?:\/\//i.test(path)) {
      const u = new URL(path);
      path = u.pathname + u.search + u.hash;
    }
  } catch {
    // keep as-is
  }

  if (root && path.startsWith(root)) {
    path = path.slice(root.length) || '/';
  }

  return path.startsWith('/') ? path : `/${path}`;
}
