import { useCallback, useEffect, useState } from 'react';
import {
  Box,
  Button,
  Checkbox,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  IconButton,
  List,
  ListItem,
  ListItemText,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material';
import ChecklistIcon from '@mui/icons-material/Checklist';
import DeleteIcon from '@mui/icons-material/Delete';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import SendIcon from '@mui/icons-material/Send';
import StopIcon from '@mui/icons-material/Stop';
import SubjectIcon from '@mui/icons-material/Subject';
import TranscribeIcon from '@mui/icons-material/Transcribe';
import { useAppDispatch } from '../../app/hooks';
import { fetchConversations } from '../conversations/conversationsSlice';
import { fetchMessages } from '../messages/messagesSlice';
import {
  deleteAudioClip,
  deleteTaskList,
  extractTasksFromAudioClip,
  fetchAudioClips,
  sendAudioClipToChat,
  transcribeAudioClip,
  updateTaskItemStatus,
} from './audioClipsApi';
import { formatElapsed } from './useAudioRecorder';

function formatClipDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  const pad = (n) => String(n).padStart(2, '0');
  return (
    `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()} ` +
    `${pad(date.getHours())}:${pad(date.getMinutes())}`
  );
}

function ClipTaskLists({ clip, onClipPatch, onError }) {
  const lists = Array.isArray(clip.task_lists) ? clip.task_lists : [];
  const extractPending = clip.tasks_extract_status === 'pending';
  const extractEmpty = clip.tasks_extract_status === 'empty';
  const extractFailed = clip.tasks_extract_status === 'error';

  const handleToggle = async (item) => {
    const next = item.status === 'done' ? 'open' : 'done';
    try {
      const updated = await updateTaskItemStatus(item.id, next);
      onClipPatch({
        ...clip,
        task_lists: lists.map((list) =>
          list.id === updated.task_list_id
            ? {
                ...list,
                items: (list.items || []).map((row) =>
                  row.id === updated.id ? { ...row, ...updated } : row,
                ),
              }
            : list,
        ),
      });
    } catch (err) {
      onError(err?.message || 'Не удалось обновить задачу.');
    }
  };

  const handleDeleteList = async (list) => {
    try {
      await deleteTaskList(list.id);
      onClipPatch({
        ...clip,
        task_lists: lists.filter((row) => row.id !== list.id),
      });
    } catch (err) {
      onError(err?.message || 'Не удалось удалить список.');
    }
  };

  if (!extractPending && !extractEmpty && !extractFailed && lists.length === 0) {
    return null;
  }

  return (
    <Box sx={{ pl: 1, pr: 1, pb: 1, width: '100%' }}>
      {extractPending ? (
        <Typography variant="caption" color="text.secondary">
          ИИ ищет задачи…
        </Typography>
      ) : null}
      {extractEmpty ? (
        <Typography variant="caption" color="text.secondary">
          {clip.tasks_extract_error || 'Явных задач в тексте не найдено.'}
        </Typography>
      ) : null}
      {extractFailed ? (
        <Typography variant="caption" color="error">
          {clip.tasks_extract_error || 'Ошибка извлечения задач.'}
        </Typography>
      ) : null}
      {lists.map((list) => (
        <Box
          key={list.id}
          sx={{
            mt: 0.5,
            p: 1,
            borderRadius: 1,
            bgcolor: 'action.hover',
          }}
        >
          <Stack direction="row" alignItems="center" justifyContent="space-between" spacing={1}>
            <Typography variant="caption" fontWeight={600}>
              {list.title || 'Задачи'}
            </Typography>
            <Tooltip title="Удалить список">
              <IconButton
                size="small"
                aria-label="Удалить список задач"
                onClick={() => handleDeleteList(list)}
              >
                <DeleteIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>
          <Stack>
            {(list.items || []).map((item) => (
              <FormControlLabel
                key={item.id}
                sx={{ ml: 0, alignItems: 'flex-start' }}
                control={
                  <Checkbox
                    size="small"
                    checked={item.status === 'done'}
                    onChange={() => handleToggle(item)}
                  />
                }
                label={
                  <Typography
                    variant="body2"
                    sx={{
                      textDecoration: item.status === 'done' ? 'line-through' : 'none',
                      color: item.status === 'done' ? 'text.secondary' : 'text.primary',
                      pt: 0.75,
                    }}
                  >
                    {item.body}
                  </Typography>
                }
              />
            ))}
          </Stack>
        </Box>
      ))}
    </Box>
  );
}

export function AudioClipsLibraryDialog({ open, onClose, conversationId }) {
  const dispatch = useAppDispatch();
  const [clips, setClips] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [playingId, setPlayingId] = useState(null);
  const [audioEl, setAudioEl] = useState(null);
  const [busyId, setBusyId] = useState(null);
  const [viewClip, setViewClip] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const list = await fetchAudioClips();
      setClips(list);
    } catch (err) {
      setError(err?.message || 'Не удалось загрузить записи.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (open) {
      load();
    } else if (audioEl) {
      audioEl.pause();
      setPlayingId(null);
      setViewClip(null);
    }
  }, [open, load, audioEl]);

  useEffect(() => {
    const el = new Audio();
    el.addEventListener('ended', () => setPlayingId(null));
    setAudioEl(el);
    return () => {
      el.pause();
      el.src = '';
    };
  }, []);

  const hasPending = clips.some(
    (c) => c.transcript_status === 'pending' || c.tasks_extract_status === 'pending',
  );

  useEffect(() => {
    if (!open || !hasPending) return undefined;
    const timer = setInterval(() => {
      fetchAudioClips()
        .then(setClips)
        .catch(() => {});
    }, 2500);
    return () => clearInterval(timer);
  }, [open, hasPending]);

  const patchClip = (updated) => {
    setClips((prev) =>
      prev.map((item) => (item.id === updated.id ? { ...item, ...updated } : item)),
    );
  };

  const handlePlay = async (clip) => {
    if (!audioEl) return;
    if (playingId === clip.id) {
      audioEl.pause();
      setPlayingId(null);
      return;
    }
    try {
      audioEl.src = clip.url;
      await audioEl.play();
      setPlayingId(clip.id);
      setError('');
    } catch (err) {
      setError(err?.message || 'Не удалось воспроизвести.');
      setPlayingId(null);
    }
  };

  const handleDelete = async (clip) => {
    try {
      if (playingId === clip.id && audioEl) {
        audioEl.pause();
        setPlayingId(null);
      }
      await deleteAudioClip(clip.id);
      setClips((prev) => prev.filter((item) => item.id !== clip.id));
      setError('');
    } catch (err) {
      setError(err?.message || 'Не удалось удалить.');
    }
  };

  const handleSend = async (clip) => {
    if (!conversationId) {
      setError('Откройте чат, чтобы отправить запись.');
      return;
    }
    setBusyId(clip.id);
    setError('');
    try {
      await sendAudioClipToChat(clip.id, conversationId);
      dispatch(fetchConversations());
      dispatch(fetchMessages({ conversationId }));
    } catch (err) {
      setError(err?.message || 'Не удалось отправить в чат.');
    } finally {
      setBusyId(null);
    }
  };

  const handleTranscribe = async (clip) => {
    setBusyId(clip.id);
    setError('');
    try {
      const updated = await transcribeAudioClip(clip.id);
      patchClip(updated);
    } catch (err) {
      setError(err?.message || 'Не удалось запустить транскрибацию.');
    } finally {
      setBusyId(null);
    }
  };

  const handleExtractTasks = async (clip) => {
    setBusyId(clip.id);
    setError('');
    try {
      const updated = await extractTasksFromAudioClip(clip.id);
      patchClip(updated);
    } catch (err) {
      setError(err?.message || 'Не удалось запустить извлечение задач.');
    } finally {
      setBusyId(null);
    }
  };

  return (
    <>
      <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
        <DialogTitle>Сохранённые записи</DialogTitle>
        <DialogContent dividers>
          {error ? (
            <Typography variant="caption" color="error" sx={{ display: 'block', mb: 1 }}>
              {error}
            </Typography>
          ) : null}
          {loading ? (
            <Typography variant="body2" color="text.secondary">
              Загрузка…
            </Typography>
          ) : clips.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              Пока нет сохранённых записей.
            </Typography>
          ) : (
            <List dense disablePadding>
              {clips.map((clip) => {
                const pending = clip.transcript_status === 'pending';
                const done = clip.transcript_status === 'done';
                const failed = clip.transcript_status === 'error';
                const extractPending = clip.tasks_extract_status === 'pending';
                const rowBusy = busyId === clip.id;

                return (
                  <Box
                    key={clip.id}
                    sx={{ borderBottom: '1px solid', borderColor: 'divider' }}
                  >
                    <ListItem
                      secondaryAction={
                        <Stack direction="row" spacing={0.25} alignItems="center">
                          <Tooltip title={playingId === clip.id ? 'Стоп' : 'Слушать'}>
                            <IconButton
                              edge="end"
                              aria-label={playingId === clip.id ? 'Стоп' : 'Слушать'}
                              onClick={() => handlePlay(clip)}
                            >
                              {playingId === clip.id ? <StopIcon /> : <PlayArrowIcon />}
                            </IconButton>
                          </Tooltip>
                          <Tooltip
                            title={
                              conversationId
                                ? 'Отправить в этот чат'
                                : 'Откройте чат, чтобы отправить'
                            }
                          >
                            <span>
                              <IconButton
                                edge="end"
                                aria-label="Отправить в чат"
                                disabled={!conversationId || rowBusy}
                                onClick={() => handleSend(clip)}
                              >
                                <SendIcon />
                              </IconButton>
                            </span>
                          </Tooltip>
                          <Tooltip
                            title={
                              pending
                                ? 'Идёт транскрибация…'
                                : failed
                                  ? clip.transcript_error || 'Ошибка — повторить'
                                  : 'Транскрибировать'
                            }
                          >
                            <span>
                              <IconButton
                                edge="end"
                                aria-label="Транскрибировать"
                                disabled={pending || rowBusy}
                                onClick={() => handleTranscribe(clip)}
                                color={failed ? 'error' : 'default'}
                              >
                                {pending ? (
                                  <CircularProgress size={18} />
                                ) : (
                                  <TranscribeIcon />
                                )}
                              </IconButton>
                            </span>
                          </Tooltip>
                          <Tooltip title={done ? 'Посмотреть текст' : 'Текст ещё не готов'}>
                            <span>
                              <IconButton
                                edge="end"
                                aria-label="Посмотреть транскрипт"
                                disabled={!done}
                                onClick={() => setViewClip(clip)}
                              >
                                <SubjectIcon />
                              </IconButton>
                            </span>
                          </Tooltip>
                          <Tooltip
                            title={
                              !done
                                ? 'Сначала нужен текст'
                                : extractPending
                                  ? 'ИИ ищет задачи…'
                                  : 'Список задач из текста'
                            }
                          >
                            <span>
                              <IconButton
                                edge="end"
                                aria-label="Список задач"
                                disabled={!done || extractPending || rowBusy}
                                onClick={() => handleExtractTasks(clip)}
                              >
                                {extractPending ? (
                                  <CircularProgress size={18} />
                                ) : (
                                  <ChecklistIcon />
                                )}
                              </IconButton>
                            </span>
                          </Tooltip>
                          <Tooltip title="Удалить">
                            <IconButton
                              edge="end"
                              aria-label="Удалить"
                              disabled={rowBusy || pending || extractPending}
                              onClick={() => handleDelete(clip)}
                            >
                              <DeleteIcon />
                            </IconButton>
                          </Tooltip>
                        </Stack>
                      }
                      sx={{ pr: 26 }}
                    >
                      <ListItemText
                        primary={formatClipDate(clip.created_at)}
                        secondary={
                          [
                            clip.duration_ms != null
                              ? formatElapsed(clip.duration_ms)
                              : null,
                            pending
                              ? 'транскрибация…'
                              : done
                                ? 'есть текст'
                                : failed
                                  ? 'ошибка транскрибации'
                                  : null,
                            extractPending
                              ? 'задачи…'
                              : (clip.task_lists || []).length > 0
                                ? `задач: ${(clip.task_lists || []).reduce(
                                    (n, list) => n + (list.items || []).length,
                                    0,
                                  )}`
                                : null,
                          ]
                            .filter(Boolean)
                            .join(' · ') || undefined
                        }
                      />
                    </ListItem>
                    <ClipTaskLists
                      clip={clip}
                      onClipPatch={patchClip}
                      onError={setError}
                    />
                  </Box>
                );
              })}
            </List>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={onClose}>Закрыть</Button>
        </DialogActions>
      </Dialog>

      <Dialog
        open={Boolean(viewClip)}
        onClose={() => setViewClip(null)}
        fullWidth
        maxWidth="sm"
      >
        <DialogTitle>Текст записи</DialogTitle>
        <DialogContent dividers>
          <Typography
            variant="body2"
            sx={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}
          >
            {(viewClip?.transcript_text || '').trim() || 'Текст пустой.'}
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button
            disabled={
              !viewClip ||
              viewClip.transcript_status !== 'done' ||
              viewClip.tasks_extract_status === 'pending'
            }
            onClick={() => {
              if (viewClip) handleExtractTasks(viewClip);
            }}
          >
            Список задач
          </Button>
          <Button onClick={() => setViewClip(null)}>Закрыть</Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
