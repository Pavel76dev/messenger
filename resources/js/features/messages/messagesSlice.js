import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { apiRequest } from '../../shared/api/client';

export const REACTION_OPTIONS = [
  { key: 'like', emoji: '👍' },
  { key: 'love', emoji: '❤️' },
  { key: 'laugh', emoji: '😂' },
  { key: 'surprised', emoji: '😮' },
  { key: 'sad', emoji: '😢' },
  { key: 'angry', emoji: '😡' },
];

function upsertMessage(list, message) {
  const existing = list || [];
  const index = existing.findIndex((item) => item.id === message.id);
  if (index === -1) {
    return [...existing, message].sort((a, b) => a.id - b.id);
  }
  const next = existing.slice();
  next[index] = message;
  return next;
}

export const fetchMessages = createAsyncThunk(
  'messages/fetch',
  async ({ conversationId, afterId, silent = false }, { rejectWithValue }) => {
    try {
      const query = afterId ? `?after_id=${afterId}` : '';
      const data = await apiRequest(
        `/api/conversations/${conversationId}/messages${query}`,
      );
      const messages = Array.isArray(data) ? data : data.messages || data.data || [];
      return {
        conversationId,
        messages,
        append: Boolean(afterId),
        silent: Boolean(silent),
      };
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

export const sendMessage = createAsyncThunk(
  'messages/send',
  async ({ conversationId, body, files = [] }, { rejectWithValue }) => {
    try {
      const formData = new FormData();
      if (body) {
        formData.append('body', body);
      }
      files.forEach((file) => {
        formData.append('files[]', file);
      });

      const data = await apiRequest(`/api/conversations/${conversationId}/messages`, {
        method: 'POST',
        body: formData,
      });
      const message = data.message || data;
      return { conversationId, message };
    } catch (error) {
      return rejectWithValue(error.message);
    }
  },
);

export const toggleMessageReaction = createAsyncThunk(
  'messages/toggleReaction',
  async ({ messageId, reactionKey }, { rejectWithValue }) => {
    try {
      const data = await apiRequest(`/api/messages/${messageId}/reactions`, {
        method: 'POST',
        body: JSON.stringify({ reaction_key: reactionKey }),
      });
      return {
        message: data.message || data,
      };
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
    realtimeConnected: false,
  },
  reducers: {
    clearMessagesError(state) {
      state.error = null;
    },
    setRealtimeConnected(state, action) {
      state.realtimeConnected = Boolean(action.payload);
    },
    applyRealtimeMessage(state, action) {
      const message = action.payload;
      if (!message?.conversation_id || !message?.id) return;
      const conversationId = message.conversation_id;
      state.byConversationId[conversationId] = upsertMessage(
        state.byConversationId[conversationId],
        message,
      );
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchMessages.pending, (state, action) => {
        const { conversationId, afterId, silent } = action.meta.arg;
        if (!afterId && !silent) {
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

        const byId = new Map(existing.map((item) => [item.id, item]));
        messages.forEach((item) => {
          byId.set(item.id, item);
        });
        state.byConversationId[conversationId] = Array.from(byId.values()).sort(
          (a, b) => a.id - b.id,
        );
      })
      .addCase(fetchMessages.rejected, (state, action) => {
        const { conversationId, silent } = action.meta.arg;
        if (!silent) {
          state.statusByConversationId[conversationId] = 'failed';
          state.error = action.payload || 'Не удалось загрузить сообщения';
        }
      })
      .addCase(sendMessage.pending, (state) => {
        state.sendStatus = 'loading';
        state.error = null;
      })
      .addCase(sendMessage.fulfilled, (state, action) => {
        state.sendStatus = 'succeeded';
        const { conversationId, message } = action.payload;
        state.byConversationId[conversationId] = upsertMessage(
          state.byConversationId[conversationId],
          message,
        );
      })
      .addCase(sendMessage.rejected, (state, action) => {
        state.sendStatus = 'failed';
        state.error = action.payload || 'Не удалось отправить сообщение';
      })
      .addCase(toggleMessageReaction.rejected, (state, action) => {
        state.error = action.payload || 'Не удалось обновить реакцию';
      })
      .addCase(toggleMessageReaction.fulfilled, (state, action) => {
        const message = action.payload.message;
        if (!message?.conversation_id || !message?.id) {
          return;
        }
        state.byConversationId[message.conversation_id] = upsertMessage(
          state.byConversationId[message.conversation_id],
          message,
        );
      });
  },
});

export const {
  clearMessagesError,
  setRealtimeConnected,
  applyRealtimeMessage,
} = messagesSlice.actions;
export default messagesSlice.reducer;
