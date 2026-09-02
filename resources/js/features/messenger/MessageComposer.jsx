import { useState } from 'react';
import { Box, IconButton, TextField } from '@mui/material';
import SendIcon from '@mui/icons-material/Send';
import { sendMessage } from '../messages/messagesSlice';
import { fetchConversations } from '../conversations/conversationsSlice';
import { useAppDispatch, useAppSelector } from '../../app/hooks';

export function MessageComposer({ conversationId }) {
  const dispatch = useAppDispatch();
  const sendStatus = useAppSelector((state) => state.messages.sendStatus);
  const [body, setBody] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    const trimmed = body.trim();
    if (!trimmed || sendStatus === 'loading') {
      return;
    }

    const result = await dispatch(
      sendMessage({ conversationId, body: trimmed }),
    );

    if (sendMessage.fulfilled.match(result)) {
      setBody('');
      dispatch(fetchConversations());
    }
  };

  return (
    <Box
      component="form"
      onSubmit={handleSubmit}
      sx={{
        display: 'flex',
        gap: 1,
        p: 1.5,
        borderTop: 1,
        borderColor: 'divider',
        bgcolor: 'background.paper',
      }}
    >
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
        disabled={!body.trim() || sendStatus === 'loading'}
        aria-label="Отправить"
      >
        <SendIcon />
      </IconButton>
    </Box>
  );
}
