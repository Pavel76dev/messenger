import { useEffect, useRef } from 'react';
import {
  AppBar,
  Avatar,
  Box,
  CircularProgress,
  IconButton,
  Link,
  Stack,
  Toolbar,
  Typography,
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import InsertDriveFileOutlinedIcon from '@mui/icons-material/InsertDriveFileOutlined';

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

function formatSize(bytes) {
  if (!bytes && bytes !== 0) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function isImageMime(mime) {
  return typeof mime === 'string' && mime.startsWith('image/');
}

function getInitials(name) {
  if (!name) return '?';
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('');
}

function MessageAttachments({ attachments, isMine }) {
  if (!attachments?.length) return null;

  return (
    <Stack spacing={1} sx={{ mt: 1 }}>
      {attachments.map((attachment) =>
        isImageMime(attachment.mime) ? (
          <Box key={attachment.id}>
            <Box
              component="a"
              href={attachment.url}
              target="_blank"
              rel="noopener noreferrer"
              sx={{ display: 'block' }}
            >
              <Box
                component="img"
                src={attachment.url}
                alt={attachment.original_name}
                sx={{
                  maxWidth: '100%',
                  maxHeight: 240,
                  borderRadius: 1,
                  display: 'block',
                }}
              />
            </Box>
          </Box>
        ) : (
          <Link
            key={attachment.id}
            href={attachment.url}
            download={attachment.original_name}
            target="_blank"
            rel="noopener noreferrer"
            underline="hover"
            sx={{
              display: 'flex',
              alignItems: 'center',
              gap: 0.75,
              color: isMine ? 'inherit' : 'primary.main',
            }}
          >
            <InsertDriveFileOutlinedIcon fontSize="small" />
            <Box>
              <Typography variant="body2" sx={{ wordBreak: 'break-word' }}>
                {attachment.original_name}
              </Typography>
              <Typography variant="caption" sx={{ opacity: 0.8 }}>
                {formatSize(attachment.size)} · Скачать
              </Typography>
            </Box>
          </Link>
        ),
      )}
    </Stack>
  );
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
        <Toolbar variant="dense" sx={{ borderBottom: 1, borderColor: 'divider', gap: 1.5 }}>
          <IconButton
            edge="start"
            onClick={onBack}
            sx={{ display: { sm: 'none' }, mr: 0.5 }}
            aria-label="Назад к списку"
          >
            <ArrowBackIcon />
          </IconButton>
          <Avatar
            src={conversation.peer?.avatar_url || undefined}
            sx={{ width: 36, height: 36 }}
          >
            {getInitials(conversation.peer?.name)}
          </Avatar>
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
                  display: 'flex',
                  gap: 1,
                  flexDirection: isMine ? 'row-reverse' : 'row',
                }}
              >
                {!isMine && (
                  <Avatar
                    src={message.user?.avatar_url || undefined}
                    sx={{ width: 28, height: 28, mt: 0.5, fontSize: 12 }}
                  >
                    {getInitials(message.user?.name)}
                  </Avatar>
                )}
                <Box
                  sx={{
                    px: 1.5,
                    py: 1,
                    borderRadius: 2,
                    bgcolor: isMine ? 'primary.main' : 'background.paper',
                    color: isMine ? 'primary.contrastText' : 'text.primary',
                    boxShadow: 1,
                    minWidth: 0,
                  }}
                >
                  {!isMine && message.user?.name && (
                    <Typography variant="caption" sx={{ opacity: 0.8, display: 'block' }}>
                      {message.user.name}
                    </Typography>
                  )}
                  {message.body && (
                    <Typography
                      variant="body1"
                      sx={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}
                    >
                      {message.body}
                    </Typography>
                  )}
                  <MessageAttachments
                    attachments={message.attachments}
                    isMine={isMine}
                  />
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
