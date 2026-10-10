-- Public-facing workshop identity managed from the protected admin dashboard.
-- Public reads and admin writes must go through server-side PHP using the service role key.
CREATE TABLE IF NOT EXISTS public.workshop_profile (
  id smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  workshop_name text NOT NULL DEFAULT 'BengkelPro' CHECK (char_length(btrim(workshop_name)) BETWEEN 1 AND 120),
  tagline text NOT NULL DEFAULT 'Perawatan kendaraan, lebih praktis' CHECK (char_length(tagline) <= 180),
  description text NOT NULL DEFAULT 'Ajukan booking servis secara online.' CHECK (char_length(description) <= 2000),
  logo_url text,
  hero_image_url text,
  gallery_image_1_url text,
  gallery_image_2_url text,
  gallery_image_3_url text,
  address text,
  whatsapp_number text,
  phone_number text,
  email text,
  google_maps_url text,
  instagram_url text,
  opening_hours text,
  service_area text,
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT workshop_profile_email_check CHECK (email IS NULL OR email ~* '^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$')
);

ALTER TABLE public.workshop_profile ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON TABLE public.workshop_profile FROM PUBLIC, anon, authenticated;
GRANT SELECT, INSERT, UPDATE ON TABLE public.workshop_profile TO service_role;

INSERT INTO public.workshop_profile (id)
VALUES (1)
ON CONFLICT (id) DO NOTHING;

COMMENT ON TABLE public.workshop_profile IS
  'Single public-facing workshop profile. Access only through trusted server-side code; never expose service role credentials to browsers.';
