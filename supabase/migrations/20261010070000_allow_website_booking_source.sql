-- Public booking requests are created by the server-side PHP endpoint.
-- Keep the source explicit rather than mislabeling website bookings as phone or walk-in.
ALTER TABLE public.bookings
  DROP CONSTRAINT IF EXISTS bookings_source_channel_check;

ALTER TABLE public.bookings
  ADD CONSTRAINT bookings_source_channel_check
  CHECK (
    source_channel = ANY (
      ARRAY[
        'dashboard'::text,
        'telegram'::text,
        'whatsapp'::text,
        'phone'::text,
        'walk_in'::text,
        'website'::text
      ]
    )
  );
