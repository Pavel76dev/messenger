import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { apiRequest } from '../../shared/api/client';

export const fetchConversations = createAsyncThunk(
  'conversations/fetchAll',
  async (_, { rejectWithValue }) => {
    try {
      const data = await apiRequest('/api/conversations');
      return Array.isArray(data) ? data : data.conversations || data.data || [];
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

export const createConversation = createAsyncThunk(
  'conversations/create',
  async (payload, { rejectWithValue }) => {
    try {
      const body =
        typeof payload === 'number' || typeof payload === 'string'
          ? { user_id: Number(payload) }
          : payload;

      const data = await apiRequest('/api/conversations', {
        method: 'POST',
        body: JSON.stringify(body),
      });
      return data.conversation || data;
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

export const searchUsers = createAsyncThunk(
  'conversations/searchUsers',
  async (query, { rejectWithValue }) => {
    try {
      const data = await apiRequest(`/api/users?q=${encodeURIComponent(query)}`);
      return Array.isArray(data) ? data : data.users || data.data || [];
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

const conversationsSlice = createSlice({
  name: 'conversations',
  initialState: {
    items: [],
    selectedId: null,
    status: 'idle',
    error: null,
    userSearch: {
      results: [],
      status: 'idle',
      error: null,
    },
  },
  reducers: {
    selectConversation(state, action) {
      state.selectedId = action.payload;
    },
    clearUserSearch(state) {
      state.userSearch = { results: [], status: 'idle', error: null };
    },
    upsertConversation(state, action) {
      const conversation = action.payload;
      if (!conversation?.id) return;
      const rest = state.items.filter((item) => item.id !== conversation.id);
      state.items = [conversation, ...rest];
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchConversations.pending, (state) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(fetchConversations.fulfilled, (state, action) => {
        state.status = 'succeeded';
        state.items = action.payload;
      })
      .addCase(fetchConversations.rejected, (state, action) => {
        state.status = 'failed';
        state.error = action.payload || 'Не удалось загрузить диалоги';
      })
      .addCase(createConversation.fulfilled, (state, action) => {
        const conversation = action.payload;
        const index = state.items.findIndex((item) => item.id === conversation.id);
        if (index >= 0) {
          state.items[index] = conversation;
        } else {
          state.items.unshift(conversation);
        }
        state.selectedId = conversation.id;
      })
      .addCase(searchUsers.pending, (state) => {
        state.userSearch.status = 'loading';
        state.userSearch.error = null;
      })
      .addCase(searchUsers.fulfilled, (state, action) => {
        state.userSearch.status = 'succeeded';
        state.userSearch.results = action.payload;
      })
      .addCase(searchUsers.rejected, (state, action) => {
        state.userSearch.status = 'failed';
        state.userSearch.error = action.payload || 'Не удалось найти пользователей';
      });
  },
});

export const { selectConversation, clearUserSearch, upsertConversation } =
  conversationsSlice.actions;
export default conversationsSlice.reducer;
