// Turns a machine value such as "in_progress" into a readable label "In progress".
// Null/undefined become an empty string so templates render nothing instead of "null".
export function formatLabel(value) {
  if (value === null || value === undefined) return ''
  const s = String(value).replace(/_/g, ' ').trim()
  return s.charAt(0).toUpperCase() + s.slice(1)
}
