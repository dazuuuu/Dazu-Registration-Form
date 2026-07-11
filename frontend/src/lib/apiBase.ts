/**
 * Resolve the API base URL for both production (Ampps subfolder) and Vite dev.
 *
 * Ampps example: site at /Registration-System/public/ → API at /Registration-System/public/api
 * Docroot = public/: site at / → API at /api
 */
export function getApiBase(): string {
  const fromEnv = import.meta.env.VITE_API_BASE?.replace(/\/$/, '');
  if (fromEnv) {
    return fromEnv.endsWith('/api') ? fromEnv : `${fromEnv}/api`;
  }

  if (typeof window !== 'undefined') {
    const path = window.location.pathname;
    const subfolder = path.match(/^(.*\/public)(?=\/|$)/);
    if (subfolder) {
      return `${subfolder[1]}/api`;
    }
  }

  return '/api';
}

/** Web root prefix for PHP admin links (e.g. /Registration-System/public or "") */
export function getWebRoot(): string {
  const fromEnv = import.meta.env.VITE_API_BASE?.replace(/\/$/, '');
  if (fromEnv) {
    return fromEnv.endsWith('/api') ? fromEnv.slice(0, -4) : fromEnv;
  }

  if (typeof window !== 'undefined') {
    const path = window.location.pathname;
    const subfolder = path.match(/^(.*\/public)(?=\/|$)/);
    if (subfolder) {
      return subfolder[1];
    }
  }

  return '';
}
