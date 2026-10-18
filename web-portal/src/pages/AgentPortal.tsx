import React, { useState, useEffect } from 'react';
import {
  Users,
  Award,
  Send,
  Copy,
  Check,
  Share2,
  TrendingUp,
  DollarSign,
  ArrowUpRight,
} from 'lucide-react';
import { PortalStorage } from '../lib/supabase';

export const AgentPortal: React.FC = () => {
  const [agent, setAgent] = useState<any>(null);
  const [downlines, setDownlines] = useState<any[]>([]);
  const [copied, setCopied] = useState(false);

  // Transfer state
  const [targetPhone, setTargetPhone] = useState('');
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [transferSuccess, setTransferSuccess] = useState<string | null>(null);

  useEffect(() => {
    loadAgentData();
  }, []);

  const loadAgentData = () => {
    const users = PortalStorage.getUsers();
    // Default to Master Agent Rahul
    const currentAgent = users.find((u) => u.role === 'agent') || users[1];
    setAgent(currentAgent);

    if (currentAgent) {
      const myMembers = users.filter((u) => u.referred_by === currentAgent.id);
      setDownlines(myMembers);
    }
  };

  const handleCopyLink = () => {
    if (!agent) return;
    const link = `https://novapay.app/join?ref=${agent.referral_code}`;
    navigator.clipboard.writeText(link);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleTransfer = (e: React.FormEvent) => {
    e.preventDefault();
    if (!agent) return;
    const transferAmt = parseFloat(amount);
    if (!targetPhone.trim()) {
      alert('Please enter member phone number');
      return;
    }
    if (isNaN(transferAmt) || transferAmt <= 0) {
      alert('Enter a valid transfer amount');
      return;
    }
    if (transferAmt > agent.itoken_balance) {
      alert('Insufficient agent balance');
      return;
    }

    const users = PortalStorage.getUsers();
    const recipient = users.find((u) => u.phone === targetPhone.trim());
    if (!recipient) {
      alert(`User with phone ${targetPhone} was not found.`);
      return;
    }

    // Execute transfer
    const agentObj = users.find((u) => u.id === agent.id);
    agentObj.itoken_balance -= transferAmt;
    recipient.itoken_balance += transferAmt;

    PortalStorage.saveUsers(users);
    setTransferSuccess(`Successfully transferred ₹${transferAmt.toFixed(2)} to ${recipient.name} (${recipient.phone})`);
    setAmount('');
    setNote('');
    loadAgentData();

    setTimeout(() => setTransferSuccess(null), 4000);
  };

  if (!agent) return null;

  return (
    <div style={{ maxWidth: 1400, margin: '24px auto', padding: '0 24px' }}>
      {/* AGENT HERO BANNER */}
      <div className="glass-panel-glow" style={{ padding: 24, marginBottom: 24 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
              <span style={{ fontSize: 11, fontWeight: 900, background: 'rgba(245, 158, 11, 0.2)', color: '#F59E0B', border: '1px solid #F59E0B', padding: '3px 8px', borderRadius: 6 }}>
                OFFICIAL AGENT DESK
              </span>
              <span style={{ fontSize: 12, color: '#10B981', fontWeight: 700 }}>
                ● Active Affiliate Status
              </span>
            </div>
            <h2 style={{ fontSize: 24, fontWeight: 900, color: '#F8FAFC' }}>
              Welcome back, {agent.name}
            </h2>
            <p style={{ fontSize: 13, color: '#94A3B8', marginTop: 4 }}>
              Registered Agent Phone: <strong style={{ color: '#00F0FF' }}>{agent.phone}</strong> • Referral Code: <strong style={{ color: '#F59E0B' }}>{agent.referral_code}</strong>
            </p>
          </div>

          <div style={{ display: 'flex', gap: 10 }}>
            <button onClick={handleCopyLink} className="btn-primary">
              {copied ? <Check size={16} /> : <Copy size={16} />}
              {copied ? 'Link Copied!' : 'Copy Invitation Link'}
            </button>
          </div>
        </div>
      </div>

      {/* AGENT METRICS */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16, marginBottom: 24 }}>
        <div className="glass-panel" style={{ padding: 20 }}>
          <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>WALLET BALANCE</span>
          <div style={{ fontSize: 26, fontWeight: 900, color: '#00F0FF', marginTop: 6 }}>
            ₹{agent.itoken_balance.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </div>
          <span style={{ fontSize: 11, color: '#94A3B8' }}>Available for downline transfers</span>
        </div>

        <div className="glass-panel" style={{ padding: 20 }}>
          <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>TEAM MEMBERS</span>
          <div style={{ fontSize: 26, fontWeight: 900, color: '#F8FAFC', marginTop: 6 }}>
            {downlines.length} Members
          </div>
          <span style={{ fontSize: 11, color: '#10B981', fontWeight: 600 }}>Active registered downlines</span>
        </div>

        <div className="glass-panel" style={{ padding: 20 }}>
          <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>TODAY'S PROFIT</span>
          <div style={{ fontSize: 26, fontWeight: 900, color: '#10B981', marginTop: 6 }}>
            +₹{agent.today_profit.toFixed(2)}
          </div>
          <span style={{ fontSize: 11, color: '#10B981', fontWeight: 600 }}>Automated 3.0% yield</span>
        </div>

        <div className="glass-panel" style={{ padding: 20 }}>
          <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>COMMISSION TIER</span>
          <div style={{ fontSize: 26, fontWeight: 900, color: '#F59E0B', marginTop: 6 }}>
            1.50% + 0.50%
          </div>
          <span style={{ fontSize: 11, color: '#F59E0B', fontWeight: 600 }}>Direct & Indirect payouts</span>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 380px', gap: 20 }}>
        {/* DOWNLINE MEMBERS TABLE */}
        <div className="glass-panel" style={{ overflow: 'hidden' }}>
          <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E', display: 'flex', justifyContent: 'space-between' }}>
            <h3 style={{ fontSize: 16, fontWeight: 800 }}>My Downline Team ({downlines.length})</h3>
            <span style={{ fontSize: 12, color: '#94A3B8' }}>Members registered with your code</span>
          </div>

          {downlines.length === 0 ? (
            <div style={{ padding: 32, textAlign: 'center', color: '#64748B' }}>
              No downline members registered yet. Share your invite link to grow your network!
            </div>
          ) : (
            <table className="portal-table">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>Phone Number</th>
                  <th>Wallet Balance</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {downlines.map((m) => (
                  <tr key={m.id}>
                    <td style={{ fontWeight: 800 }}>{m.name}</td>
                    <td style={{ fontFamily: 'monospace', color: '#00F0FF' }}>{m.phone}</td>
                    <td style={{ fontWeight: 800, color: '#10B981' }}>
                      ₹{m.itoken_balance.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </td>
                    <td>
                      <span style={{ padding: '3px 6px', borderRadius: 4, background: 'rgba(16, 185, 129, 0.15)', color: '#10B981', fontSize: 11, fontWeight: 700 }}>
                        {m.status}
                      </span>
                    </td>
                    <td>
                      <button
                        onClick={() => setTargetPhone(m.phone)}
                        className="btn-secondary"
                        style={{ fontSize: 11, padding: '4px 8px' }}
                      >
                        Quick Top-up
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* DIRECT TOKEN TRANSFER FORM */}
        <div className="glass-panel" style={{ padding: 20 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 14 }}>
            <Send size={18} color="#00F0FF" />
            <h3 style={{ fontSize: 16, fontWeight: 800 }}>Instant Token Transfer</h3>
          </div>
          <p style={{ fontSize: 12, color: '#94A3B8', marginBottom: 16 }}>
            Top-up your downline member's balance directly from your agent balance without bank delay.
          </p>

          {transferSuccess && (
            <div style={{ background: 'rgba(16, 185, 129, 0.2)', border: '1px solid #10B981', padding: '10px 14px', borderRadius: 8, color: '#10B981', fontSize: 12, fontWeight: 700, marginBottom: 14 }}>
              {transferSuccess}
            </div>
          )}

          <form onSubmit={handleTransfer}>
            <div style={{ marginBottom: 12 }}>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                Member Phone Number
              </label>
              <input
                type="text"
                placeholder="e.g. 7777777777"
                value={targetPhone}
                onChange={(e) => setTargetPhone(e.target.value)}
                style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC', fontWeight: 700 }}
              />
            </div>

            <div style={{ marginBottom: 12 }}>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                Transfer Amount (iTokens)
              </label>
              <input
                type="number"
                placeholder="e.g. 1000"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#00F0FF', fontWeight: 900, fontSize: 16 }}
              />
            </div>

            <div style={{ marginBottom: 16 }}>
              <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                Note (Optional)
              </label>
              <input
                type="text"
                placeholder="e.g. Agent downline recharge"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC' }}
              />
            </div>

            <button type="submit" className="btn-primary" style={{ width: '100%', justifyContent: 'center', padding: '12px' }}>
              Confirm & Transfer iTokens
            </button>
          </form>
        </div>
      </div>
    </div>
  );
};
