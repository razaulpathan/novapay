import React from 'react';
import { ShieldCheck, Users, Database, LogOut } from 'lucide-react';

interface NavbarProps {
  currentView: 'admin' | 'agent';
  onViewChange: (view: 'admin' | 'agent') => void;
  onOpenSupabaseModal: () => void;
}

export const AdminNavbar: React.FC<NavbarProps> = ({
  currentView,
  onViewChange,
  onOpenSupabaseModal,
}) => {
  return (
    <header className="glass-panel" style={{ borderRadius: 0, borderTop: 'none', borderLeft: 'none', borderRight: 'none', padding: '14px 28px' }}>
      <div style={{ maxWidth: 1400, margin: '0 auto', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        {/* LOGO & TITLE */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
          <img
            src="/logo.png"
            alt="Nova Pay"
            style={{ width: 42, height: 42, borderRadius: 10, border: '1px solid #00F0FF' }}
          />
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span style={{ fontSize: 18, fontWeight: 900, letterSpacing: '1px', color: '#F8FAFC' }}>
                NOVA PAY
              </span>
              <span style={{ fontSize: 10, fontWeight: 800, padding: '2px 8px', borderRadius: 4, background: 'rgba(0, 240, 255, 0.15)', color: '#00F0FF', border: '1px solid #00F0FF' }}>
                MANAGEMENT CLOUD
              </span>
            </div>
            <span style={{ fontSize: 11, color: '#64748B' }}>
              Vercel Serverless Ready • Supabase Powered
            </span>
          </div>
        </div>

        {/* NAVIGATION PILLS */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, background: '#0E1728', padding: '4px', borderRadius: 12, border: '1px solid #1E2E4E' }}>
          <button
            onClick={() => onViewChange('admin')}
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: 6,
              padding: '8px 16px',
              borderRadius: 8,
              border: 'none',
              background: currentView === 'admin' ? '#00F0FF' : 'transparent',
              color: currentView === 'admin' ? '#070B14' : '#94A3B8',
              fontWeight: 800,
              fontSize: 13,
              cursor: 'pointer',
              transition: 'all 0.2s',
            }}
          >
            <ShieldCheck size={16} />
            Admin Dashboard
          </button>

          <button
            onClick={() => onViewChange('agent')}
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: 6,
              padding: '8px 16px',
              borderRadius: 8,
              border: 'none',
              background: currentView === 'agent' ? '#F59E0B' : 'transparent',
              color: currentView === 'agent' ? '#070B14' : '#94A3B8',
              fontWeight: 800,
              fontSize: 13,
              cursor: 'pointer',
              transition: 'all 0.2s',
            }}
          >
            <Users size={16} />
            Agent Portal
          </button>
        </div>

        {/* SUPABASE STATUS & ACTIONS */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <button
            onClick={onOpenSupabaseModal}
            className="btn-secondary"
            style={{ fontSize: 12, padding: '6px 12px' }}
          >
            <Database size={14} color="#10B981" />
            Supabase Config
          </button>

          <div style={{ display: 'flex', alignItems: 'center', gap: 8, borderLeft: '1px solid #1E2E4E', paddingLeft: 12 }}>
            <div style={{ width: 34, height: 34, borderRadius: 17, background: 'rgba(0, 240, 255, 0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #00F0FF' }}>
              <span style={{ fontSize: 12, fontWeight: 900, color: '#00F0FF' }}>AD</span>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
};
