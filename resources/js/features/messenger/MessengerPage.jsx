import { useEffect, useRef, useState } from 'react';
import { Alert, Box, Paper } from '@mui/material';
import { fetchConversations, selectConversation } from '../conversations/conversationsSlice';
import { fetchMessages } from '../messages/messagesSlice';
import { useAppDispatch, useAppSelector } from '../../app/hooks';
import { ConversationList } from './ConversationList';
import { MessageThread } from './MessageThread';
import { MessageComposer } from './MessageComposer';
import { NewChatDialog } from './NewChatDialog';

export function MessengerPage() {
  const dispatch = useAppDispatch();
  const [newChatOpen, setNewChatOpen] = useState(false);
  const { items, selectedId, status, error } = useAppSelector((state) => state.conversations);
  const messagesError = useAppSelector((state) => state.messages.error);
  const currentUser = useAppSelector((state) => state.auth.user);
  const messagesById = useAppSelector((state) => state.messages.byConversationId);
  const lastMessageIdRef = useRef(null);

  const selectedConversation = items.find((item) => item.id === selectedId) || null;
  const messages = messagesById[selectedId] || [];
  const messagesStatus = useAppSelector(
    (state) => state.messages.statusByConversationId[selectedId] || 'idle',
  );

  useEffect(() => {
    const last = messages.length ? messages[messages.length - 1] : null;
    lastMessageIdRef.current = last?.id ?? null;
  }, [messages]);

  useEffect(() => {
    dispatch(fetchConversations());
  }, [dispatch]);

  useEffect(() => {
    if (!selectedId) {
      return undefined;
    }

    lastMessageIdRef.current = null;
    dispatch(fetchMessages({ conversationId: selectedId }));

    const intervalId = setInterval(() => {
      dispatch(
        fetchMessages({
          conversationId: selectedId,
          afterId: lastMessageIdRef.current || undefined,
        }),
      );
    }, 3000);

    return () => clearInterval(intervalId);
  }, [dispatch, selectedId]);

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
