import { useRef, useState } from 'react';
import {
  Box,
  Chip,
  IconButton,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import AttachFileIcon from '@mui/icons-material/AttachFile';
import SendIcon from '@mui/icons-material/Send';
import { sendMessage } from '../messages/messagesSlice';
import { fetchConversations } from '../conversations/conversationsSlice';
import { useAppDispatch, useAppSelector } from '../../app/hooks';

const MAX_FILES = 5;
const MAX_FILE_BYTES = 10 * 1024 * 1024;

function formatSize(bytes) {
  if (!bytes && bytes !== 0) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function MessageComposer({ conversationId }) {
  const dispatch = useAppDispatch();
  const sendStatus = useAppSelector((state) => state.messages.sendStatus);
  const sendError = useAppSelector((state) => state.messages.error);
  const [body, setBody] = useState('');
  const [files, setFiles] = useState([]);
  const [localError, setLocalError] = useState('');
  const fileInputRef = useRef(null);

  const canSend =
    (body.trim() || files.length > 0) && sendStatus !== 'loading';

  const handleFilesSelected = (event) => {
    const selected = Array.from(event.target.files || []);
    event.target.value = '';
    if (selected.length === 0) return;

    setLocalError('');
    const next = [...files];

    for (const file of selected) {
      if (next.length >= MAX_FILES) {
        setLocalError(`Не больше ${MAX_FILES} файлов на сообщение`);
        break;
      }
      if (file.size > MAX_FILE_BYTES) {
        setLocalError(`Файл «${file.name}» больше 10 MB`);
        continue;
      }
      next.push(file);
    }

    setFiles(next);
  };

  const removeFile = (index) => {
    setFiles((prev) => prev.filter((_, i) => i !== index));
    setLocalError('');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    const trimmed = body.trim();
    if ((!trimmed && files.length === 0) || sendStatus === 'loading') {
      return;
    }

    const result = await dispatch(
      sendMessage({
        conversationId,
        body: trimmed,
        files,
      }),
    );

    if (sendMessage.fulfilled.match(result)) {
      setBody('');
      setFiles([]);
      setLocalError('');
      dispatch(fetchConversations());
    }
  };

  return (
    <Box
      component="form"
      onSubmit={handleSubmit}
      sx={{
        p: 1.5,
        borderTop: 1,
        borderColor: 'divider',
        bgcolor: 'background.paper',
      }}
    >
      {files.length > 0 && (
        <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap" sx={{ mb: 1 }}>
          {files.map((file, index) => (
            <Chip
              key={`${file.name}-${file.size}-${index}`}
              label={`${file.name} (${formatSize(file.size)})`}
              onDelete={() => removeFile(index)}
              size="small"
            />
          ))}
        </Stack>
      )}

      {(localError || sendError) && (
        <Typography variant="caption" color="error" sx={{ display: 'block', mb: 1 }}>
          {localError || sendError}
        </Typography>
      )}

      <Box sx={{ display: 'flex', gap: 1, alignItems: 'flex-end' }}>
        <IconButton
          type="button"
          color="primary"
          disabled={sendStatus === 'loading'}
          aria-label="Прикрепить файл"
          onClick={() => fileInputRef.current?.click()}
        >
          <AttachFileIcon />
        </IconButton>
        <input
          ref={fileInputRef}
          type="file"
          multiple
          hidden
          onChange={handleFilesSelected}
        />
        <TextField
          value={body}
          onChange={(e) => setBody(e.target.value)}
          placeholder="Введите сообщение…"
          fullWidth
          size="small"
          multiline
          maxRows={4}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault();
              handleSubmit(event);
            }
          }}
        />
        <IconButton
          type="submit"
          color="primary"
          disabled={!canSend}
          aria-label="Отправить"
        >
          <SendIcon />
        </IconButton>
      </Box>
    </Box>
  );
}
