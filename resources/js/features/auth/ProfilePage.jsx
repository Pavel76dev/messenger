import { useEffect, useRef, useState } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import {
  Alert,
  Avatar,
  Box,
  Button,
  Container,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import {
  clearAuthError,
  deleteAvatar,
  updateProfile,
  uploadAvatar,
} from './authSlice';
import { useAppDispatch, useAppSelector } from '../../app/hooks';

function getInitials(name) {
  if (!name) return '?';
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('');
}

export function ProfilePage() {
  const dispatch = useAppDispatch();
  const { user, profileStatus, error } = useAppSelector((state) => state.auth);
  const fileInputRef = useRef(null);

  const [name, setName] = useState(user?.name || '');
  const [email, setEmail] = useState(user?.email || '');
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [success, setSuccess] = useState('');

  useEffect(() => {
    dispatch(clearAuthError());
  }, [dispatch]);

  useEffect(() => {
    if (user) {
      setName(user.name || '');
      setEmail(user.email || '');
    }
  }, [user]);

  const busy = profileStatus === 'loading';

  const handleSave = async (event) => {
    event.preventDefault();
    setSuccess('');

    const payload = { name, email };
    if (password) {
      payload.password = password;
      payload.password_confirmation = passwordConfirmation;
      payload.current_password = currentPassword;
    }

    const result = await dispatch(updateProfile(payload));
    if (updateProfile.fulfilled.match(result)) {
      setCurrentPassword('');
      setPassword('');
      setPasswordConfirmation('');
      setSuccess('Профиль сохранён');
    }
  };

  const handleAvatarChange = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    setSuccess('');
    const result = await dispatch(uploadAvatar(file));
    if (uploadAvatar.fulfilled.match(result)) {
      setSuccess('Фото обновлено');
    }
  };

  const handleDeleteAvatar = async () => {
    setSuccess('');
    const result = await dispatch(deleteAvatar());
    if (deleteAvatar.fulfilled.match(result)) {
      setSuccess('Фото удалено');
    }
  };

  return (
    <Container maxWidth="sm" sx={{ py: 4 }}>
      <Button component={RouterLink} to="/" sx={{ mb: 2 }}>
        ← К чатам
      </Button>

      <Paper sx={{ p: 3 }}>
        <Typography variant="h5" gutterBottom>
          Профиль
        </Typography>

        <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 3 }}>
          <Avatar
            src={user?.avatar_url || undefined}
            sx={{ width: 72, height: 72 }}
          >
            {getInitials(user?.name)}
          </Avatar>
          <Stack spacing={1}>
            <Button
              variant="outlined"
              size="small"
              disabled={busy}
              onClick={() => fileInputRef.current?.click()}
            >
              Загрузить фото
            </Button>
            <Button
              color="inherit"
              size="small"
              disabled={busy || !user?.avatar_url}
              onClick={handleDeleteAvatar}
            >
              Удалить фото
            </Button>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              hidden
              onChange={handleAvatarChange}
            />
          </Stack>
        </Stack>

        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}
        {success && (
          <Alert severity="success" sx={{ mb: 2 }}>
            {success}
          </Alert>
        )}

        <Box component="form" onSubmit={handleSave}>
          <Stack spacing={2}>
            <TextField
              label="Имя"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              fullWidth
            />
            <TextField
              label="Email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              fullWidth
            />
            <Typography variant="subtitle2" color="text.secondary" sx={{ pt: 1 }}>
              Смена пароля (необязательно)
            </Typography>
            <TextField
              label="Текущий пароль"
              type="password"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              fullWidth
              autoComplete="current-password"
            />
            <TextField
              label="Новый пароль"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              fullWidth
              autoComplete="new-password"
            />
            <TextField
              label="Подтверждение пароля"
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              fullWidth
              autoComplete="new-password"
            />
            <Button type="submit" variant="contained" disabled={busy}>
              Сохранить
            </Button>
          </Stack>
        </Box>
      </Paper>
    </Container>
  );
}
