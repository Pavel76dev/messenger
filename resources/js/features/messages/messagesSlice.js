import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { apiRequest } from '../../shared/api/client';

export const fetchMessages = createAsyncThunk(
  'messages/fetch',
  async ({ conversationId, afterId }, { rejectWithValue }) => {
    try {
      const query = afterId ? `?after_id=${afterId}` : '';
      const data = await apiRequest(
        `/api/conversations/${conversationId}/messages${query}`,
      );
      const messages = Array.isArray(data) ? data : data.messages || data.data || [];
      return { conversationId, messages, append: Boolean(afterId) };
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

export const sendMessage = createAsyncThunk(
  'messages/send',
  async ({ conversationId, body }, { rejectWithValue }) => {
    try {
      const data = await apiRequest(`/api/conversations/${conversationId}/messages`, {
        method: 'POST',
        body: JSON.stringify({ body }),
      });
      const message = data.message || data;
      return { conversationId, message };
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

const messagesSlice = createSlice({
  name: 'messages',
  initialState: {
    byConversationId: {},
    statusByConversationId: {},
    error: null,
    sendStatus: 'idle',
  },
  reducers: {
    clearMessagesError(state) {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchMessages.pending, (state, action) => {
        const { conversationId, afterId } = action.meta.arg;
        if (!afterId) {
          state.statusByConversationId[conversationId] = 'loading';
        }
        state.error = null;
      })
      .addCase(fetchMessages.fulfilled, (state, action) => {
        const { conversationId, messages, append } = action.payload;
        state.statusByConversationId[conversationId] = 'succeeded';
        const existing = state.byConversationId[conversationId] || [];

        if (!append) {
          state.byConversationId[conversationId] = messages;
          return;
        }

        const knownIds = new Set(existing.map((item) => item.id));
        const fresh = messages.filter((item) => !knownIds.has(item.id));
        state.byConversationId[conversationId] = [...existing, ...fresh];
      })
      .addCase(fetchMessages.rejected, (state, action) => {
        const { conversationId } = action.meta.arg;
        state.statusByConversationId[conversationId] = 'failed';
        state.error = action.payload || 'Не удалось загрузить сообщения';
      })
      .addCase(sendMessage.pending, (state) => {
        state.sendStatus = 'loading';
        state.error = null;
      })
      .addCase(sendMessage.fulfilled, (state, action) => {
        state.sendStatus = 'succeeded';
        const { conversationId, message } = action.payload;
        const existing = state.byConversationId[conversationId] || [];
        if (!existing.some((item) => item.id === message.id)) {
          state.byConversationId[conversationId] = [...existing, message];
        }
      })
      .addCase(sendMessage.rejected, (state, action) => {
        state.sendStatus = 'failed';
        state.error = action.payload || 'Не удалось отправить сообщение';
      });
  },
});

export const { clearMessagesError } = messagesSlice.actions;
export default messagesSlice.reducer;
