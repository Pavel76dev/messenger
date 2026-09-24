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
  if (conversation.last_message) {
    return 'Вложение';
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

function conversationTitle(conversation) {
  if (conversation.type === 'group') {
    return conversation.title || 'Группа';
  }
  return conversation.peer?.name || 'Собеседник';
}

function conversationSubtitle(conversation) {
  if (conversation.type === 'group') {
    const count = conversation.members_count || 0;
    return `${count} уч. · ${formatPreview(conversation)}`;
  }
  return formatPreview(conversation);
}

function conversationAvatar(conversation) {
  if (conversation.type === 'group') {
    return getInitials(conversation.title || 'Гр');
  }
  return getInitials(conversation.peer?.name);
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
                <Avatar
                  src={
                    conversation.type === 'group'
                      ? undefined
                      : conversation.peer?.avatar_url || undefined
                  }
                >
                  {conversationAvatar(conversation)}
                </Avatar>
              </ListItemAvatar>
              <ListItemText
                primary={conversationTitle(conversation)}
                secondary={conversationSubtitle(conversation)}
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
