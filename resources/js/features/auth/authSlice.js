import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { apiRequest, clearToken, getToken, setToken } from '../../shared/api/client';

export const register = createAsyncThunk(
  'auth/register',
  async (payload, { rejectWithValue }) => {
    try {
      const data = await apiRequest('/api/register', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setToken(data.token);
      return data;
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

export const login = createAsyncThunk(
  'auth/login',
  async (payload, { rejectWithValue }) => {
    try {
      const data = await apiRequest('/api/login', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setToken(data.token);
      return data;
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

export const fetchMe = createAsyncThunk(
  'auth/fetchMe',
  async (_, { rejectWithValue }) => {
    try {
      const data = await apiRequest('/api/me');
      return data;
    } catch (error) {
      clearToken();
      return rejectWithValue(error.message);
    }
  },
);

export const logout = createAsyncThunk('auth/logout', async () => {
  try {
    await apiRequest('/api/logout', { method: 'POST' });
  } catch {
    // ignore network/API errors on logout
  } finally {
    clearToken();
  }
});

const initialState = {
  user: null,
  token: getToken(),
  status: 'idle',
  error: null,
  initialized: false,
};

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    clearAuthError(state) {
      state.error = null;
    },
    markAuthInitialized(state) {
      state.initialized = true;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(register.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(register.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.user = action.payload.user;
        state.token = action.payload.token;
        state.initialized = true;
      })
      .addCase(register.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload || 'Не удалось зарегистрироваться';
      })
      .addCase(login.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(login.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.user = action.payload.user;
        state.token = action.payload.token;
        state.initialized = true;
      })
      .addCase(login.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload || 'Не удалось войти';
      })
      .addCase(fetchMe.pending, (state) => {
        state.status = 'loading';
      })
      .addCase(fetchMe.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.user = action.payload;
        state.initialized = true;
      })
      .addCase(fetchMe.rejected, (state) => {
        state.status = 'idle';
        state.user = null;
        state.token = null;
        state.initialized = true;
      })
      .addCase(logout.fulfilled, (state) => {
        state.user = null;
        state.token = null;
        state.status = 'idle';
        state.error = null;
        state.initialized = true;
      });
  },
});

export const { clearAuthError, markAuthInitialized } = authSlice.actions;
export default authSlice.reducer;
