import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  List,
  ListItem,
  ListItemText,
  Stack,
  Typography,
} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import StopIcon from '@mui/icons-material/Stop';
import { deleteAudioClip, fetchAudioClips } from './audioClipsApi';
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

export function AudioClipsLibraryDialog({ open, onClose }) {
  const [clips, setClips] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [playingId, setPlayingId] = useState(null);
  const [audioEl, setAudioEl] = useState(null);

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

  return (
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
            {clips.map((clip) => (
              <ListItem
                key={clip.id}
                secondaryAction={
                  <Stack direction="row" spacing={0.5}>
                    <IconButton
                      edge="end"
                      aria-label={playingId === clip.id ? 'Стоп' : 'Слушать'}
                      onClick={() => handlePlay(clip)}
                    >
                      {playingId === clip.id ? <StopIcon /> : <PlayArrowIcon />}
                    </IconButton>
                    <IconButton
                      edge="end"
                      aria-label="Удалить"
                      onClick={() => handleDelete(clip)}
                    >
                      <DeleteIcon />
                    </IconButton>
                  </Stack>
                }
                sx={{ pr: 12 }}
              >
                <ListItemText
                  primary={formatClipDate(clip.created_at)}
                  secondary={
                    clip.duration_ms != null
                      ? formatElapsed(clip.duration_ms)
                      : undefined
                  }
                />
              </ListItem>
            ))}
          </List>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>Закрыть</Button>
      </DialogActions>
    </Dialog>
  );
}
