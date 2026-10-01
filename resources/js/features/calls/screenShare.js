/**
 * Screen share capability for browser clients.
 * iOS Safari historically lacks or limits getDisplayMedia — gate the UI.
 */
export function canScreenShare() {
  if (typeof navigator === 'undefined' || !navigator.mediaDevices) {
    return false;
  }
  if (typeof navigator.mediaDevices.getDisplayMedia !== 'function') {
    return false;
  }
  const ua = navigator.userAgent || '';
  // iPhone / iPod: no reliable screen share in mobile Safari web
  if (/iPhone|iPod/i.test(ua)) {
    return false;
  }
  // iPadOS 13+ reports as Mac; still often limited in Safari webviews
  if (/iPad/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) {
    return false;
  }
  return true;
}

export function screenShareHint() {
  if (canScreenShare()) {
    return '';
  }
  return 'Демонстрация экрана недоступна в этом браузере. На iOS используйте нативное приложение (позже) или desktop.';
}
