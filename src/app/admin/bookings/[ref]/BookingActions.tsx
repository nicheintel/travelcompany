"use client";

import { useActionState } from "react";
import { type AdminActionState, addNoteAction, cancelAction, markPaidAction } from "@/app/actions/admin";
import { PAYMENT_METHODS } from "@/lib/admin";

function Result({ state }: { state: AdminActionState }) {
  if (state?.error) return <p role="alert" className="text-sm font-medium text-red-600">{state.error}</p>;
  if (state?.ok) return <p role="status" className="text-sm font-medium text-emerald-700">{state.ok}</p>;
  return null;
}

const INPUT = "w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900";
const BTN = "rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-60";

export function MarkPaidForm({ reference, total }: { reference: string; total: string }) {
  const [state, action, pending] = useActionState(markPaidAction, undefined);
  return (
    <form action={action} className="space-y-3">
      <input type="hidden" name="reference" value={reference} />
      <label className="block text-xs font-medium text-slate-500">
        Payment method
        <select name="method" defaultValue="" className={`${INPUT} mt-1`} required>
          <option value="" disabled>
            Choose…
          </option>
          {PAYMENT_METHODS.map((m) => (
            <option key={m}>{m}</option>
          ))}
        </select>
      </label>
      <label className="block text-xs font-medium text-slate-500">
        Note (optional)
        <input name="note" placeholder="e.g. transfer ref 12345" maxLength={300} className={`${INPUT} mt-1`} />
      </label>
      <button type="submit" disabled={pending} className={`${BTN} w-full bg-emerald-600 text-white hover:bg-emerald-700`}>
        {pending ? "Saving…" : `Mark ${total} as paid`}
      </button>
      <Result state={state} />
    </form>
  );
}

export function CancelForm({ reference, wasPaid }: { reference: string; wasPaid: boolean }) {
  const [state, action, pending] = useActionState(cancelAction, undefined);
  return (
    <form
      action={action}
      onSubmit={(e) => {
        if (!confirm(`Cancel ${reference}? The customer will be emailed.`)) e.preventDefault();
      }}
      className="space-y-3"
    >
      <input type="hidden" name="reference" value={reference} />
      <label className="block text-xs font-medium text-slate-500">
        Reason
        <input name="reason" placeholder="e.g. Customer asked to cancel by phone" maxLength={300} className={`${INPUT} mt-1`} />
      </label>
      {wasPaid && (
        <p className="text-xs text-amber-700">
          This trip is paid. Cancelling doesn&apos;t refund automatically — issue the refund in Stripe or your bank.
        </p>
      )}
      <button type="submit" disabled={pending} className={`${BTN} w-full text-red-600 ring-1 ring-red-200 hover:bg-red-50`}>
        {pending ? "Cancelling…" : "Cancel booking"}
      </button>
      <Result state={state} />
    </form>
  );
}

export function NoteForm({ reference }: { reference: string }) {
  const [state, action, pending] = useActionState(addNoteAction, undefined);
  return (
    <form action={action} className="space-y-2">
      <input type="hidden" name="reference" value={reference} />
      <textarea
        name="note"
        rows={3}
        maxLength={1000}
        placeholder="Internal note — e.g. Called customer, prefers window seats. Not visible to the customer."
        className={INPUT}
      />
      <div className="flex items-center justify-between gap-3">
        <Result state={state} />
        <button type="submit" disabled={pending} className={`${BTN} ml-auto bg-brand-600 text-white hover:bg-brand-700`}>
          {pending ? "Saving…" : "Add note"}
        </button>
      </div>
    </form>
  );
}
