import { useEffect, useState } from 'react';
import { Alert, Box, Paper } from '@mui/material';
import { fetchConversations, selectConversation, upsertConversation } from '../conversations/conversationsSlice';
import {
  applyRealtimeMessage,
  fetchMessages,
  setRealtimeConnected,
} from '../messages/messagesSlice';
import {
  applyCallUpdate,
  fetchActiveCall,
  resetCallState,
  setIncomingCall,
} from '../calls/callsSlice';
import { extractCall, resolveCallId } from '../calls/callUtils';
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
import { CallOverlay } from '../calls/CallOverlay';

export function MessengerPage() {
  const dispatch = useAppDispatch();
  const [newChatOpen, setNewChatOpen] = useState(false);
  const { items, selectedId, status, error } = useAppSelector((state) => state.conversations);
  const messagesError = useAppSelector((state) => state.messages.error);
  const callError = useAppSelector((state) => state.calls.error);
  const realtimeConnected = useAppSelector((state) => state.messages.realtimeConnected);
  const currentUser = useAppSelector((state) => state.auth.user);
  const messagesById = useAppSelector((state) => state.messages.byConversationId);
  const callSession = useAppSelector((state) => state.calls.session);

  const selectedConversation = items.find((item) => item.id === selectedId) || null;
  const messages = messagesById[selectedId] || [];
  const messagesStatus = useAppSelector(
    (state) => state.messages.statusByConversationId[selectedId] || 'idle',
  );

  useEffect(() => {
    dispatch(fetchConversations());
  }, [dispatch]);

  useEffect(() => {
    if (selectedId) {
      dispatch(fetchActiveCall(selectedId));
    }
  }, [dispatch, selectedId]);

  // Личный канал: новые группы/диалоги + входящие звонки
  useEffect(() => {
    if (!currentUser?.id || !isRealtimeEnabled()) {
      return undefined;
    }

    refreshEchoAuth();
    const echo = getEcho();
    if (!echo) {
      return undefined;
    }

    const channelName = `user.${currentUser.id}`;
    const channel = echo.private(channelName);

    channel.listen('.conversation.created', (payload) => {
      if (payload?.conversation) {
        dispatch(upsertConversation(payload.conversation));
      } else {
        dispatch(fetchConversations());
      }
    });

    const onIncoming = (payload) => {
      const call = extractCall(payload);
      if (!call) return;
      if (Number(call.created_by) === Number(currentUser.id)) return;
      if (resolveCallId(callSession?.call?.id) === resolveCallId(call.id)) return;
      dispatch(setIncomingCall(call));
    };

    const onCallLifecycle = (payload) => {
      const call = extractCall(payload);
      if (!call) return;
      dispatch(applyCallUpdate(call));
      if (call.status === 'ended' || call.status === 'rejected') {
        if (resolveCallId(callSession?.call?.id) === resolveCallId(call.id)) {
          dispatch(resetCallState());
        }
        dispatch(setIncomingCall(null));
      }
    };

    channel.listen('.call.incoming', onIncoming);
    channel.listen('.call.accepted', onCallLifecycle);
    channel.listen('.call.rejected', onCallLifecycle);
    channel.listen('.call.ended', onCallLifecycle);
    channel.listen('.call.updated', onCallLifecycle);

    return () => {
      channel.stopListening('.conversation.created');
      channel.stopListening('.call.incoming');
      channel.stopListening('.call.accepted');
      channel.stopListening('.call.rejected');
      channel.stopListening('.call.ended');
      channel.stopListening('.call.updated');
      echo.leave(channelName);
    };
  }, [dispatch, currentUser?.id, callSession?.call?.id]);

  useEffect(() => {
    if (!selectedId) {
      return undefined;
    }

    dispatch(fetchMessages({ conversationId: selectedId }));

    const intervalMs = realtimeConnected ? 15000 : 3000;
    const intervalId = setInterval(() => {
      dispatch(
        fetchMessages({
          conversationId: selectedId,
          silent: true,
        }),
      );
      dispatch(fetchActiveCall(selectedId));
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

    const onCallEvent = (payload) => {
      const call = extractCall(payload);
      if (!call) return;
      dispatch(applyCallUpdate(call));
      if (
        Number(call.created_by) !== Number(currentUser.id) &&
        (call.status === 'ringing' || call.status === 'active') &&
        !callSession
      ) {
        dispatch(setIncomingCall(call));
      }
      if (call.status === 'ended' || call.status === 'rejected') {
        if (resolveCallId(callSession?.call?.id) === resolveCallId(call.id)) {
          dispatch(resetCallState());
        }
      }
    };

    channel.listen('.message.created', handleIncoming);
    channel.listen('.message.reaction.updated', handleIncoming);
    channel.listen('.call.incoming', onCallEvent);
    channel.listen('.call.accepted', onCallEvent);
    channel.listen('.call.rejected', onCallEvent);
    channel.listen('.call.ended', onCallEvent);
    channel.listen('.call.updated', onCallEvent);

    return () => {
      channel.stopListening('.message.created');
      channel.stopListening('.message.reaction.updated');
      channel.stopListening('.call.incoming');
      channel.stopListening('.call.accepted');
      channel.stopListening('.call.rejected');
      channel.stopListening('.call.ended');
      channel.stopListening('.call.updated');
      echo.leave(channelName);
      echo.connector?.pusher?.connection?.unbind('connected', onConnected);
      echo.connector?.pusher?.connection?.unbind('disconnected', onDisconnected);
      echo.connector?.pusher?.connection?.unbind('unavailable', onDisconnected);
    };
  }, [dispatch, selectedId, currentUser?.id, callSession]);

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
        {(error || messagesError || callError) && (
          <Alert severity="error" sx={{ m: 1 }}>
            {error || messagesError || callError}
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
      <CallOverlay />
    </Box>
  );
}
