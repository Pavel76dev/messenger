/** Public URL prefix when app is served under a subdirectory (e.g. /__nr_gate). */
export const APP_BASE = String(
  import.meta.env.VITE_APP_BASE || import.meta.env.BASE_URL || '/',
)
  .replace(/\/+$/, '')
  .replace(/^\s*$/, '');

/** API origin prefix; defaults to APP_BASE so /api stays under the same subdirectory. */
export const API_BASE = String(
  import.meta.env.VITE_API_URL !== undefined && import.meta.env.VITE_API_URL !== ''
    ? import.meta.env.VITE_API_URL
    : APP_BASE,
).replace(/\/+$/, '');
