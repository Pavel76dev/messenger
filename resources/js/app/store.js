import { configureStore } from '@reduxjs/toolkit';
import authReducer from '../features/auth/authSlice';
import conversationsReducer from '../features/conversations/conversationsSlice';
import messagesReducer from '../features/messages/messagesSlice';
import callsReducer from '../features/calls/callsSlice';

export const store = configureStore({
  reducer: {
    auth: authReducer,
    conversations: conversationsReducer,
    messages: messagesReducer,
    calls: callsReducer,
  },
});
