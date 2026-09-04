import {
  AppBar,
  Avatar,
  Box,
  Button,
  Toolbar,
  Typography,
} from '@mui/material';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import { logout } from '../../features/auth/authSlice';
import { useAppDispatch, useAppSelector } from '../../app/hooks';

function getInitials(name) {
  if (!name) return '?';
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('');
}

export function AppLayout({ children }) {
  const dispatch = useAppDispatch();
  const navigate = useNavigate();
  const user = useAppSelector((state) => state.auth.user);

  const handleLogout = async () => {
    await dispatch(logout());
    navigate('/login', { replace: true });
  };

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', height: '100vh' }}>
      <AppBar position="static" elevation={1} color="default">
        <Toolbar>
          <Typography
            variant="h6"
            component={RouterLink}
            to="/"
            sx={{ flexGrow: 1, textDecoration: 'none', color: 'inherit' }}
          >
            Мессенджер
          </Typography>
          {user && (
            <Button
              component={RouterLink}
              to="/profile"
              color="inherit"
              startIcon={
                <Avatar
                  src={user.avatar_url || undefined}
                  sx={{ width: 28, height: 28, fontSize: 12 }}
                >
                  {getInitials(user.name)}
                </Avatar>
              }
              sx={{ mr: 1, textTransform: 'none' }}
            >
              {user.name}
            </Button>
          )}
          <Button color="inherit" onClick={handleLogout}>
            Выйти
          </Button>
        </Toolbar>
      </AppBar>
      <Box sx={{ flex: 1, minHeight: 0, overflow: 'auto' }}>{children}</Box>
    </Box>
  );
}
