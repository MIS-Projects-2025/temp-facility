// Returns an ISO-8601 UTC string (e.g. 2026-11-06T02:30:00.000Z) so the server
// always knows the exact moment, regardless of the browser's timezone.
export default function formatDateTime(date) {
	if (!date) return "";

	const d = date instanceof Date ? date : new Date(date);
	if (Number.isNaN(d.getTime())) return "";

	return d.toISOString();
}