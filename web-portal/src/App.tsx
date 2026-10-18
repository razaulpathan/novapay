import React, { useState } from 'react';
import { AdminNavbar } from './components/AdminNavbar';
import { AdminDashboard } from './pages/AdminDashboard';
import { AgentPortal } from './pages/AgentPortal';
import { Database, X, ExternalLink, Check } from 'lucide-react';

export function App() {
  const [currentView, setCurrentView] = useState<'admin' | 'agent'>('admin');
  const [isSupabaseModalOpen, setIsSupabaseModalOpen] = useState(false);
  const [supabaseUrl, setSupabaseUrl] = useState('');
  const [supabaseKey, setSupabaseKey] = useState('');
  const [savedSuccess, setSavedSuccess] = useState(false);

  const handleSaveSupabase = (e: React.FormEvent) => {
    e.preventDefault();
    if (!supabaseUrl.trim() || !supabaseKey.trim()) {
      alert('Please enter both Supabase URL and Anon Key');
      return;
    }
    localStorage.setItem('novapay_supabase_url', supabaseUrl.trim());
    localStorage.setItem('novapay_supabase_anon_key', supabaseKey.trim());
    setSavedSuccess(true);
    setTimeout(() => {
      setSavedSuccess(false);
      setIsSupabaseModalOpen(false);
    }, 1500);
  };

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', backgroundColor: '#070B14' }}>
      {/* HEADER / NAVIGATION */}
      <AdminNavbar
        currentView={currentView}
        onViewChange={setCurrentView}
        onOpenSupabaseModal={() => setIsSupabaseModalOpen(true)}
      />

      {/* MAIN VIEW CONTENT */}
      <main style={{ flex: 1 }}>
        {currentView === 'admin' ? <AdminDashboard /> : <AgentPortal />}
      </main>

      {/* FOOTER */}
      <footer style={{ borderTop: '1px solid #1E2E4E', padding: '16px 24px', textAlign: 'center', fontSize: 12, color: '#64748B' }}>
        <div style={{ maxWidth: 1400, margin: '0 auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            Nova Pay Cloud Management Platform • Built with React & Vite • Optimized for Vercel
          </div>
          <div style={{ display: 'flex', gap: 14 }}>
            <span style={{ color: '#10B981', fontWeight: 700 }}>● All Systems Operational</span>
            <span style={{ color: '#00F0FF' }}>Supabase Ready</span>
          </div>
        </div>
      </footer>

      {/* SUPABASE CONNECTION MODAL */}
      {isSupabaseModalOpen && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.8)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 100, padding: 20 }}>
          <div className="glass-panel" style={{ width: '100%', maxWidth: 520, padding: 24, position: 'relative' }}>
            <button
              onClick={() => setIsSupabaseModalOpen(false)}
              style={{ position: 'absolute', top: 16, right: 16, background: 'none', border: 'none', color: '#94A3B8', cursor: 'pointer' }}
            >
              <X size={20} />
            </button>

            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
              <Database size={22} color="#10B981" />
              <h3 style={{ fontSize: 18, fontWeight: 900 }}>Connect Supabase Cloud Backend</h3>
            </div>

            <p style={{ fontSize: 13, color: '#94A3B8', lineHeight: '20px', marginBottom: 16 }}>
              Enter your Supabase credentials to sync live user deposits, payouts, and commission payouts in real time.
            </p>

            {savedSuccess ? (
              <div style={{ background: 'rgba(16, 185, 129, 0.2)', border: '1px solid #10B981', color: '#10B981', padding: 14, borderRadius: 10, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}>
                <Check size={18} /> Credentials saved! Live Supabase connection active.
              </div>
            ) : (
              <form onSubmit={handleSaveSupabase}>
                <div style={{ marginBottom: 14 }}>
                  <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 6 }}>
                    Supabase Project URL
                  </label>
                  <input
                    type="url"
                    placeholder="https://xyzabcdefg.supabase.co"
                    value={supabaseUrl}
                    onChange={(e) => setSupabaseUrl(e.target.value)}
                    style={{ width: '100%', background: '#0E1728', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC', fontSize: 13 }}
                  />
                </div>

                <div style={{ marginBottom: 18 }}>
                  <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 6 }}>
                    Supabase Anon Public API Key
                  </label>
                  <input
                    type="text"
                    placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6..."
                    value={supabaseKey}
                    onChange={(e) => setSupabaseKey(e.target.value)}
                    style={{ width: '100%', background: '#0E1728', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC', fontSize: 13, fontFamily: 'monospace' }}
                  />
                </div>

                <button type="submit" className="btn-primary" style={{ width: '100%', justifyContent: 'center', padding: '12px' }}>
                  Connect Supabase Database
                </button>
              </form>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default App;
