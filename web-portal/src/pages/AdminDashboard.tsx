import React, { useState, useEffect } from 'react';
import {
  TrendingUp,
  Clock,
  CheckCircle2,
  XCircle,
  Users,
  Settings,
  DollarSign,
  ArrowDownCircle,
  ArrowUpCircle,
  Search,
  Save,
  Check,
} from 'lucide-react';
import { PortalStorage } from '../lib/supabase';

export const AdminDashboard: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'deposits' | 'withdrawals' | 'users' | 'settings'>('deposits');
  const [users, setUsers] = useState<any[]>([]);
  const [buyOrders, setBuyOrders] = useState<any[]>([]);
  const [sellOrders, setSellOrders] = useState<any[]>([]);
  const [settings, setSettings] = useState<any>({});
  const [searchUser, setSearchUser] = useState('');
  const [savedAlert, setSavedAlert] = useState(false);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = () => {
    setUsers(PortalStorage.getUsers());
    setBuyOrders(PortalStorage.getBuyOrders());
    setSellOrders(PortalStorage.getSellOrders());
    setSettings(PortalStorage.getSettings());
  };

  const handleApproveDeposit = (orderId: number) => {
    const orders = [...buyOrders];
    const target = orders.find((o) => o.id === orderId);
    if (!target) return;

    target.status = 'Success';
    PortalStorage.saveBuyOrders(orders);

    // Credit user balance
    const userList = [...users];
    const user = userList.find((u) => u.id === target.user_id);
    if (user) {
      user.itoken_balance += target.itokens;
      user.today_profit += target.reward;

      // Credit referrer commission
      if (user.referred_by) {
        const ref = userList.find((u) => u.id === user.referred_by);
        if (ref) {
          const comm = Number((target.price * 0.015).toFixed(2));
          ref.itoken_balance += comm;
          ref.today_profit += comm;
        }
      }
      PortalStorage.saveUsers(userList);
    }

    loadData();
  };

  const handleRejectDeposit = (orderId: number) => {
    const reason = prompt('Enter rejection reason:') || 'UTR verification failed';
    const orders = [...buyOrders];
    const target = orders.find((o) => o.id === orderId);
    if (!target) return;
    target.status = 'Cancelled';
    target.cancel_reason = reason;
    PortalStorage.saveBuyOrders(orders);
    loadData();
  };

  const handleApprovePayout = (payoutId: number) => {
    const orders = [...sellOrders];
    const target = orders.find((o) => o.id === payoutId);
    if (!target) return;
    target.status = 'Success';
    PortalStorage.saveSellOrders(orders);
    loadData();
  };

  const handleRoleChange = (userId: number, newRole: string) => {
    const userList = [...users];
    const target = userList.find((u) => u.id === userId);
    if (!target) return;
    target.role = newRole;
    PortalStorage.saveUsers(userList);
    loadData();
  };

  const handleAdjustBalance = (userId: number) => {
    const amountStr = prompt('Enter adjustment amount (e.g. +500 or -500):');
    if (!amountStr) return;
    const delta = parseFloat(amountStr);
    if (isNaN(delta)) return;

    const userList = [...users];
    const target = userList.find((u) => u.id === userId);
    if (!target) return;
    target.itoken_balance = Math.max(0, target.itoken_balance + delta);
    PortalStorage.saveUsers(userList);
    loadData();
  };

  const handleSaveSettings = () => {
    PortalStorage.saveSettings(settings);
    setSavedAlert(true);
    setTimeout(() => setSavedAlert(false), 2500);
  };

  // KPIs
  const totalVolume = buyOrders
    .filter((o) => o.status === 'Success')
    .reduce((sum, o) => sum + o.price, 0);
  const pendingDeposits = buyOrders.filter((o) => o.status === 'Pending').length;
  const pendingPayouts = sellOrders.filter((o) => o.status === 'Paying').length;

  const filteredUsers = users.filter(
    (u) =>
      u.phone.includes(searchUser) ||
      u.name.toLowerCase().includes(searchUser.toLowerCase()) ||
      u.role.includes(searchUser.toLowerCase())
  );

  return (
    <div style={{ maxWidth: 1400, margin: '24px auto', padding: '0 24px' }}>
      {/* KPI METRICS */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16, marginBottom: 24 }}>
        <div className="glass-panel" style={{ padding: 20 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>TOTAL USERS</span>
            <Users size={18} color="#00F0FF" />
          </div>
          <div style={{ fontSize: 28, fontWeight: 900, color: '#F8FAFC', marginTop: 8 }}>{users.length}</div>
          <span style={{ fontSize: 11, color: '#10B981', fontWeight: 600 }}>Active registered accounts</span>
        </div>

        <div className="glass-panel" style={{ padding: 20 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>APPROVED VOLUME</span>
            <TrendingUp size={18} color="#10B981" />
          </div>
          <div style={{ fontSize: 28, fontWeight: 900, color: '#10B981', marginTop: 8 }}>
            ₹{totalVolume.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
          </div>
          <span style={{ fontSize: 11, color: '#94A3B8' }}>Total deposit turnover</span>
        </div>

        <div className="glass-panel-glow" style={{ padding: 20 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#00F0FF' }}>PENDING UTRs</span>
            <Clock size={18} color="#00F0FF" />
          </div>
          <div style={{ fontSize: 28, fontWeight: 900, color: '#00F0FF', marginTop: 8 }}>
            {pendingDeposits}
          </div>
          <span style={{ fontSize: 11, color: '#00F0FF', fontWeight: 600 }}>Requires manual verification</span>
        </div>

        <div className="glass-panel" style={{ padding: 20 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>PENDING PAYOUTS</span>
            <ArrowUpCircle size={18} color="#F59E0B" />
          </div>
          <div style={{ fontSize: 28, fontWeight: 900, color: '#F59E0B', marginTop: 8 }}>
            {pendingPayouts}
          </div>
          <span style={{ fontSize: 11, color: '#F59E0B', fontWeight: 600 }}>Queued UPI withdrawals</span>
        </div>
      </div>

      {/* TABS NAVIGATION */}
      <div style={{ display: 'flex', gap: 12, borderBottom: '1px solid #1E2E4E', paddingBottom: 12, marginBottom: 20 }}>
        <button
          onClick={() => setActiveTab('deposits')}
          className={activeTab === 'deposits' ? 'btn-primary' : 'btn-secondary'}
        >
          <ArrowDownCircle size={16} />
          Deposit Orders ({pendingDeposits})
        </button>

        <button
          onClick={() => setActiveTab('withdrawals')}
          className={activeTab === 'withdrawals' ? 'btn-primary' : 'btn-secondary'}
        >
          <ArrowUpCircle size={16} />
          Withdrawals ({pendingPayouts})
        </button>

        <button
          onClick={() => setActiveTab('users')}
          className={activeTab === 'users' ? 'btn-primary' : 'btn-secondary'}
        >
          <Users size={16} />
          Users & Agents ({users.length})
        </button>

        <button
          onClick={() => setActiveTab('settings')}
          className={activeTab === 'settings' ? 'btn-primary' : 'btn-secondary'}
        >
          <Settings size={16} />
          Platform Settings
        </button>
      </div>

      {/* TAB 1: DEPOSIT ORDERS */}
      {activeTab === 'deposits' && (
        <div className="glass-panel" style={{ overflow: 'hidden' }}>
          <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E', display: 'flex', justifyContent: 'space-between' }}>
            <h3 style={{ fontSize: 16, fontWeight: 800 }}>Deposit Orders (UTR Verification)</h3>
            <span style={{ fontSize: 12, color: '#94A3B8' }}>Approve valid UTR numbers to credit member balance</span>
          </div>

          <table className="portal-table">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>User / Phone</th>
                <th>Type</th>
                <th>Amount (INR)</th>
                <th>Reward Tokens</th>
                <th>UTR Reference Number</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {buyOrders.map((ord) => (
                <tr key={ord.id}>
                  <td style={{ fontWeight: 800, color: '#00F0FF' }}>{ord.order_no}</td>
                  <td>
                    <div style={{ fontWeight: 700 }}>{ord.user_name || 'Member'}</div>
                    <div style={{ fontSize: 11, color: '#64748B' }}>{ord.user_phone}</div>
                  </td>
                  <td>
                    <span style={{ padding: '3px 8px', borderRadius: 6, background: '#15223C', fontSize: 11, fontWeight: 700 }}>
                      {ord.type}
                    </span>
                  </td>
                  <td style={{ fontWeight: 800 }}>₹{ord.price.toFixed(2)}</td>
                  <td style={{ color: '#10B981', fontWeight: 800 }}>+{ord.itokens.toFixed(2)}</td>
                  <td>
                    <span style={{ background: '#0E1728', border: '1px solid #1E2E4E', padding: '4px 8px', borderRadius: 6, fontFamily: 'monospace', fontWeight: 700, color: '#F59E0B' }}>
                      {ord.utr || 'Pending submission'}
                    </span>
                  </td>
                  <td>
                    <span
                      style={{
                        padding: '4px 8px',
                        borderRadius: 6,
                        fontSize: 11,
                        fontWeight: 800,
                        background:
                          ord.status === 'Success'
                            ? 'rgba(16, 185, 129, 0.15)'
                            : ord.status === 'Pending'
                            ? 'rgba(245, 158, 11, 0.15)'
                            : 'rgba(239, 68, 68, 0.15)',
                        color:
                          ord.status === 'Success'
                            ? '#10B981'
                            : ord.status === 'Pending'
                            ? '#F59E0B'
                            : '#EF4444',
                      }}
                    >
                      {ord.status}
                    </span>
                  </td>
                  <td>
                    {ord.status === 'Pending' ? (
                      <div style={{ display: 'flex', gap: 6 }}>
                        <button
                          onClick={() => handleApproveDeposit(ord.id)}
                          style={{
                            background: '#10B981',
                            color: '#070B14',
                            border: 'none',
                            padding: '6px 12px',
                            borderRadius: 6,
                            fontWeight: 800,
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 4,
                            fontSize: 12,
                          }}
                        >
                          <CheckCircle2 size={14} /> Approve
                        </button>
                        <button
                          onClick={() => handleRejectDeposit(ord.id)}
                          style={{
                            background: 'rgba(239, 68, 68, 0.15)',
                            color: '#EF4444',
                            border: '1px solid rgba(239, 68, 68, 0.3)',
                            padding: '6px 12px',
                            borderRadius: 6,
                            fontWeight: 700,
                            cursor: 'pointer',
                            fontSize: 12,
                          }}
                        >
                          Reject
                        </button>
                      </div>
                    ) : (
                      <span style={{ fontSize: 11, color: '#64748B' }}>Completed</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB 2: WITHDRAWALS */}
      {activeTab === 'withdrawals' && (
        <div className="glass-panel" style={{ overflow: 'hidden' }}>
          <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E' }}>
            <h3 style={{ fontSize: 16, fontWeight: 800 }}>Payout Requests (Sell Orders)</h3>
          </div>

          <table className="portal-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Member</th>
                <th>Amount</th>
                <th>Payment Partner</th>
                <th>Target UPI Handle</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {sellOrders.map((ord) => (
                <tr key={ord.id}>
                  <td>#{ord.id}</td>
                  <td>
                    <div style={{ fontWeight: 700 }}>{ord.user_name}</div>
                    <div style={{ fontSize: 11, color: '#64748B' }}>{ord.user_phone}</div>
                  </td>
                  <td style={{ fontWeight: 800, color: '#EF4444' }}>-₹{ord.amount.toFixed(2)}</td>
                  <td>{ord.partner}</td>
                  <td style={{ fontFamily: 'monospace', fontWeight: 700, color: '#00F0FF' }}>{ord.upi_no}</td>
                  <td>
                    <span
                      style={{
                        padding: '4px 8px',
                        borderRadius: 6,
                        fontSize: 11,
                        fontWeight: 800,
                        background: ord.status === 'Success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)',
                        color: ord.status === 'Success' ? '#10B981' : '#F59E0B',
                      }}
                    >
                      {ord.status}
                    </span>
                  </td>
                  <td>
                    {ord.status === 'Paying' ? (
                      <button
                        onClick={() => handleApprovePayout(ord.id)}
                        className="btn-primary"
                        style={{ fontSize: 12, padding: '6px 12px' }}
                      >
                        <Check size={14} /> Mark Payout Sent
                      </button>
                    ) : (
                      <span style={{ fontSize: 11, color: '#10B981', fontWeight: 700 }}>Settled</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB 3: USERS & AGENTS */}
      {activeTab === 'users' && (
        <div className="glass-panel" style={{ overflow: 'hidden' }}>
          <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h3 style={{ fontSize: 16, fontWeight: 800 }}>User & Agent Directory</h3>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, background: '#0E1728', border: '1px solid #1E2E4E', padding: '6px 12px', borderRadius: 8 }}>
              <Search size={14} color="#64748B" />
              <input
                type="text"
                placeholder="Search phone, name, role..."
                value={searchUser}
                onChange={(e) => setSearchUser(e.target.value)}
                style={{ background: 'transparent', border: 'none', color: '#F8FAFC', outline: 'none', fontSize: 12 }}
              />
            </div>
          </div>

          <table className="portal-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name / Phone</th>
                <th>Role</th>
                <th>Wallet Balance</th>
                <th>Referral Code</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredUsers.map((u) => (
                <tr key={u.id}>
                  <td>#{u.id}</td>
                  <td>
                    <div style={{ fontWeight: 700 }}>{u.name}</div>
                    <div style={{ fontSize: 11, color: '#64748B' }}>{u.phone}</div>
                  </td>
                  <td>
                    <select
                      value={u.role}
                      onChange={(e) => handleRoleChange(u.id, e.target.value)}
                      style={{
                        background: '#15223C',
                        color: u.role === 'agent' ? '#F59E0B' : u.role === 'admin' ? '#00F0FF' : '#F8FAFC',
                        border: '1px solid #1E2E4E',
                        padding: '4px 8px',
                        borderRadius: 6,
                        fontWeight: 800,
                        fontSize: 11,
                        cursor: 'pointer',
                      }}
                    >
                      <option value="user">Member (User)</option>
                      <option value="agent">VIP Agent</option>
                      <option value="sub_admin">Sub Admin</option>
                      <option value="admin">Super Admin</option>
                    </select>
                  </td>
                  <td style={{ fontWeight: 800, color: '#00F0FF' }}>
                    ₹{u.itoken_balance.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                  </td>
                  <td style={{ fontFamily: 'monospace', fontWeight: 700 }}>{u.referral_code}</td>
                  <td>
                    <span style={{ padding: '3px 6px', borderRadius: 4, background: 'rgba(16, 185, 129, 0.15)', color: '#10B981', fontSize: 11, fontWeight: 700 }}>
                      {u.status}
                    </span>
                  </td>
                  <td>
                    <button
                      onClick={() => handleAdjustBalance(u.id)}
                      className="btn-secondary"
                      style={{ fontSize: 11, padding: '4px 8px' }}
                    >
                      Adjust Balance
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB 4: SETTINGS */}
      {activeTab === 'settings' && (
        <div className="glass-panel" style={{ padding: 24 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
            <div>
              <h3 style={{ fontSize: 18, fontWeight: 900 }}>Platform Payment & Commission Rules</h3>
              <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 4 }}>
                Configure official receiving details, crypto rates, and affiliate reward percentages.
              </p>
            </div>
            <button onClick={handleSaveSettings} className="btn-primary">
              <Save size={16} /> Save Changes
            </button>
          </div>

          {savedAlert && (
            <div style={{ background: 'rgba(16, 185, 129, 0.2)', border: '1px solid #10B981', padding: '10px 16px', borderRadius: 8, color: '#10B981', fontWeight: 700, marginBottom: 16 }}>
              Settings saved and updated successfully across all mobile and web channels!
            </div>
          )}

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
            <div style={{ background: '#0E1728', padding: 18, borderRadius: 12, border: '1px solid #1E2E4E' }}>
              <h4 style={{ fontSize: 14, fontWeight: 800, color: '#00F0FF', marginBottom: 14 }}>
                UPI & Bank Receiving Details
              </h4>

              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                  Admin Deposit UPI ID
                </label>
                <input
                  type="text"
                  value={settings.admin_upi_id || ''}
                  onChange={(e) => setSettings({ ...settings, admin_upi_id: e.target.value })}
                  style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC', fontWeight: 700 }}
                />
              </div>

              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                  Account Holder Name
                </label>
                <input
                  type="text"
                  value={settings.admin_bank_holder || ''}
                  onChange={(e) => setSettings({ ...settings, admin_bank_holder: e.target.value })}
                  style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC' }}
                />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <div>
                  <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                    Bank Account Number
                  </label>
                  <input
                    type="text"
                    value={settings.admin_bank_account || ''}
                    onChange={(e) => setSettings({ ...settings, admin_bank_account: e.target.value })}
                    style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC' }}
                  />
                </div>
                <div>
                  <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                    IFSC Code
                  </label>
                  <input
                    type="text"
                    value={settings.admin_bank_ifsc || ''}
                    onChange={(e) => setSettings({ ...settings, admin_bank_ifsc: e.target.value })}
                    style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC' }}
                  />
                </div>
              </div>
            </div>

            <div style={{ background: '#0E1728', padding: 18, borderRadius: 12, border: '1px solid #1E2E4E' }}>
              <h4 style={{ fontSize: 14, fontWeight: 800, color: '#F59E0B', marginBottom: 14 }}>
                USDT Crypto & Affiliate Rates
              </h4>

              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                  Official USDT (TRC-20) Address
                </label>
                <input
                  type="text"
                  value={settings.admin_usdt_address || ''}
                  onChange={(e) => setSettings({ ...settings, admin_usdt_address: e.target.value })}
                  style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC', fontFamily: 'monospace' }}
                />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 12 }}>
                <div>
                  <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                    1 USDT = INR Rate (₹)
                  </label>
                  <input
                    type="number"
                    value={settings.exchange_rate_usdt || 92.5}
                    onChange={(e) => setSettings({ ...settings, exchange_rate_usdt: parseFloat(e.target.value) })}
                    style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC', fontWeight: 800 }}
                  />
                </div>
                <div>
                  <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                    Deposit Bonus Rate (%)
                  </label>
                  <input
                    type="number"
                    value={settings.default_reward_percentage || 2.5}
                    onChange={(e) => setSettings({ ...settings, default_reward_percentage: parseFloat(e.target.value) })}
                    style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#10B981', fontWeight: 800 }}
                  />
                </div>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <div>
                  <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                    Level 1 Direct Comm (%)
                  </label>
                  <input
                    type="number"
                    value={settings.commission_level1_percent || 1.5}
                    onChange={(e) => setSettings({ ...settings, commission_level1_percent: parseFloat(e.target.value) })}
                    style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F59E0B', fontWeight: 800 }}
                  />
                </div>
                <div>
                  <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                    Level 2 Indirect Comm (%)
                  </label>
                  <input
                    type="number"
                    value={settings.commission_level2_percent || 0.5}
                    onChange={(e) => setSettings({ ...settings, commission_level2_percent: parseFloat(e.target.value) })}
                    style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F59E0B', fontWeight: 800 }}
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
