import { useEffect, useState } from 'react';
import { Alert, Box, Paper } from '@mui/material';
import { fetchConversations, selectConversation } from '../conversations/conversationsSlice';
import {
  applyRealtimeMessage,
  fetchMessages,
  setRealtimeConnected,
} from '../messages/messagesSlice';
import { useAppDispatch, useAppSelector } from '../../app/hooks';
import {
  adaptRealtimeMessage,
  getEcho,
  isRealtimeEnabled,
  refreshEchoAuth,
} from '../../shared/realtime/echo';
import { ConversationList } from './ConversationList';
import { MessageThread } from './MessageThread';
import { MessageComposer } from './MessageComposer';
import { NewChatDialog } from './NewChatDialog';

export function MessengerPage() {
  const dispatch = useAppDispatch();
  const [newChatOpen, setNewChatOpen] = useState(false);
  const { items, selectedId, status, error } = useAppSelector((state) => state.conversations);
  const messagesError = useAppSelector((state) => state.messages.error);
  const realtimeConnected = useAppSelector((state) => state.messages.realtimeConnected);
  const currentUser = useAppSelector((state) => state.auth.user);
  const messagesById = useAppSelector((state) => state.messages.byConversationId);

  const selectedConversation = items.find((item) => item.id === selectedId) || null;
  const messages = messagesById[selectedId] || [];
  const messagesStatus = useAppSelector(
    (state) => state.messages.statusByConversationId[selectedId] || 'idle',
  );

  useEffect(() => {
    dispatch(fetchConversations());
  }, [dispatch]);

  useEffect(() => {
    if (!selectedId) {
      return undefined;
    }

    dispatch(fetchMessages({ conversationId: selectedId }));

    // Fallback polling: реже, если WebSocket подключён
    const intervalMs = realtimeConnected ? 15000 : 3000;
    const intervalId = setInterval(() => {
      dispatch(
        fetchMessages({
          conversationId: selectedId,
          silent: true,
        }),
      );
    }, intervalMs);

    return () => clearInterval(intervalId);
  }, [dispatch, selectedId, realtimeConnected]);

  useEffect(() => {
    if (!selectedId || !currentUser?.id || !isRealtimeEnabled()) {
      dispatch(setRealtimeConnected(false));
      return undefined;
    }

    refreshEchoAuth();
    const echo = getEcho();
    if (!echo) {
      dispatch(setRealtimeConnected(false));
      return undefined;
    }

    const channelName = `conversation.${selectedId}`;
    const channel = echo.private(channelName);

    const onConnected = () => dispatch(setRealtimeConnected(true));
    const onDisconnected = () => dispatch(setRealtimeConnected(false));

    echo.connector?.pusher?.connection?.bind('connected', onConnected);
    echo.connector?.pusher?.connection?.bind('disconnected', onDisconnected);
    echo.connector?.pusher?.connection?.bind('unavailable', onDisconnected);

    if (echo.connector?.pusher?.connection?.state === 'connected') {
      dispatch(setRealtimeConnected(true));
    }

    const handleIncoming = (payload) => {
      const adapted = adaptRealtimeMessage(payload?.message, currentUser.id);
      if (adapted) {
        dispatch(applyRealtimeMessage(adapted));
        dispatch(fetchConversations());
      }
    };

    channel.listen('.message.created', handleIncoming);
    channel.listen('.message.reaction.updated', handleIncoming);

    return () => {
      channel.stopListening('.message.created');
      channel.stopListening('.message.reaction.updated');
      echo.leave(channelName);
      echo.connector?.pusher?.connection?.unbind('connected', onConnected);
      echo.connector?.pusher?.connection?.unbind('disconnected', onDisconnected);
      echo.connector?.pusher?.connection?.unbind('unavailable', onDisconnected);
    };
  }, [dispatch, selectedId, currentUser?.id]);

  const handleSelect = (conversationId) => {
    dispatch(selectConversation(conversationId));
  };

  return (
    <Box
      sx={{
        display: 'flex',
        height: '100%',
        minHeight: '100%',
        gap: 0,
        bgcolor: 'background.default',
        overflow: 'hidden',
      }}
    >
      <Paper
        square
        elevation={0}
        sx={{
          width: { xs: selectedId ? 0 : '100%', sm: 320, md: 360 },
          display: { xs: selectedId ? 'none' : 'flex', sm: 'flex' },
          flexDirection: 'column',
          borderRight: 1,
          borderColor: 'divider',
          minHeight: 0,
        }}
      >
        <ConversationList
          conversations={items}
          selectedId={selectedId}
          status={status}
          onSelect={handleSelect}
          onNewChat={() => setNewChatOpen(true)}
        />
      </Paper>

      <Box
        sx={{
          flex: 1,
          display: { xs: selectedId ? 'flex' : 'none', sm: 'flex' },
          flexDirection: 'column',
          minWidth: 0,
          minHeight: 0,
        }}
      >
        {(error || messagesError) && (
          <Alert severity="error" sx={{ m: 1 }}>
            {error || messagesError}
          </Alert>
        )}
        <MessageThread
          conversation={selectedConversation}
          messages={messages}
          status={messagesStatus}
          currentUserId={currentUser?.id}
          onBack={() => dispatch(selectConversation(null))}
        />
        {selectedConversation && (
          <MessageComposer conversationId={selectedConversation.id} />
        )}
      </Box>

      <NewChatDialog open={newChatOpen} onClose={() => setNewChatOpen(false)} />
    </Box>
  );
}
