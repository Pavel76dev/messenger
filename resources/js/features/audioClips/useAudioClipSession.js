import { useCallback, useState } from 'react';
import { useAppDispatch } from '../../app/hooks';
import { fetchConversations } from '../conversations/conversationsSlice';
import { sendMessage } from '../messages/messagesSlice';
import { saveAudioClip } from './audioClipsApi';
import { blobToAudioFile, useAudioRecorder } from './useAudioRecorder';

export function useAudioClipSession({ conversationId, onSaved }) {
  const dispatch = useAppDispatch();
  const recorder = useAudioRecorder();
  const [pending, setPending] = useState(null);
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState('');

  const clearPending = useCallback(() => {
    setPending(null);
    setActionError('');
  }, []);

  const handleToggle = useCallback(async () => {
    setActionError('');
    if (recorder.recording) {
      const result = await recorder.stop();
      if (result?.blob) {
        setPending(result);
      }
      return;
    }
    await recorder.start();
  }, [recorder]);

  const handleSave = useCallback(async () => {
    if (!pending?.blob) return;
    setBusy(true);
    setActionError('');
    try {
      const file = blobToAudioFile(pending.blob, pending.mime);
      await saveAudioClip(file, pending.durationMs);
      clearPending();
      onSaved?.();
    } catch (err) {
      setActionError(err?.message || 'Не удалось сохранить запись.');
    } finally {
      setBusy(false);
    }
  }, [pending, clearPending, onSaved]);

  const handleSend = useCallback(async () => {
    if (!pending?.blob) return;
    if (!conversationId) {
      setActionError('Откройте чат, чтобы отправить запись.');
      return;
    }
    setBusy(true);
    setActionError('');
    try {
      const file = blobToAudioFile(pending.blob, pending.mime);
      const result = await dispatch(
        sendMessage({
          conversationId,
          body: '',
          files: [file],
        }),
      );
      if (sendMessage.fulfilled.match(result)) {
        clearPending();
        dispatch(fetchConversations());
      } else {
        setActionError(result.payload || 'Не удалось отправить запись.');
      }
    } catch (err) {
      setActionError(err?.message || 'Не удалось отправить запись.');
    } finally {
      setBusy(false);
    }
  }, [pending, conversationId, dispatch, clearPending]);

  return {
    recording: recorder.recording,
    elapsedMs: recorder.elapsedMs,
    recorderError: recorder.error,
    pending,
    busy,
    actionError,
    handleToggle,
    handleSave,
    handleSend,
    clearPending,
    setRecorderError: recorder.setError,
  };
}
