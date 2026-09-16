import { useEffect, useRef, useState } from 'react';
import {
  AppBar,
  Avatar,
  Box,
  Button,
  ClickAwayListener,
  CircularProgress,
  IconButton,
  Link,
  Paper,
  Popper,
  Stack,
  Toolbar,
  Typography,
} from '@mui/material';
import AddReactionOutlinedIcon from '@mui/icons-material/AddReactionOutlined';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import InsertDriveFileOutlinedIcon from '@mui/icons-material/InsertDriveFileOutlined';
import { useAppDispatch } from '../../app/hooks';
import { REACTION_OPTIONS, toggleMessageReaction } from '../messages/messagesSlice';

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

function MessageReactions({ message }) {
  const dispatch = useAppDispatch();
  const [anchorEl, setAnchorEl] = useState(null);
  const reactions = Array.isArray(message.reactions) ? message.reactions : [];

  const handleToggle = (reactionKey) => {
    dispatch(toggleMessageReaction({ messageId: message.id, reactionKey }));
    setAnchorEl(null);
  };

  return (
    <Stack direction="row" spacing={0.75} sx={{ mt: 0.75 }} alignItems="center" useFlexGap flexWrap="wrap">
      {reactions.map((reaction) => (
        <Button
          key={reaction.reaction_key}
          size="small"
          variant={reaction.reacted_by_me ? 'contained' : 'outlined'}
          color={reaction.reacted_by_me ? 'primary' : 'inherit'}
          onClick={() => handleToggle(reaction.reaction_key)}
          sx={{
            minWidth: 'auto',
            px: 0.9,
            py: 0.15,
            borderRadius: 999,
            lineHeight: 1,
            textTransform: 'none',
            gap: 0.6,
          }}
        >
          {reaction.emoji}
          <Typography component="span" variant="caption">
            {reaction.count}
          </Typography>
        </Button>
      ))}

      <IconButton
        size="small"
        aria-label="Добавить реакцию"
        onClick={(event) => setAnchorEl(event.currentTarget)}
      >
        <AddReactionOutlinedIcon fontSize="small" />
      </IconButton>

      <Popper open={Boolean(anchorEl)} anchorEl={anchorEl} placement="top-start" sx={{ zIndex: 1300 }}>
        <ClickAwayListener onClickAway={() => setAnchorEl(null)}>
          <Paper elevation={4} sx={{ p: 0.75, borderRadius: 2 }}>
            <Stack direction="row" spacing={0.5}>
              {REACTION_OPTIONS.map((option) => (
                <IconButton
                  key={option.key}
                  size="small"
                  aria-label={`Реакция ${option.emoji}`}
                  onClick={() => handleToggle(option.key)}
                  sx={{ fontSize: 18, lineHeight: 1 }}
                >
                  {option.emoji}
                </IconButton>
              ))}
            </Stack>
          </Paper>
        </ClickAwayListener>
      </Popper>
    </Stack>
  );
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
  const lastMessageId = messages.length ? messages[messages.length - 1].id : null;

  // Скроллим только при новом сообщении, не при silent-poll реакций
  useEffect(() => {
    if (!lastMessageId) return;
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [lastMessageId]);

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
                  <MessageReactions message={message} />
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
