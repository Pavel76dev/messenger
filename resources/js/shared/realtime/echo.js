import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getToken } from '../api/client';
import { APP_BASE } from '../config';

window.Pusher = Pusher;

let echoInstance = null;

function boolEnv(value, fallback = false) {
  if (value === undefined || value === null || value === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}

function appPath(path) {
  const normalized = path.startsWith('/') ? path : `/${path}`;
  return `${APP_BASE}${normalized}`;
}

export function isRealtimeEnabled() {
  return Boolean(import.meta.env.VITE_PUSHER_APP_KEY);
}

function createAuthorizer() {
  return (channel) => ({
    authorize: (socketId, callback) => {
      const token = getToken();
      if (!token) {
        callback(new Error('Нет токена авторизации'), null);
        return;
      }

      fetch(appPath('/broadcasting/auth'), {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded',
          Authorization: `Bearer ${token}`,
        },
        body: new URLSearchParams({
          socket_id: socketId,
          channel_name: channel.name,
        }),
      })
        .then(async (response) => {
          if (!response.ok) {
            const message = `Broadcast auth ${response.status}`;
            callback(new Error(message), null);
            return;
          }
          const data = await response.json();
          callback(null, data);
        })
        .catch((error) => callback(error, null));
    },
  });
}

export function getEcho() {
  if (!isRealtimeEnabled()) {
    return null;
  }

  if (echoInstance) {
    return echoInstance;
  }

  const key = import.meta.env.VITE_PUSHER_APP_KEY;
  const host = import.meta.env.VITE_PUSHER_HOST || window.location.hostname;
  const port = Number(import.meta.env.VITE_PUSHER_PORT || 6001);
  const scheme = import.meta.env.VITE_PUSHER_SCHEME || 'http';
  const forceTLS = boolEnv(import.meta.env.VITE_PUSHER_FORCE_TLS, scheme === 'https');
  // pusher-js already appends `/app/{key}`; under subdirectory pass only APP_BASE
  // (e.g. /__nr_gate → wss://host/__nr_gate/app/messenger-key).
  const wsPath =
    import.meta.env.VITE_PUSHER_WS_PATH ||
    (APP_BASE ? APP_BASE : '/app');
  const authEndpoint = appPath('/broadcasting/auth');

  echoInstance = new Echo({
    broadcaster: 'pusher',
    key,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
    wsHost: host,
    wsPort: port,
    wssPort: port,
    wsPath,
    forceTLS,
    encrypted: forceTLS,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint,
    authorizer: createAuthorizer(),
  });

  return echoInstance;
}

export function refreshEchoAuth() {
  // Token is read fresh on each authorize() via createAuthorizer.
}

export function disconnectEcho() {
  if (!echoInstance) return;
  echoInstance.disconnect();
  echoInstance = null;
}

export function adaptRealtimeMessage(message, currentUserId) {
  if (!message) return message;
  const reactions = Array.isArray(message.reactions)
    ? message.reactions.map((reaction) => {
        const userIds = Array.isArray(reaction.user_ids)
          ? reaction.user_ids.map((id) => Number(id))
          : [];
        return {
          ...reaction,
          reacted_by_me:
            currentUserId != null
              ? userIds.includes(Number(currentUserId))
              : Boolean(reaction.reacted_by_me),
        };
      })
    : [];

  return {
    ...message,
    reactions,
  };
}
