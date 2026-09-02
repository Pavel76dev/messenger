import { useEffect } from 'react';
import { Navigate } from 'react-router-dom';
import { Box, CircularProgress } from '@mui/material';
import { fetchMe, markAuthInitialized } from './authSlice';
import { useAppDispatch, useAppSelector } from '../../app/hooks';

export function ProtectedRoute({ children }) {
  const dispatch = useAppDispatch();
  const { user, token, initialized, status } = useAppSelector((state) => state.auth);

  useEffect(() => {
    if (!initialized) {
      if (token) {
        dispatch(fetchMe());
      } else {
        dispatch(markAuthInitialized());
      }
    }
  }, [dispatch, token, initialized]);

  if (!initialized || (token && !user && status === 'loading')) {
    return (
      <Box
        sx={{
          minHeight: '100vh',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        <CircularProgress />
      </Box>
    );
  }

  if (!token || !user) {
    return <Navigate to="/login" replace />;
  }

  return children;
}
