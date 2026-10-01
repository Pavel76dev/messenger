/**
 * @param {unknown} id
 * @returns {number|null}
 */
export function resolveCallId(id) {
  if (id == null || id === '' || id === 'undefined' || id === 'null') {
    return null;
  }
  const n = Number(id);
  return Number.isFinite(n) && n > 0 ? n : null;
}

/**
 * Echo / REST may wrap as `{ call: {...} }` or send the call object flat.
 * @param {unknown} payload
 * @returns {object|null}
 */
export function extractCall(payload) {
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  const candidate =
    payload.call && typeof payload.call === 'object' ? payload.call : payload;

  if (!candidate || typeof candidate !== 'object' || Array.isArray(candidate)) {
    return null;
  }

  if (resolveCallId(candidate.id) == null) {
    return null;
  }

  return candidate;
}
