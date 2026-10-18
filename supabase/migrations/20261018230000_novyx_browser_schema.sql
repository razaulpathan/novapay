-- ============================================================
-- NOVYX Browser — Browse Beyond Limits
-- Database Schema, Profiles, Preferences, Sync & RLS Policies
-- ============================================================

-- 1. Helper function for updated_at timestamps
CREATE OR REPLACE FUNCTION public.handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 2. Profiles Table (User profile metadata with explicit sync consent)
CREATE TABLE IF NOT EXISTS public.profiles (
  id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  display_name TEXT,
  avatar_url TEXT,
  sync_enabled BOOLEAN DEFAULT FALSE NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  updated_at TIMESTAMPTZ DEFAULT NOW() NOT NULL
);

-- Trigger for profiles updated_at
DROP TRIGGER IF EXISTS trg_profiles_updated_at ON public.profiles;
CREATE TRIGGER trg_profiles_updated_at
BEFORE UPDATE ON public.profiles
FOR EACH ROW
EXECUTE FUNCTION public.handle_updated_at();

-- 3. User Preferences Table (Privacy, theme, ad-block, search engine)
CREATE TABLE IF NOT EXISTS public.user_preferences (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  search_engine TEXT DEFAULT 'google' NOT NULL,
  ad_block_enabled BOOLEAN DEFAULT TRUE NOT NULL,
  dark_mode BOOLEAN DEFAULT TRUE NOT NULL,
  do_not_track BOOLEAN DEFAULT TRUE NOT NULL,
  sync_bookmarks BOOLEAN DEFAULT FALSE NOT NULL,
  sync_preferences BOOLEAN DEFAULT FALSE NOT NULL,
  theme_settings JSONB DEFAULT '{}'::jsonb NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  updated_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  CONSTRAINT uq_user_preferences_user UNIQUE (user_id)
);

CREATE INDEX IF NOT EXISTS idx_user_preferences_user_id ON public.user_preferences(user_id);

DROP TRIGGER IF EXISTS trg_user_preferences_updated_at ON public.user_preferences;
CREATE TRIGGER trg_user_preferences_updated_at
BEFORE UPDATE ON public.user_preferences
FOR EACH ROW
EXECUTE FUNCTION public.handle_updated_at();

-- 4. Synced Bookmarks Table (Optional cloud bookmarks with conflict handling)
CREATE TABLE IF NOT EXISTS public.synced_bookmarks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  url TEXT NOT NULL,
  favicon_url TEXT,
  folder TEXT DEFAULT 'default' NOT NULL,
  position INT DEFAULT 0 NOT NULL,
  is_deleted BOOLEAN DEFAULT FALSE NOT NULL,
  client_updated_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  updated_at TIMESTAMPTZ DEFAULT NOW() NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_synced_bookmarks_user_id ON public.synced_bookmarks(user_id);
CREATE INDEX IF NOT EXISTS idx_synced_bookmarks_updated_at ON public.synced_bookmarks(user_id, updated_at);

DROP TRIGGER IF EXISTS trg_synced_bookmarks_updated_at ON public.synced_bookmarks;
CREATE TRIGGER trg_synced_bookmarks_updated_at
BEFORE UPDATE ON public.synced_bookmarks
FOR EACH ROW
EXECUTE FUNCTION public.handle_updated_at();

-- 5. Sync Metadata Table (Per-device sync tracking & versioning)
CREATE TABLE IF NOT EXISTS public.sync_metadata (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  device_id TEXT NOT NULL,
  device_name TEXT,
  last_synced_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  sync_version INT DEFAULT 1 NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  updated_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
  CONSTRAINT uq_sync_metadata_user_device UNIQUE (user_id, device_id)
);

CREATE INDEX IF NOT EXISTS idx_sync_metadata_user_id ON public.sync_metadata(user_id);

DROP TRIGGER IF EXISTS trg_sync_metadata_updated_at ON public.sync_metadata;
CREATE TRIGGER trg_sync_metadata_updated_at
BEFORE UPDATE ON public.sync_metadata
FOR EACH ROW
EXECUTE FUNCTION public.handle_updated_at();

-- 6. Enable Row Level Security (RLS) on all browser tables
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.user_preferences ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.synced_bookmarks ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.sync_metadata ENABLE ROW LEVEL SECURITY;

-- 7. RLS Policies: Profiles
DROP POLICY IF EXISTS "profiles_select_own" ON public.profiles;
CREATE POLICY "profiles_select_own" ON public.profiles
FOR SELECT TO authenticated
USING ((select auth.uid()) = id);

DROP POLICY IF EXISTS "profiles_insert_own" ON public.profiles;
CREATE POLICY "profiles_insert_own" ON public.profiles
FOR INSERT TO authenticated
WITH CHECK ((select auth.uid()) = id);

DROP POLICY IF EXISTS "profiles_update_own" ON public.profiles;
CREATE POLICY "profiles_update_own" ON public.profiles
FOR UPDATE TO authenticated
USING ((select auth.uid()) = id)
WITH CHECK ((select auth.uid()) = id);

DROP POLICY IF EXISTS "profiles_delete_own" ON public.profiles;
CREATE POLICY "profiles_delete_own" ON public.profiles
FOR DELETE TO authenticated
USING ((select auth.uid()) = id);

-- 8. RLS Policies: User Preferences
DROP POLICY IF EXISTS "preferences_select_own" ON public.user_preferences;
CREATE POLICY "preferences_select_own" ON public.user_preferences
FOR SELECT TO authenticated
USING ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "preferences_insert_own" ON public.user_preferences;
CREATE POLICY "preferences_insert_own" ON public.user_preferences
FOR INSERT TO authenticated
WITH CHECK ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "preferences_update_own" ON public.user_preferences;
CREATE POLICY "preferences_update_own" ON public.user_preferences
FOR UPDATE TO authenticated
USING ((select auth.uid()) = user_id)
WITH CHECK ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "preferences_delete_own" ON public.user_preferences;
CREATE POLICY "preferences_delete_own" ON public.user_preferences
FOR DELETE TO authenticated
USING ((select auth.uid()) = user_id);

-- 9. RLS Policies: Synced Bookmarks
DROP POLICY IF EXISTS "bookmarks_select_own" ON public.synced_bookmarks;
CREATE POLICY "bookmarks_select_own" ON public.synced_bookmarks
FOR SELECT TO authenticated
USING ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "bookmarks_insert_own" ON public.synced_bookmarks;
CREATE POLICY "bookmarks_insert_own" ON public.synced_bookmarks
FOR INSERT TO authenticated
WITH CHECK ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "bookmarks_update_own" ON public.synced_bookmarks;
CREATE POLICY "bookmarks_update_own" ON public.synced_bookmarks
FOR UPDATE TO authenticated
USING ((select auth.uid()) = user_id)
WITH CHECK ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "bookmarks_delete_own" ON public.synced_bookmarks;
CREATE POLICY "bookmarks_delete_own" ON public.synced_bookmarks
FOR DELETE TO authenticated
USING ((select auth.uid()) = user_id);

-- 10. RLS Policies: Sync Metadata
DROP POLICY IF EXISTS "sync_metadata_select_own" ON public.sync_metadata;
CREATE POLICY "sync_metadata_select_own" ON public.sync_metadata
FOR SELECT TO authenticated
USING ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "sync_metadata_insert_own" ON public.sync_metadata;
CREATE POLICY "sync_metadata_insert_own" ON public.sync_metadata
FOR INSERT TO authenticated
WITH CHECK ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "sync_metadata_update_own" ON public.sync_metadata;
CREATE POLICY "sync_metadata_update_own" ON public.sync_metadata
FOR UPDATE TO authenticated
USING ((select auth.uid()) = user_id)
WITH CHECK ((select auth.uid()) = user_id);

DROP POLICY IF EXISTS "sync_metadata_delete_own" ON public.sync_metadata;
CREATE POLICY "sync_metadata_delete_own" ON public.sync_metadata
FOR DELETE TO authenticated
USING ((select auth.uid()) = user_id);

-- 11. Automatic Profile & Preference Creation Trigger upon User Signup
CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER AS $$
BEGIN
  INSERT INTO public.profiles (id, display_name, sync_enabled)
  VALUES (
    NEW.id,
    COALESCE(NEW.raw_user_meta_data->>'full_name', NEW.raw_user_meta_data->>'name', 'Novyx User'),
    FALSE
  )
  ON CONFLICT (id) DO NOTHING;

  INSERT INTO public.user_preferences (user_id, search_engine, ad_block_enabled, dark_mode, do_not_track, sync_bookmarks, sync_preferences)
  VALUES (
    NEW.id,
    'google',
    TRUE,
    TRUE,
    TRUE,
    FALSE,
    FALSE
  )
  ON CONFLICT (user_id) DO NOTHING;

  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

DROP TRIGGER IF EXISTS trg_on_auth_user_created ON auth.users;
CREATE TRIGGER trg_on_auth_user_created
AFTER INSERT ON auth.users
FOR EACH ROW
EXECUTE FUNCTION public.handle_new_user();

-- 12. Ensure public read access for announcements and settings
DROP POLICY IF EXISTS "announcements_read_active" ON public.announcements;
CREATE POLICY "announcements_read_active" ON public.announcements
FOR SELECT TO anon, authenticated
USING (is_active = TRUE);

DROP POLICY IF EXISTS "settings_read_public" ON public.settings;
CREATE POLICY "settings_read_public" ON public.settings
FOR SELECT TO anon, authenticated
USING (TRUE);
