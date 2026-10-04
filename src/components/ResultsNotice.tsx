/** Shown above search results: sample-data notice, supplier errors, or no availability. */
export function ResultsNotice({ live, error, empty, what }: { live: boolean; error?: string; empty: boolean; what: string }) {
  if (error) {
    return <p className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700 ring-1 ring-red-200">{error}</p>;
  }
  if (empty) {
    return (
      <p className="mb-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
        No {what} available for these dates. Try different dates or a nearby airport.
      </p>
    );
  }
  if (!live) {
    return (
      <p className="mb-4 rounded-xl bg-amber-50 px-4 py-2 text-xs font-medium text-amber-800 ring-1 ring-amber-200">
        Showing sample {what} — live prices appear once the supplier API key is added.
      </p>
    );
  }
  return null;
}
