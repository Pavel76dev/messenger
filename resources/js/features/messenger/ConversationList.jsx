import {
  Avatar,
  Box,
  CircularProgress,
  Divider,
  IconButton,
  List,
  ListItemAvatar,
  ListItemButton,
  ListItemText,
  Toolbar,
  Typography,
} from '@mui/material';
import AddCommentOutlinedIcon from '@mui/icons-material/AddCommentOutlined';

function formatPreview(conversation) {
  if (conversation.last_message?.body) {
    return conversation.last_message.body;
  }
  return 'Нет сообщений';
}

function getInitials(name) {
  if (!name) return '?';
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('');
}

export function ConversationList({
  conversations,
  selectedId,
  status,
  onSelect,
  onNewChat,
}) {
  return (
    <>
      <Toolbar sx={{ px: 2, gap: 1, justifyContent: 'space-between' }}>
        <Typography variant="h6">Диалоги</Typography>
        <IconButton color="primary" onClick={onNewChat} aria-label="Новый чат">
          <AddCommentOutlinedIcon />
        </IconButton>
      </Toolbar>
      <Divider />

      {status === 'loading' && conversations.length === 0 ? (
        <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
          <CircularProgress size={28} />
        </Box>
      ) : conversations.length === 0 ? (
        <Box sx={{ p: 3, textAlign: 'center' }}>
          <Typography color="text.secondary">
            Пока нет диалогов. Нажмите «+», чтобы начать переписку.
          </Typography>
        </Box>
      ) : (
        <List sx={{ overflow: 'auto', flex: 1, py: 0 }}>
          {conversations.map((conversation) => (
            <ListItemButton
              key={conversation.id}
              selected={conversation.id === selectedId}
              onClick={() => onSelect(conversation.id)}
              alignItems="flex-start"
            >
              <ListItemAvatar>
                <Avatar>{getInitials(conversation.peer?.name)}</Avatar>
              </ListItemAvatar>
              <ListItemText
                primary={conversation.peer?.name || 'Собеседник'}
                secondary={formatPreview(conversation)}
                primaryTypographyProps={{ noWrap: true }}
                secondaryTypographyProps={{ noWrap: true }}
              />
            </ListItemButton>
          ))}
        </List>
      )}
    </>
  );
}
