"use server";

import { refresh } from "next/cache";
import { redirect } from "next/navigation";
import { PAYMENT_METHODS } from "@/lib/admin";
import { addEvent, adminCancel, adminGetBooking, adminMarkPaid } from "@/lib/server/bookings";
import { requireAdmin } from "@/lib/server/dal";
import { notifyBooking } from "@/lib/server/notify";
import { findUserById, setUserRole } from "@/lib/server/users";

export type AdminActionState = { ok?: string; error?: string } | undefined;

function field(formData: FormData, key: string) {
  const v = formData.get(key);
  return typeof v === "string" ? v.trim() : "";
}

export async function markPaidAction(_prev: AdminActionState, formData: FormData): Promise<AdminActionState> {
  const reference = field(formData, "reference");
  const admin = await requireAdmin(`/admin/bookings/${reference}`);
  const method = field(formData, "method");
  const note = field(formData, "note").slice(0, 300);
  if (!(PAYMENT_METHODS as readonly string[]).includes(method)) return { error: "Choose how the customer paid." };

  const booking = adminGetBooking(reference);
  if (!booking) return { error: "Booking not found." };
  if (!adminMarkPaid(reference, admin.id, method, note)) return { error: "Only reserved (unpaid) bookings can be marked as paid." };
  await notifyBooking("paid", booking.customer.id, reference);
  // The payment form disappears once paid, so confirm with a page banner instead.
  redirect(`/admin/bookings/${encodeURIComponent(reference)}?done=paid`);
}

export async function cancelAction(_prev: AdminActionState, formData: FormData): Promise<AdminActionState> {
  const reference = field(formData, "reference");
  const admin = await requireAdmin(`/admin/bookings/${reference}`);
  const reason = field(formData, "reason");
  if (reason.length < 3) return { error: "Please give a reason (it's saved in the activity log)." };

  const booking = adminGetBooking(reference);
  if (!booking) return { error: "Booking not found." };
  if (!adminCancel(reference, admin.id, reason.slice(0, 300))) return { error: "This booking is already cancelled." };
  await notifyBooking("cancelled", booking.customer.id, reference);
  redirect(`/admin/bookings/${encodeURIComponent(reference)}?done=cancelled`);
}

export async function addNoteAction(_prev: AdminActionState, formData: FormData): Promise<AdminActionState> {
  const reference = field(formData, "reference");
  const admin = await requireAdmin(`/admin/bookings/${reference}`);
  const note = field(formData, "note");
  if (!note) return { error: "Write a note first." };
  if (!adminGetBooking(reference)) return { error: "Booking not found." };
  addEvent(reference, admin.id, "note", note.slice(0, 1000));
  refresh();
  return { ok: "Note added." };
}

export async function setRoleAction(_prev: AdminActionState, formData: FormData): Promise<AdminActionState> {
  const admin = await requireAdmin("/admin/users");
  const userId = Number(field(formData, "userId"));
  const role = field(formData, "role") === "admin" ? "admin" : "customer";
  const target = findUserById(userId);
  if (!target) return { error: "User not found." };
  if (target.id === admin.id) return { error: "You can't change your own access." };
  if (target.adminByConfig) return { error: "This admin is set by ADMIN_EMAILS and can only be changed there." };
  setUserRole(userId, role);
  refresh();
  return { ok: role === "admin" ? `${target.name} is now an admin.` : `${target.name} is no longer an admin.` };
}
