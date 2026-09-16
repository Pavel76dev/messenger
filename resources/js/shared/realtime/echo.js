import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getToken } from '../api/client';

window.Pusher = Pusher;

let echoInstance = null;

function boolEnv(value, fallback = false) {
  if (value === undefined || value === null || value === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}

export function isRealtimeEnabled() {
  return Boolean(import.meta.env.VITE_PUSHER_APP_KEY);
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

  echoInstance = new Echo({
    broadcaster: 'pusher',
    key,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS,
    encrypted: forceTLS,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${getToken() || ''}`,
      },
    },
  });

  return echoInstance;
}

export function refreshEchoAuth() {
  if (!echoInstance) return;
  const token = getToken();
  if (echoInstance.connector?.pusher?.config?.auth?.headers) {
    echoInstance.connector.pusher.config.auth.headers.Authorization = `Bearer ${token || ''}`;
  }
  if (echoInstance.options?.auth?.headers) {
    echoInstance.options.auth.headers.Authorization = `Bearer ${token || ''}`;
  }
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
