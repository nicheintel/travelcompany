"use client";

import Form from "next/form";
import { useState } from "react";
import { addDays } from "@/lib/format";
import { useToday } from "@/lib/useToday";
import { SearchIcon } from "../icons";
import { AirportInput } from "./AirportInput";
import { DateField, SubmitButton, TravellersField, type Travellers } from "./fields";

export type HotelFormDefaults = {
  to?: string;
  checkin?: string;
  checkout?: string;
  adults?: number;
  children?: number;
  rooms?: number;
};

export function HotelSearchForm({ defaults = {} }: { defaults?: HotelFormDefaults }) {
  const today = useToday();
  const [to, setTo] = useState(defaults.to ?? "");
  const [checkInPick, setCheckInPick] = useState(defaults.checkin ?? "");
  const [checkOutPick, setCheckOutPick] = useState(defaults.checkout ?? "");
  const [travellers, setTravellers] = useState<Travellers>({
    adults: defaults.adults ?? 2,
    children: defaults.children ?? 0,
    rooms: defaults.rooms ?? 1,
  });
  const [error, setError] = useState<string | null>(null);

  const checkIn = checkInPick || (today ? addDays(today, 14) : "");
  const checkOut =
    checkOutPick && checkOutPick > checkIn ? checkOutPick : checkIn ? addDays(checkIn, 3) : "";

  return (
    <Form
      action="/hotels"
      onSubmit={(e) => {
        if (!to) {
          e.preventDefault();
          setError("Please choose a destination.");
        } else setError(null);
      }}
      className="space-y-3"
    >
      <div className="grid gap-3 lg:grid-cols-[2fr_0.8fr_0.8fr_1fr_auto]">
        <AirportInput name="to" label="Destination" placeholder="City, e.g. Bangkok" value={to} onChange={setTo} />
        <DateField label="Check-in" name="checkin" value={checkIn} min={today} onChange={setCheckInPick} />
        <DateField
          label="Check-out"
          name="checkout"
          value={checkOut}
          min={checkIn ? addDays(checkIn, 1) : today}
          onChange={setCheckOutPick}
        />
        <TravellersField value={travellers} onChange={setTravellers} showRooms />
        <SubmitButton>
          <SearchIcon width={18} height={18} />
          <span>Search</span>
        </SubmitButton>
      </div>
      {error && (
        <p role="alert" className="text-sm font-medium text-red-600">
          {error}
        </p>
      )}
    </Form>
  );
}
