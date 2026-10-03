/**
 * Classify a song against the client's questionnaire picks.
 *
 * @param {{must_play?: number[], do_not_play?: number[]}|null|undefined} requests
 * @param {number|null|undefined} songId
 * @returns {'must_play'|'do_not_play'|'none'}
 */
export function clientSongStatus(requests, songId) {
  if (!requests || songId == null) return 'none';
  if ((requests.do_not_play || []).includes(songId)) return 'do_not_play';
  if ((requests.must_play || []).includes(songId)) return 'must_play';
  return 'none';
}
