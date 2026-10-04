"use client";

import { useActionState } from "react";
import { createBookingAction } from "@/app/actions/bookings";
import { FormMessage, SubmitButton, TextField } from "@/components/auth/fields";
import { formatPrice } from "@/lib/format";
import type { TravelerSlot } from "@/lib/quote";

export function BookingForm({
  kind,
  query,
  total,
  slots,
  defaults,
}: {
  kind: string;
  query: string;
  total: number;
  slots: TravelerSlot[];
  defaults: { firstName: string; lastName: string; email: string };
}) {
  const [state, action, pending] = useActionState(createBookingAction, undefined);
  const v = (name: string, fallback = "") => state?.values?.[name] ?? fallback;
  const err = (name: string) => (state?.errors?.[name] ? [state.errors[name]] : undefined);

  return (
    <form action={action} className="space-y-6" noValidate>
      <input type="hidden" name="kind" value={kind} />
      <input type="hidden" name="query" value={query} />
      <input type="hidden" name="expectedTotal" value={total} />

      <FormMessage message={state?.message} />

      <section className="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 className="text-lg font-semibold text-slate-900">
          {slots.length === 1 && !slots[0].needsDob ? "Lead guest" : "Traveler details"}
        </h2>
        <p className="mt-1 text-sm text-slate-500">
          Names must match each traveler&apos;s passport or government ID.
        </p>
        <div className="mt-5 space-y-6">
          {slots.map((slot, i) => (
            <fieldset key={slot.label} className="space-y-4 border-t border-slate-100 pt-5 first:border-0 first:pt-0">
              <legend className="mb-3 text-sm font-semibold text-brand-700">{slot.label}</legend>
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  label="First name"
                  name={`t${i}_first`}
                  autoComplete={i === 0 ? "given-name" : "off"}
                  defaultValue={v(`t${i}_first`, i === 0 ? defaults.firstName : "")}
                  errors={err(`t${i}_first`)}
                />
                <TextField
                  label="Last name"
                  name={`t${i}_last`}
                  autoComplete={i === 0 ? "family-name" : "off"}
                  defaultValue={v(`t${i}_last`, i === 0 ? defaults.lastName : "")}
                  errors={err(`t${i}_last`)}
                />
              </div>
              {slot.needsDob && (
                <div className="sm:w-1/2 sm:pr-2">
                  <TextField
                    label="Date of birth"
                    name={`t${i}_dob`}
                    type="date"
                    autoComplete={i === 0 ? "bday" : "off"}
                    defaultValue={v(`t${i}_dob`)}
                    errors={err(`t${i}_dob`)}
                  />
                </div>
              )}
            </fieldset>
          ))}
        </div>
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-6">
        <h2 className="text-lg font-semibold text-slate-900">Contact details</h2>
        <p className="mt-1 text-sm text-slate-500">We&apos;ll send your confirmation and updates here.</p>
        <div className="mt-5 grid gap-4 sm:grid-cols-2">
          <TextField
            label="Email"
            name="email"
            type="email"
            autoComplete="email"
            defaultValue={v("email", defaults.email)}
            errors={err("email")}
          />
          <TextField
            label="Mobile phone"
            name="phone"
            type="tel"
            autoComplete="tel"
            placeholder="+1 555 123 4567"
            defaultValue={v("phone")}
            errors={err("phone")}
          />
        </div>
      </section>

      <div className="rounded-2xl bg-brand-50 p-5 text-sm text-brand-900 ring-1 ring-brand-100">
        <p className="font-semibold">Reserve now, pay later</p>
        <p className="mt-1 text-brand-800">
          No payment is taken today. A travel assistant will contact you within 24 hours to confirm
          availability and arrange payment.
        </p>
      </div>

      <SubmitButton pending={pending}>
        {pending ? "Reserving…" : `Reserve trip · ${formatPrice(total)}`}
      </SubmitButton>
    </form>
  );
}
