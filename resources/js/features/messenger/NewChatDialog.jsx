import { useEffect, useState } from 'react';
import {
  Avatar,
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  List,
  ListItemAvatar,
  ListItemButton,
  ListItemText,
  TextField,
  Typography,
} from '@mui/material';
import {
  clearUserSearch,
  createConversation,
  searchUsers,
} from '../conversations/conversationsSlice';
import { useAppDispatch, useAppSelector } from '../../app/hooks';

export function NewChatDialog({ open, onClose }) {
  const dispatch = useAppDispatch();
  const currentUser = useAppSelector((state) => state.auth.user);
  const { results, status, error } = useAppSelector(
    (state) => state.conversations.userSearch,
  );
  const [query, setQuery] = useState('');
  const [creating, setCreating] = useState(false);

  useEffect(() => {
    if (!open) {
      setQuery('');
      dispatch(clearUserSearch());
      return undefined;
    }

    const trimmed = query.trim();
    if (trimmed.length < 1) {
      dispatch(clearUserSearch());
      return undefined;
    }

    const timer = setTimeout(() => {
      dispatch(searchUsers(trimmed));
    }, 300);

    return () => clearTimeout(timer);
  }, [open, query, dispatch]);

  const handleClose = () => {
    if (!creating) {
      onClose();
    }
  };

  const handleSelectUser = async (userId) => {
    setCreating(true);
    const result = await dispatch(createConversation(userId));
    setCreating(false);
    if (createConversation.fulfilled.match(result)) {
      onClose();
    }
  };

  const filteredResults = results.filter((user) => user.id !== currentUser?.id);

  return (
    <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
      <DialogTitle>Новый чат</DialogTitle>
      <DialogContent>
        <TextField
          autoFocus
          margin="dense"
          label="Поиск пользователя"
          placeholder="Имя или email"
          fullWidth
          value={query}
          onChange={(e) => setQuery(e.target.value)}
        />

        {error && (
          <Typography color="error" variant="body2" sx={{ mt: 1 }}>
            {error}
          </Typography>
        )}

        <Box sx={{ mt: 2, minHeight: 160 }}>
          {status === 'loading' || creating ? (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}>
              <CircularProgress size={28} />
            </Box>
          ) : query.trim() && filteredResults.length === 0 && status === 'succeeded' ? (
            <Typography color="text.secondary" align="center" sx={{ py: 3 }}>
              Пользователи не найдены
            </Typography>
          ) : (
            <List dense>
              {filteredResults.map((user) => (
                <ListItemButton
                  key={user.id}
                  onClick={() => handleSelectUser(user.id)}
                  disabled={creating}
                >
                  <ListItemAvatar>
                    <Avatar src={user.avatar_url || undefined}>
                      {user.name?.[0]?.toUpperCase() || '?'}
                    </Avatar>
                  </ListItemAvatar>
                  <ListItemText primary={user.name} secondary={user.email} />
                </ListItemButton>
              ))}
            </List>
          )}
        </Box>
      </DialogContent>
      <DialogActions>
        <Button onClick={handleClose} disabled={creating}>
          Отмена
        </Button>
      </DialogActions>
    </Dialog>
  );
}
