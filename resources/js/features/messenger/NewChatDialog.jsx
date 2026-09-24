import { useEffect, useState } from 'react';
import {
  Avatar,
  Box,
  Button,
  Checkbox,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  List,
  ListItemAvatar,
  ListItemButton,
  ListItemText,
  Tab,
  Tabs,
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
  const [mode, setMode] = useState('direct');
  const [query, setQuery] = useState('');
  const [groupTitle, setGroupTitle] = useState('');
  const [selectedIds, setSelectedIds] = useState([]);
  const [creating, setCreating] = useState(false);
  const [localError, setLocalError] = useState('');

  useEffect(() => {
    if (!open) {
      setQuery('');
      setMode('direct');
      setGroupTitle('');
      setSelectedIds([]);
      setLocalError('');
      dispatch(clearUserSearch());
      return undefined;
    }

    const trimmed = query.trim();
    const timer = setTimeout(() => {
      dispatch(searchUsers(trimmed));
    }, trimmed.length === 0 ? 0 : 300);

    return () => clearTimeout(timer);
  }, [open, query, dispatch]);

  const handleClose = () => {
    if (!creating) {
      onClose();
    }
  };

  const filteredResults = results.filter((user) => user.id !== currentUser?.id);

  const toggleMember = (userId) => {
    setLocalError('');
    setSelectedIds((prev) =>
      prev.includes(userId) ? prev.filter((id) => id !== userId) : [...prev, userId],
    );
  };

  const handleSelectUser = async (userId) => {
    if (mode === 'group') {
      toggleMember(userId);
      return;
    }

    setCreating(true);
    setLocalError('');
    const result = await dispatch(createConversation({ user_id: userId }));
    setCreating(false);
    if (createConversation.fulfilled.match(result)) {
      onClose();
    } else if (createConversation.rejected.match(result)) {
      setLocalError(result.payload || 'Не удалось создать диалог');
    }
  };

  const handleCreateGroup = async () => {
    const title = groupTitle.trim();
    if (!title) {
      setLocalError('Укажите название группы');
      return;
    }
    if (selectedIds.length < 2) {
      setLocalError('Выберите минимум двух участников');
      return;
    }

    setCreating(true);
    setLocalError('');
    const result = await dispatch(
      createConversation({
        title,
        user_ids: selectedIds,
      }),
    );
    setCreating(false);
    if (createConversation.fulfilled.match(result)) {
      onClose();
    } else if (createConversation.rejected.match(result)) {
      setLocalError(result.payload || 'Не удалось создать группу');
    }
  };

  const canCreateGroup =
    groupTitle.trim().length > 0 && selectedIds.length >= 2 && !creating;

  return (
    <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
      <DialogTitle>Новый чат</DialogTitle>
      <DialogContent>
        <Tabs
          value={mode}
          onChange={(_, value) => {
            setMode(value);
            setLocalError('');
            setSelectedIds([]);
            setGroupTitle('');
          }}
          sx={{ mb: 1 }}
        >
          <Tab value="direct" label="Личный" />
          <Tab value="group" label="Группа" />
        </Tabs>

        {mode === 'group' && (
          <TextField
            margin="dense"
            label="Название группы"
            fullWidth
            value={groupTitle}
            onChange={(e) => {
              setGroupTitle(e.target.value);
              setLocalError('');
            }}
            disabled={creating}
          />
        )}

        <TextField
          autoFocus={mode === 'direct'}
          margin="dense"
          label="Поиск пользователя"
          placeholder="Имя или email"
          fullWidth
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          disabled={creating}
        />

        {mode === 'group' && (
          <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 1 }}>
            Выбрано: {selectedIds.length} (нужно минимум 2)
          </Typography>
        )}

        {(error || localError) && (
          <Typography color="error" variant="body2" sx={{ mt: 1 }}>
            {localError || error}
          </Typography>
        )}

        <Box sx={{ mt: 2, minHeight: 160 }}>
          {status === 'loading' || creating ? (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}>
              <CircularProgress size={28} />
            </Box>
          ) : filteredResults.length === 0 && status === 'succeeded' ? (
            <Typography color="text.secondary" align="center" sx={{ py: 3 }}>
              {query.trim()
                ? 'Пользователи не найдены'
                : 'Нет других пользователей для чата'}
            </Typography>
          ) : (
            <List dense>
              {filteredResults.map((user) => {
                const checked = selectedIds.includes(user.id);
                return (
                  <ListItemButton
                    key={user.id}
                    onClick={() => handleSelectUser(user.id)}
                    disabled={creating}
                  >
                    {mode === 'group' && (
                      <Checkbox
                        edge="start"
                        checked={checked}
                        tabIndex={-1}
                        disableRipple
                        sx={{ mr: 1 }}
                      />
                    )}
                    <ListItemAvatar>
                      <Avatar src={user.avatar_url || undefined}>
                        {user.name?.[0]?.toUpperCase() || '?'}
                      </Avatar>
                    </ListItemAvatar>
                    <ListItemText primary={user.name} secondary={user.email} />
                  </ListItemButton>
                );
              })}
            </List>
          )}
        </Box>
      </DialogContent>
      <DialogActions>
        <Button onClick={handleClose} disabled={creating}>
          Отмена
        </Button>
        {mode === 'group' && (
          <Button
            variant="contained"
            onClick={handleCreateGroup}
            disabled={!canCreateGroup}
          >
            Создать группу
          </Button>
        )}
      </DialogActions>
    </Dialog>
  );
}
