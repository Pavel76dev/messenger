import { apiRequest } from '../../shared/api/client';

export async function fetchAudioClips() {
  const data = await apiRequest('/api/audio-clips');
  return Array.isArray(data) ? data : data.data || [];
}

export async function saveAudioClip(file, durationMs) {
  const formData = new FormData();
  formData.append('file', file);
  if (durationMs != null && durationMs >= 0) {
    formData.append('duration_ms', String(Math.round(durationMs)));
  }
  return apiRequest('/api/audio-clips', {
    method: 'POST',
    body: formData,
  });
}

export async function deleteAudioClip(id) {
  return apiRequest(`/api/audio-clips/${id}`, { method: 'DELETE' });
}
