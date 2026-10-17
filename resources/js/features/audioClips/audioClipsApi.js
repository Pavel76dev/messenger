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

export async function transcribeAudioClip(id) {
  return apiRequest(`/api/audio-clips/${id}/transcribe`, { method: 'POST' });
}

export async function extractTasksFromAudioClip(id) {
  return apiRequest(`/api/audio-clips/${id}/extract-tasks`, { method: 'POST' });
}

export async function updateTaskItemStatus(id, status) {
  return apiRequest(`/api/task-items/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  });
}

export async function deleteTaskList(id) {
  return apiRequest(`/api/task-lists/${id}`, { method: 'DELETE' });
}

export async function sendAudioClipToChat(id, conversationId) {
  return apiRequest(`/api/audio-clips/${id}/send`, {
    method: 'POST',
    body: JSON.stringify({ conversation_id: conversationId }),
  });
}
