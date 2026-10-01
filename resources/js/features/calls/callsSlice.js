import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { apiRequest } from '../../shared/api/client';
import { extractCall, resolveCallId } from './callUtils';

function requireCallId(callId) {
  const id = resolveCallId(callId);
  if (id == null) {
    throw new Error('Некорректный идентификатор звонка.');
  }
  return id;
}

export const startCall = createAsyncThunk(
  'calls/start',
  async ({ conversationId, mediaType }) => {
    const id = resolveCallId(conversationId);
    if (id == null) {
      throw new Error('Некорректный диалог для звонка.');
    }
    return apiRequest(`/api/conversations/${id}/calls`, {
      method: 'POST',
      body: JSON.stringify({ media_type: mediaType }),
    });
  },
);

export const acceptCall = createAsyncThunk('calls/accept', async (callId) => {
  const id = requireCallId(callId);
  return apiRequest(`/api/calls/${id}/accept`, { method: 'POST' });
});

export const joinCall = createAsyncThunk('calls/join', async (callId) => {
  const id = requireCallId(callId);
  return apiRequest(`/api/calls/${id}/join`, { method: 'POST' });
});

export const rejectCall = createAsyncThunk('calls/reject', async (callId) => {
  const id = requireCallId(callId);
  return apiRequest(`/api/calls/${id}/reject`, { method: 'POST' });
});

export const endCall = createAsyncThunk('calls/end', async (callId) => {
  const id = requireCallId(callId);
  return apiRequest(`/api/calls/${id}/end`, { method: 'POST' });
});

export const fetchActiveCall = createAsyncThunk(
  'calls/fetchActive',
  async (conversationId) => {
    const id = resolveCallId(conversationId);
    if (id == null) {
      return { call: null };
    }
    return apiRequest(`/api/conversations/${id}/calls/active`);
  },
);

const initialState = {
  session: null, // { call, token, livekit_url, role: 'caller'|'callee'|'joiner' }
  incoming: null,
  conversationActive: null, // banner for selected conversation
  status: 'idle',
  error: null,
};

function applySession(state, payload, role) {
  const call = extractCall(payload?.call) || extractCall(payload);
  if (!call || !payload?.token) {
    state.status = 'idle';
    state.error = 'Сервер вернул некорректный ответ звонка.';
    return;
  }
  state.session = {
    call,
    token: payload.token,
    livekit_url: payload.livekit_url,
    role,
  };
  state.incoming = null;
  state.conversationActive = call;
  state.status = 'in_call';
  state.error = null;
}

const callsSlice = createSlice({
  name: 'calls',
  initialState,
  reducers: {
    setIncomingCall(state, action) {
      const call = extractCall(action.payload);
      if (!call) {
        state.incoming = null;
        return;
      }
      if (resolveCallId(state.session?.call?.id) === resolveCallId(call.id)) {
        return;
      }
      state.incoming = call;
      if (call.conversation_type === 'group') {
        state.conversationActive = call;
      }
    },
    clearIncomingCall(state) {
      state.incoming = null;
    },
    applyCallUpdate(state, action) {
      const call = extractCall(action.payload);
      if (!call) return;

      const callId = resolveCallId(call.id);

      if (resolveCallId(state.session?.call?.id) === callId) {
        state.session.call = call;
      }
      if (resolveCallId(state.incoming?.id) === callId) {
        if (call.status === 'ended' || call.status === 'rejected') {
          state.incoming = null;
        } else {
          state.incoming = call;
        }
      }
      if (
        resolveCallId(state.conversationActive?.id) === callId ||
        state.conversationActive?.conversation_id === call.conversation_id
      ) {
        if (call.status === 'ended' || call.status === 'rejected') {
          if (resolveCallId(state.conversationActive?.id) === callId) {
            state.conversationActive = null;
          }
        } else {
          state.conversationActive = call;
        }
      }
    },
    clearSession(state) {
      state.session = null;
      state.status = 'idle';
      state.error = null;
    },
    setConversationActive(state, action) {
      state.conversationActive = action.payload;
    },
    setCallError(state, action) {
      state.error = action.payload || null;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(startCall.pending, (state) => {
        state.status = 'starting';
        state.error = null;
      })
      .addCase(startCall.fulfilled, (state, action) => {
        applySession(state, action.payload, 'caller');
      })
      .addCase(startCall.rejected, (state, action) => {
        state.status = 'idle';
        state.error = action.error.message || 'Не удалось начать звонок';
      })
      .addCase(acceptCall.fulfilled, (state, action) => {
        applySession(state, action.payload, 'callee');
      })
      .addCase(acceptCall.rejected, (state, action) => {
        state.error = action.error.message || 'Не удалось принять звонок';
      })
      .addCase(joinCall.fulfilled, (state, action) => {
        applySession(state, action.payload, 'joiner');
      })
      .addCase(joinCall.rejected, (state, action) => {
        state.error = action.error.message || 'Не удалось присоединиться';
      })
      .addCase(rejectCall.fulfilled, (state) => {
        state.incoming = null;
        state.status = 'idle';
      })
      .addCase(endCall.fulfilled, (state, action) => {
        const ended = extractCall(action.payload?.call);
        state.session = null;
        state.incoming = null;
        if (
          !ended ||
          resolveCallId(state.conversationActive?.id) === resolveCallId(ended.id)
        ) {
          state.conversationActive = null;
        }
        state.status = 'idle';
        state.error = null;
      })
      .addCase(endCall.rejected, (state, action) => {
        state.error = action.error.message || 'Не удалось завершить звонок';
      })
      .addCase(fetchActiveCall.fulfilled, (state, action) => {
        const call = extractCall(action.payload?.call) || null;
        if (state.session && resolveCallId(state.session.call?.id)) {
          if (call && resolveCallId(call.id) === resolveCallId(state.session.call.id)) {
            state.conversationActive = call;
          }
          return;
        }
        state.conversationActive = call;
      });
  },
});

export const {
  setIncomingCall,
  clearIncomingCall,
  applyCallUpdate,
  clearSession,
  setConversationActive,
  setCallError,
} = callsSlice.actions;

export default callsSlice.reducer;
