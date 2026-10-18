import AsyncStorage from '@react-native-async-storage/async-storage';
import { createClient, SupabaseClient, User as SupabaseAuthUser } from '@supabase/supabase-js';

// Default project configuration from environment or fallback
const SUPABASE_URL = process.env.EXPO_PUBLIC_SUPABASE_URL || 'https://uvnpvfckgbqxmfzanfwi.supabase.co';
const SUPABASE_ANON_KEY = process.env.EXPO_PUBLIC_SUPABASE_ANON_KEY || 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InV2bnB2ZmNrZ2JxeG1memFuZndpIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTIzMjA4NTMsImV4cCI6MjEwNzg5Njg1M30.-_RbUcxy_W-iwI7xu2Ii_whrWmvAnq_itSzkOItvyVw';

export interface LocalBookmark {
  id: string;
  title: string;
  url: string;
  favicon_url?: string;
  folder: string;
  position: number;
  is_deleted?: boolean;
  client_updated_at: string;
}

export interface UserPreferences {
  search_engine: string;
  ad_block_enabled: boolean;
  dark_mode: boolean;
  do_not_track: boolean;
  sync_bookmarks: boolean;
  sync_preferences: boolean;
}

class NovyxSupabaseService {
  private client: SupabaseClient | null = null;

  constructor() {
    this.init();
  }

  private init() {
    if (SUPABASE_URL && SUPABASE_ANON_KEY) {
      this.client = createClient(SUPABASE_URL, SUPABASE_ANON_KEY, {
        auth: {
          storage: AsyncStorage,
          autoRefreshToken: true,
          persistSession: true,
          detectSessionInUrl: false,
        },
      });
    }
  }

  public getClient(): SupabaseClient | null {
    return this.client;
  }

  // --- AUTHENTICATION ---
  public async getSession() {
    if (!this.client) return null;
    const { data } = await this.client.auth.getSession();
    return data.session;
  }

  public async getCurrentUser(): Promise<SupabaseAuthUser | null> {
    if (!this.client) return null;
    const { data } = await this.client.auth.getUser();
    return data.user;
  }

  public async signInWithEmail(email: string, password: string) {
    if (!this.client) throw new Error('Supabase client not initialized');
    return await this.client.auth.signInWithPassword({ email, password });
  }

  public async signUpWithEmail(email: string, password: string, displayName?: string) {
    if (!this.client) throw new Error('Supabase client not initialized');
    return await this.client.auth.signUp({
      email,
      password,
      options: {
        data: {
          full_name: displayName || 'Novyx User',
        },
      },
    });
  }

  public async signOut() {
    if (!this.client) return;
    await this.client.auth.signOut();
  }

  // --- PRIVACY PRESERVATION ---
  // STRICT RULE: History, cache, private tabs, and cookies are ALWAYS stored locally on device.
  // We explicitly DO NOT provide cloud upload methods for browsing history or private session data.

  // --- BOOKMARK SYNCHRONIZATION (Requires explicit user consent) ---
  public async syncBookmarks(localBookmarks: LocalBookmark[], userConsent: boolean): Promise<LocalBookmark[]> {
    if (!this.client || !userConsent) {
      // If consent not given, keep everything local only
      return localBookmarks;
    }

    const user = await this.getCurrentUser();
    if (!user) return localBookmarks;

    try {
      // 1. Fetch remote bookmarks updated since last sync
      const { data: remoteBookmarks, error } = await this.client
        .from('synced_bookmarks')
        .select('*')
        .eq('user_id', user.id);

      if (error) {
        console.warn('Sync fetch warning:', error.message);
        return localBookmarks;
      }

      const mergedMap = new Map<string, LocalBookmark>();

      // Populate local bookmarks first
      for (const lb of localBookmarks) {
        mergedMap.set(lb.id, lb);
      }

      // Reconcile with remote bookmarks using timestamp comparison
      if (remoteBookmarks) {
        for (const rb of remoteBookmarks) {
          const local = mergedMap.get(rb.id);
          if (!local) {
            // New remote bookmark
            if (!rb.is_deleted) {
              mergedMap.set(rb.id, {
                id: rb.id,
                title: rb.title,
                url: rb.url,
                favicon_url: rb.favicon_url,
                folder: rb.folder,
                position: rb.position,
                is_deleted: rb.is_deleted,
                client_updated_at: rb.client_updated_at,
              });
            }
          } else {
            // Conflict resolution: latest client_updated_at wins
            const remoteTime = new Date(rb.client_updated_at).getTime();
            const localTime = new Date(local.client_updated_at).getTime();
            if (remoteTime > localTime) {
              mergedMap.set(rb.id, {
                id: rb.id,
                title: rb.title,
                url: rb.url,
                favicon_url: rb.favicon_url,
                folder: rb.folder,
                position: rb.position,
                is_deleted: rb.is_deleted,
                client_updated_at: rb.client_updated_at,
              });
            }
          }
        }
      }

      // 2. Push unsynced / updated local items to Supabase
      const itemsToUpload = Array.from(mergedMap.values()).map((item) => ({
        id: item.id,
        user_id: user.id,
        title: item.title,
        url: item.url,
        favicon_url: item.favicon_url || null,
        folder: item.folder,
        position: item.position,
        is_deleted: !!item.is_deleted,
        client_updated_at: item.client_updated_at,
      }));

      if (itemsToUpload.length > 0) {
        await this.client.from('synced_bookmarks').upsert(itemsToUpload, { onConflict: 'id' });
      }

      return Array.from(mergedMap.values()).filter((b) => !b.is_deleted);
    } catch (err) {
      console.warn('Offline bookmark sync fallback:', err);
      return localBookmarks;
    }
  }

  // --- USER PREFERENCES ---
  public async getPreferences(): Promise<UserPreferences | null> {
    if (!this.client) return null;
    const user = await this.getCurrentUser();
    if (!user) return null;

    const { data, error } = await this.client
      .from('user_preferences')
      .select('*')
      .eq('user_id', user.id)
      .single();

    if (error || !data) return null;
    return data as UserPreferences;
  }

  public async updatePreferences(prefs: Partial<UserPreferences>) {
    if (!this.client) return;
    const user = await this.getCurrentUser();
    if (!user) return;

    await this.client
      .from('user_preferences')
      .update(prefs)
      .eq('user_id', user.id);
  }
}

export const NovyxClient = new NovyxSupabaseService();
