import { useEffect, useRef } from 'react';
import {
  AppBar,
  Box,
  CircularProgress,
  IconButton,
  Toolbar,
  Typography,
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';

function formatTime(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: 'short',
  });
}

export function MessageThread({
  conversation,
  messages,
  status,
  currentUserId,
  onBack,
}) {
  const bottomRef = useRef(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  if (!conversation) {
    return (
      <Box
        sx={{
          flex: 1,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          p: 4,
        }}
      >
        <Typography color="text.secondary" align="center">
          Выберите диалог слева или создайте новый чат
        </Typography>
      </Box>
    );
  }

  return (
    <Box sx={{ flex: 1, display: 'flex', flexDirection: 'column', minHeight: 0 }}>
      <AppBar position="static" color="transparent" elevation={0}>
        <Toolbar variant="dense" sx={{ borderBottom: 1, borderColor: 'divider' }}>
          <IconButton
            edge="start"
            onClick={onBack}
            sx={{ display: { sm: 'none' }, mr: 1 }}
            aria-label="Назад к списку"
          >
            <ArrowBackIcon />
          </IconButton>
          <Box>
            <Typography variant="subtitle1" fontWeight={600}>
              {conversation.peer?.name || 'Собеседник'}
            </Typography>
            {conversation.peer?.email && (
              <Typography variant="caption" color="text.secondary">
                {conversation.peer.email}
              </Typography>
            )}
          </Box>
        </Toolbar>
      </AppBar>

      <Box
        sx={{
          flex: 1,
          overflow: 'auto',
          p: 2,
          display: 'flex',
          flexDirection: 'column',
          gap: 1.5,
          bgcolor: 'grey.50',
        }}
      >
        {status === 'loading' && messages.length === 0 ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
            <CircularProgress size={28} />
          </Box>
        ) : messages.length === 0 ? (
          <Typography color="text.secondary" align="center" sx={{ mt: 4 }}>
            Сообщений пока нет. Напишите первое!
          </Typography>
        ) : (
          messages.map((message) => {
            const isMine = message.user_id === currentUserId;
            return (
              <Box
                key={message.id}
                sx={{
                  alignSelf: isMine ? 'flex-end' : 'flex-start',
                  maxWidth: '75%',
                }}
              >
                <Box
                  sx={{
                    px: 1.5,
                    py: 1,
                    borderRadius: 2,
                    bgcolor: isMine ? 'primary.main' : 'background.paper',
                    color: isMine ? 'primary.contrastText' : 'text.primary',
                    boxShadow: 1,
                  }}
                >
                  {!isMine && message.user?.name && (
                    <Typography variant="caption" sx={{ opacity: 0.8, display: 'block' }}>
                      {message.user.name}
                    </Typography>
                  )}
                  <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                    {message.body}
                  </Typography>
                  <Typography
                    variant="caption"
                    sx={{
                      display: 'block',
                      mt: 0.5,
                      opacity: 0.75,
                      textAlign: 'right',
                    }}
                  >
                    {formatTime(message.created_at)}
                  </Typography>
                </Box>
              </Box>
            );
          })
        )}
        <div ref={bottomRef} />
      </Box>
    </Box>
  );
}
