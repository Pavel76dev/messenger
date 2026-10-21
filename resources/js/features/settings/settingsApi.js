import { apiRequest } from '../../shared/api/client';

export async function fetchUserSettings() {
  return apiRequest('/api/me/settings');
}

export async function updateUserSettings(values) {
  return apiRequest('/api/me/settings', {
    method: 'PATCH',
    body: JSON.stringify({ values }),
  });
}
