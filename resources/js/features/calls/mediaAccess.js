/**
 * getUserMedia exists only in a secure context:
 * https, http://localhost, http://127.0.0.1, http://*.localhost.
 * http://messenger (OpenServer) is not one of those, so navigator.mediaDevices is undefined.
 */
export function callMediaBlockReason() {
  const hasMedia =
    typeof navigator !== 'undefined' &&
    navigator.mediaDevices &&
    typeof navigator.mediaDevices.getUserMedia === 'function';

  if (hasMedia) {
    return '';
  }

  const host = typeof window !== 'undefined' ? window.location.host : '';
  return (
    `Микрофон и камера недоступны на http://${host || 'этом адресе'}: ` +
    'браузер не даёт getUserMedia вне безопасного контекста. ' +
    'Откройте мессенджер как http://messenger.localhost ' +
    '(алиас домена messenger в OpenServer и строка hosts 127.0.0.1 messenger.localhost) ' +
    'либо по HTTPS. Адрес http://messenger без HTTPS для звонков не подходит.'
  );
}
