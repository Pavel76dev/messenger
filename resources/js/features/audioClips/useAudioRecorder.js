import { useCallback, useEffect, useRef, useState } from 'react';
import { callMediaBlockReason } from '../calls/mediaAccess';

const MAX_DURATION_MS = 5 * 60 * 1000;

function pickMimeType() {
  if (typeof MediaRecorder === 'undefined') {
    return '';
  }
  const candidates = [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/ogg;codecs=opus',
    'audio/mp4',
  ];
  return candidates.find((type) => MediaRecorder.isTypeSupported(type)) || '';
}

function extensionForMime(mime) {
  if (!mime) return 'webm';
  if (mime.includes('ogg')) return 'ogg';
  if (mime.includes('mp4')) return 'm4a';
  if (mime.includes('mpeg') || mime.includes('mp3')) return 'mp3';
  return 'webm';
}

export function blobToAudioFile(blob, mime, recordedAt = new Date()) {
  const safeMime = mime || blob.type || 'audio/webm';
  const ext = extensionForMime(safeMime);
  const stamp = recordedAt
    .toISOString()
    .replace(/[-:]/g, '')
    .replace(/\.\d+Z$/, '')
    .replace('T', '-');
  return new File([blob], `audio-clip-${stamp}.${ext}`, { type: safeMime });
}

export function useAudioRecorder() {
  const [recording, setRecording] = useState(false);
  const [elapsedMs, setElapsedMs] = useState(0);
  const [error, setError] = useState('');
  const recorderRef = useRef(null);
  const streamRef = useRef(null);
  const chunksRef = useRef([]);
  const mimeRef = useRef('');
  const startedAtRef = useRef(0);
  const timerRef = useRef(null);
  const maxTimerRef = useRef(null);
  const stopResolverRef = useRef(null);

  const clearTimers = useCallback(() => {
    if (timerRef.current) {
      clearInterval(timerRef.current);
      timerRef.current = null;
    }
    if (maxTimerRef.current) {
      clearTimeout(maxTimerRef.current);
      maxTimerRef.current = null;
    }
  }, []);

  const cleanupStream = useCallback(() => {
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop());
      streamRef.current = null;
    }
    recorderRef.current = null;
  }, []);

  useEffect(() => () => {
    clearTimers();
    try {
      if (recorderRef.current && recorderRef.current.state !== 'inactive') {
        recorderRef.current.stop();
      }
    } catch {
      // ignore
    }
    cleanupStream();
  }, [clearTimers, cleanupStream]);

  const finishWithBlob = useCallback((blob) => {
    clearTimers();
    cleanupStream();
    setRecording(false);
    const mime = mimeRef.current || blob?.type || 'audio/webm';
    const durationMs = Math.max(0, Date.now() - startedAtRef.current);
    const resolve = stopResolverRef.current;
    stopResolverRef.current = null;
    if (!blob || blob.size === 0) {
      setError('Запись пустая. Попробуйте ещё раз.');
      resolve?.({ blob: null, mime, durationMs: 0 });
      return;
    }
    if (blob.size > 10 * 1024 * 1024) {
      setError('Файл больше 10 MB — нельзя сохранить или отправить.');
      resolve?.({ blob: null, mime, durationMs });
      return;
    }
    resolve?.({ blob, mime, durationMs });
  }, [clearTimers, cleanupStream]);

  const stop = useCallback(() => {
    const recorder = recorderRef.current;
    if (!recorder || recorder.state === 'inactive') {
      setRecording(false);
      return Promise.resolve({ blob: null, mime: '', durationMs: 0 });
    }

    return new Promise((resolve) => {
      stopResolverRef.current = resolve;
      try {
        recorder.stop();
      } catch (err) {
        stopResolverRef.current = null;
        clearTimers();
        cleanupStream();
        setRecording(false);
        setError('Не удалось остановить запись.');
        resolve({ blob: null, mime: '', durationMs: 0 });
      }
    });
  }, [clearTimers, cleanupStream]);

  const start = useCallback(async () => {
    setError('');
    const blocked = callMediaBlockReason();
    if (blocked) {
      setError(blocked);
      return false;
    }
    if (typeof MediaRecorder === 'undefined') {
      setError('Запись аудио не поддерживается в этом браузере.');
      return false;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: false,
      });
      streamRef.current = stream;
      const mime = pickMimeType();
      mimeRef.current = mime;
      const recorder = mime
        ? new MediaRecorder(stream, { mimeType: mime })
        : new MediaRecorder(stream);
      chunksRef.current = [];
      recorderRef.current = recorder;

      recorder.ondataavailable = (event) => {
        if (event.data && event.data.size > 0) {
          chunksRef.current.push(event.data);
        }
      };

      recorder.onerror = () => {
        setError('Ошибка записи микрофона.');
        clearTimers();
        cleanupStream();
        setRecording(false);
        const resolve = stopResolverRef.current;
        stopResolverRef.current = null;
        resolve?.({ blob: null, mime: mimeRef.current, durationMs: 0 });
      };

      recorder.onstop = () => {
        const type = mimeRef.current || 'audio/webm';
        const blob = new Blob(chunksRef.current, { type });
        chunksRef.current = [];
        finishWithBlob(blob);
      };

      startedAtRef.current = Date.now();
      setElapsedMs(0);
      recorder.start(250);
      setRecording(true);

      timerRef.current = setInterval(() => {
        setElapsedMs(Date.now() - startedAtRef.current);
      }, 250);

      maxTimerRef.current = setTimeout(() => {
        if (recorderRef.current && recorderRef.current.state !== 'inactive') {
          stop();
        }
      }, MAX_DURATION_MS);

      return true;
    } catch (err) {
      cleanupStream();
      const name = err?.name || '';
      if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        setError('Нет доступа к микрофону. Разрешите запись в настройках браузера.');
      } else if (name === 'NotFoundError') {
        setError('Микрофон не найден.');
      } else {
        setError(err?.message || 'Не удалось начать запись.');
      }
      return false;
    }
  }, [clearTimers, cleanupStream, finishWithBlob, stop]);

  const toggle = useCallback(async () => {
    if (recording) {
      return stop();
    }
    const ok = await start();
    return ok ? null : { blob: null, mime: '', durationMs: 0 };
  }, [recording, start, stop]);

  return {
    recording,
    elapsedMs,
    error,
    setError,
    start,
    stop,
    toggle,
  };
}

export function formatElapsed(ms) {
  const totalSec = Math.floor((ms || 0) / 1000);
  const min = Math.floor(totalSec / 60);
  const sec = totalSec % 60;
  return `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
}
