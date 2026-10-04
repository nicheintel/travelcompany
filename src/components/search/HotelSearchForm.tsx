"use client";

import Form from "next/form";
import { useState } from "react";
import { addDays } from "@/lib/format";
import { useToday } from "@/lib/useToday";
import { SearchIcon } from "../icons";
import { AirportInput } from "./AirportInput";
import { DateField, SubmitButton, TravellersField, type Travellers } from "./fields";

export function HotelSearchForm() {
  const today = useToday();
  const [to, setTo] = useState("");
  const [checkInPick, setCheckInPick] = useState("");
  const [checkOutPick, setCheckOutPick] = useState("");
  const [travellers, setTravellers] = useState<Travellers>({ adults: 2, children: 0, rooms: 1 });

  const checkIn = checkInPick || (today ? addDays(today, 14) : "");
  const checkOut =
    checkOutPick && checkOutPick > checkIn ? checkOutPick : checkIn ? addDays(checkIn, 3) : "";

  return (
    <Form action="/hotels" className="grid gap-3 lg:grid-cols-[2fr_0.8fr_0.8fr_1fr_auto]">
      <AirportInput name="to" label="Destination" placeholder="City, e.g. Bangkok" value={to} onChange={setTo} />
      <DateField label="Check-in" name="checkin" value={checkIn} min={today} onChange={setCheckInPick} />
      <DateField label="Check-out" name="checkout" value={checkOut} min={checkIn || today} onChange={setCheckOutPick} />
      <TravellersField value={travellers} onChange={setTravellers} showRooms />
      <SubmitButton>
        <SearchIcon width={18} height={18} />
        <span>Search</span>
      </SubmitButton>
    </Form>
  );
}
