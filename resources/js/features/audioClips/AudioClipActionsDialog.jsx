import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Typography,
} from '@mui/material';

export function AudioClipActionsDialog({
  open,
  busy = false,
  error = '',
  onSave,
  onSend,
  onCancel,
}) {
  return (
    <Dialog open={open} onClose={busy ? undefined : onCancel} fullWidth maxWidth="xs">
      <DialogTitle>Аудиозапись</DialogTitle>
      <DialogContent>
        <Typography variant="body2" sx={{ mb: 1 }}>
          Сохранить в библиотеку или отправить в текущий чат?
        </Typography>
        {error ? (
          <Typography variant="caption" color="error" sx={{ display: 'block' }}>
            {error}
          </Typography>
        ) : null}
      </DialogContent>
      <DialogActions sx={{ px: 2, pb: 2, gap: 1, flexWrap: 'wrap' }}>
        <Button onClick={onCancel} disabled={busy} color="inherit">
          Отмена
        </Button>
        <Button onClick={onSave} disabled={busy} variant="outlined">
          Сохранить
        </Button>
        <Button onClick={onSend} disabled={busy} variant="contained">
          Отправить
        </Button>
      </DialogActions>
    </Dialog>
  );
}
