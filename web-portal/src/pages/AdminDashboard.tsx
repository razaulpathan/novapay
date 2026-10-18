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
  Send,
  MessageSquare,
  Bell,
  CreditCard,
  Bot,
  Image as ImageIcon,
  UserCheck,
  Database,
  Plus,
  Trash2,
  ExternalLink,
  RefreshCw,
  Edit3,
} from 'lucide-react';
import { PortalStorage } from '../lib/supabase';

export type AdminSection =
  | 'dashboard'
  | 'users'
  | 'buy_orders'
  | 'sell_orders'
  | 'token_transfers'
  | 'support_tickets'
  | 'settings'
  | 'announcements'
  | 'partners'
  | 'telegram'
  | 'banners'
  | 'transfer_user'
  | 'backup';

export const AdminDashboard: React.FC = () => {
  const [section, setSection] = useState<AdminSection>('dashboard');

  // Core Data States
  const [users, setUsers] = useState<any[]>([]);
  const [buyOrders, setBuyOrders] = useState<any[]>([]);
  const [sellOrders, setSellOrders] = useState<any[]>([]);
  const [settings, setSettings] = useState<any>({});
  const [transfers, setTransfers] = useState<any[]>([]);
  const [tickets, setTickets] = useState<any[]>([]);
  const [announcements, setAnnouncements] = useState<any[]>([]);
  const [partners, setPartners] = useState<any[]>([]);
  const [banners, setBanners] = useState<any[]>([]);
  const [telegramConfig, setTelegramConfig] = useState<any>({});

  // UI / Form States
  const [searchQuery, setSearchQuery] = useState('');
  const [toastMsg, setToastMsg] = useState<string | null>(null);

  // Modals / Inputs
  const [replyTicketId, setReplyTicketId] = useState<number | null>(null);
  const [replyText, setReplyText] = useState('');
  const [newAnnTitle, setNewAnnTitle] = useState('');
  const [newAnnBody, setNewAnnBody] = useState('');
  const [newAnnBadge, setNewAnnBadge] = useState('HOT');
  const [newPartnerName, setNewPartnerName] = useState('');
  const [newPartnerType, setNewPartnerType] = useState('Sell');
  const [newBannerTitle, setNewBannerTitle] = useState('');
  const [newBannerSubtitle, setNewBannerSubtitle] = useState('');
  const [newBannerImage, setNewBannerImage] = useState('');
  const [transferTargetPhone, setTransferTargetPhone] = useState('');
  const [transferNewAgentPhone, setTransferNewAgentPhone] = useState('');

  useEffect(() => {
    loadAllData();
  }, []);

  const showToast = (msg: string) => {
    setToastMsg(msg);
    setTimeout(() => setToastMsg(null), 3000);
  };

  const loadAllData = () => {
    setUsers(PortalStorage.getUsers());
    setBuyOrders(PortalStorage.getBuyOrders());
    setSellOrders(PortalStorage.getSellOrders());
    setSettings(PortalStorage.getSettings());

    // Load or seed other entities
    const rawTx = localStorage.getItem('np_portal_transfers');
    if (!rawTx) {
      const demoTx = [
        {
          id: 1,
          agent_phone: '8888888888',
          target_phone: '7777777777',
          target_name: 'Vikram Sharma',
          amount: 2500,
          status: 'Success',
          note: 'Downline top-up',
          created_at: '2026-10-18T16:45:00Z',
        },
      ];
      localStorage.setItem('np_portal_transfers', JSON.stringify(demoTx));
      setTransfers(demoTx);
    } else {
      setTransfers(JSON.parse(rawTx));
    }

    const rawTickets = localStorage.getItem('np_portal_tickets');
    if (!rawTickets) {
      const demoTickets = [
        {
          id: 101,
          user_phone: '7777777777',
          user_name: 'Vikram Sharma',
          subject: 'Deposit UTR Verification',
          message: 'I made a deposit of ₹2,000 with UTR 428819204918. Please verify.',
          status: 'Open',
          reply: null,
          created_at: '2026-10-18T16:30:00Z',
        },
        {
          id: 102,
          user_phone: '9123456780',
          user_name: 'Amit Patel',
          subject: 'USDT Deposit Query',
          message: 'Transferred 108 USDT to TRC20 address. When will it reflect?',
          status: 'Replied',
          reply: 'Credited to your wallet balance. Thank you.',
          created_at: '2026-10-18T15:00:00Z',
        },
      ];
      localStorage.setItem('np_portal_tickets', JSON.stringify(demoTickets));
      setTickets(demoTickets);
    } else {
      setTickets(JSON.parse(rawTickets));
    }

    const rawAnn = localStorage.getItem('np_portal_announcements');
    if (!rawAnn) {
      const demoAnn = [
        {
          id: 1,
          title: '🔥 Welcome to Nova Pay 2.0',
          body: 'Instant UPI deposits are now live with 2.5% bonus on every recharge. Agent tier commissions updated.',
          badge: 'HOT',
          is_active: true,
          created_at: '2026-10-18T12:00:00Z',
        },
        {
          id: 2,
          title: '⚡ 24/7 Automated UPI Withdrawals',
          body: 'Sell orders are processed 24/7 to your verified PhonePe / GPay handles.',
          badge: 'NOTICE',
          is_active: true,
          created_at: '2026-10-18T13:00:00Z',
        },
      ];
      localStorage.setItem('np_portal_announcements', JSON.stringify(demoAnn));
      setAnnouncements(demoAnn);
    } else {
      setAnnouncements(JSON.parse(rawAnn));
    }

    const rawPartners = localStorage.getItem('np_portal_partners');
    if (!rawPartners) {
      const demoPartners = [
        { id: 1, name: 'PhonePe', type: 'Both', active: true },
        { id: 2, name: 'Google Pay', type: 'Both', active: true },
        { id: 3, name: 'Paytm Business', type: 'Sell', active: true },
        { id: 4, name: 'BHIM UPI', type: 'Sell', active: true },
        { id: 5, name: 'IndusPay', type: 'Sell', active: true },
        { id: 6, name: 'BharatPe Business', type: 'Sell', active: true },
        { id: 7, name: 'Amazon Pay', type: 'Both', active: true },
      ];
      localStorage.setItem('np_portal_partners', JSON.stringify(demoPartners));
      setPartners(demoPartners);
    } else {
      setPartners(JSON.parse(rawPartners));
    }

    const rawBanners = localStorage.getItem('np_portal_banners');
    if (!rawBanners) {
      const demoBanners = [
        {
          id: 1,
          title: 'High Commission Rewards',
          subtitle: 'Earn up to 25% agent commission on your downline volume.',
          image_url: '/banner.png',
          link_url: '/agent',
          active: true,
        },
      ];
      localStorage.setItem('np_portal_banners', JSON.stringify(demoBanners));
      setBanners(demoBanners);
    } else {
      setBanners(JSON.parse(rawBanners));
    }

    const rawTg = localStorage.getItem('np_portal_telegram');
    if (!rawTg) {
      const demoTg = {
        bot_token: '',
        admin_chat_id: '',
        notify_deposit: true,
        notify_withdrawal: true,
        notify_ticket: true,
      };
      localStorage.setItem('np_portal_telegram', JSON.stringify(demoTg));
      setTelegramConfig(demoTg);
    } else {
      setTelegramConfig(JSON.parse(rawTg));
    }
  };

  // --- ACTIONS ---
  const handleApproveDeposit = (orderId: number) => {
    const orders = [...buyOrders];
    const target = orders.find((o) => o.id === orderId);
    if (!target) return;

    target.status = 'Success';
    PortalStorage.saveBuyOrders(orders);

    const userList = [...users];
    const user = userList.find((u) => u.id === target.user_id);
    if (user) {
      user.itoken_balance += target.itokens;
      user.today_profit += target.reward;

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

    loadAllData();
    showToast(`Order #${target.order_no} Approved! Balance credited.`);
  };

  const handleRejectDeposit = (orderId: number) => {
    const reason = prompt('Enter rejection reason:') || 'Invalid UTR reference number';
    const orders = [...buyOrders];
    const target = orders.find((o) => o.id === orderId);
    if (!target) return;
    target.status = 'Cancelled';
    target.cancel_reason = reason;
    PortalStorage.saveBuyOrders(orders);
    loadAllData();
    showToast(`Order #${target.order_no} Rejected.`);
  };

  const handleApprovePayout = (payoutId: number) => {
    const orders = [...sellOrders];
    const target = orders.find((o) => o.id === payoutId);
    if (!target) return;
    target.status = 'Success';
    PortalStorage.saveSellOrders(orders);
    loadAllData();
    showToast(`Payout #${target.id} marked as Paid!`);
  };

  const handleRejectPayout = (payoutId: number) => {
    const orders = [...sellOrders];
    const target = orders.find((o) => o.id === payoutId);
    if (!target) return;
    target.status = 'Failed';

    // Refund user balance
    const userList = [...users];
    const user = userList.find((u) => u.id === target.user_id);
    if (user) {
      user.itoken_balance += target.amount;
      PortalStorage.saveUsers(userList);
    }

    PortalStorage.saveSellOrders(orders);
    loadAllData();
    showToast(`Payout #${target.id} marked as Failed. Tokens refunded.`);
  };

  const handleRoleChange = (userId: number, newRole: string) => {
    const userList = [...users];
    const target = userList.find((u) => u.id === userId);
    if (!target) return;
    target.role = newRole;
    PortalStorage.saveUsers(userList);
    loadAllData();
    showToast(`User ${target.phone} role updated to ${newRole}`);
  };

  const handleToggleUserStatus = (userId: number) => {
    const userList = [...users];
    const target = userList.find((u) => u.id === userId);
    if (!target) return;
    target.status = target.status === 'Active' ? 'Blocked' : 'Active';
    PortalStorage.saveUsers(userList);
    loadAllData();
    showToast(`User status changed to ${target.status}`);
  };

  const handleAdjustBalance = (userId: number) => {
    const amountStr = prompt('Enter adjustment amount (+500 or -500):');
    if (!amountStr) return;
    const delta = parseFloat(amountStr);
    if (isNaN(delta)) return;

    const userList = [...users];
    const target = userList.find((u) => u.id === userId);
    if (!target) return;
    target.itoken_balance = Math.max(0, target.itoken_balance + delta);
    PortalStorage.saveUsers(userList);
    loadAllData();
    showToast(`Balance updated for ${target.phone}`);
  };

  const handleTransferReferrer = (e: React.FormEvent) => {
    e.preventDefault();
    if (!transferTargetPhone || !transferNewAgentPhone) {
      alert('Please enter both Member Phone and New Agent Phone');
      return;
    }
    const userList = [...users];
    const member = userList.find((u) => u.phone === transferTargetPhone.trim());
    const newAgent = userList.find((u) => u.phone === transferNewAgentPhone.trim());

    if (!member) {
      alert(`Member with phone ${transferTargetPhone} not found`);
      return;
    }
    if (!newAgent) {
      alert(`New Agent with phone ${transferNewAgentPhone} not found`);
      return;
    }

    member.referred_by = newAgent.id;
    PortalStorage.saveUsers(userList);
    loadAllData();
    setTransferTargetPhone('');
    setTransferNewAgentPhone('');
    showToast(`Member ${member.phone} transferred under Agent ${newAgent.name} (${newAgent.phone})!`);
  };

  const handleReplyTicket = (ticketId: number) => {
    if (!replyText.trim()) return;
    const tList = [...tickets];
    const t = tList.find((item) => item.id === ticketId);
    if (!t) return;
    t.reply = replyText.trim();
    t.status = 'Replied';
    localStorage.setItem('np_portal_tickets', JSON.stringify(tList));
    setTickets(tList);
    setReplyTicketId(null);
    setReplyText('');
    showToast(`Replied to Ticket #${ticketId}`);
  };

  const handleAddAnnouncement = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAnnTitle.trim() || !newAnnBody.trim()) return;
    const annList = [...announcements];
    const item = {
      id: Date.now(),
      title: newAnnTitle.trim(),
      body: newAnnBody.trim(),
      badge: newAnnBadge,
      is_active: true,
      created_at: new Date().toISOString(),
    };
    annList.unshift(item);
    localStorage.setItem('np_portal_announcements', JSON.stringify(annList));
    setAnnouncements(annList);
    setNewAnnTitle('');
    setNewAnnBody('');
    showToast('New Announcement Published!');
  };

  const handleDeleteAnnouncement = (id: number) => {
    const filtered = announcements.filter((a) => a.id !== id);
    localStorage.setItem('np_portal_announcements', JSON.stringify(filtered));
    setAnnouncements(filtered);
    showToast('Announcement Deleted.');
  };

  const handleAddPartner = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newPartnerName.trim()) return;
    const list = [...partners];
    list.push({
      id: Date.now(),
      name: newPartnerName.trim(),
      type: newPartnerType,
      active: true,
    });
    localStorage.setItem('np_portal_partners', JSON.stringify(list));
    setPartners(list);
    setNewPartnerName('');
    showToast(`Partner ${newPartnerName} added!`);
  };

  const handleTogglePartner = (id: number) => {
    const list = [...partners];
    const p = list.find((item) => item.id === id);
    if (!p) return;
    p.active = !p.active;
    localStorage.setItem('np_portal_partners', JSON.stringify(list));
    setPartners(list);
  };

  const handleExportBackup = () => {
    const backupData = {
      timestamp: new Date().toISOString(),
      users: PortalStorage.getUsers(),
      buy_orders: PortalStorage.getBuyOrders(),
      sell_orders: PortalStorage.getSellOrders(),
      settings: PortalStorage.getSettings(),
      transfers,
      tickets,
      announcements,
      partners,
    };
    const jsonStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(backupData, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute('href', jsonStr);
    downloadAnchor.setAttribute('download', `novapay_backup_${new Date().toISOString().slice(0, 10)}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
    showToast('Database JSON Backup Downloaded!');
  };

  // KPIs
  const totalVolume = buyOrders
    .filter((o) => o.status === 'Success')
    .reduce((sum, o) => sum + o.price, 0);
  const pendingDeposits = buyOrders.filter((o) => o.status === 'Pending').length;
  const pendingPayouts = sellOrders.filter((o) => o.status === 'Paying').length;
  const openTicketsCount = tickets.filter((t) => t.status === 'Open').length;

  return (
    <div style={{ display: 'flex', minHeight: 'calc(100vh - 71px)' }}>
      {/* ── LEFT SIDEBAR (13 SECTIONS) ───────────────────────── */}
      <aside
        style={{
          width: 250,
          background: '#0B111F',
          borderRight: '1px solid #1E2E4E',
          display: 'flex',
          flexDirection: 'column',
          padding: '16px 8px',
        }}
      >
        <div style={{ fontSize: 10, fontWeight: 800, color: '#64748B', letterSpacing: 1.2, padding: '0 12px 10px', textTransform: 'uppercase' }}>
          Platform Management
        </div>

        <nav style={{ display: 'flex', flexDirection: 'column', gap: 4, flex: 1, overflowY: 'auto' }}>
          {[
            { key: 'dashboard', label: 'Dashboard', icon: TrendingUp },
            { key: 'buy_orders', label: 'Deposit Orders', icon: ArrowDownCircle, badge: pendingDeposits },
            { key: 'sell_orders', label: 'Withdrawal Orders', icon: ArrowUpCircle, badge: pendingPayouts },
            { key: 'users', label: 'Users & Agents', icon: Users, count: users.length },
            { key: 'token_transfers', label: 'Token Transfers', icon: Send },
            { key: 'transfer_user', label: 'Transfer Referrer', icon: UserCheck },
            { key: 'support_tickets', label: 'Support Tickets', icon: MessageSquare, badge: openTicketsCount },
            { key: 'partners', label: 'Payment Partners', icon: CreditCard },
            { key: 'announcements', label: 'News / Broadcasts', icon: Bell },
            { key: 'banners', label: 'Banners & Media', icon: ImageIcon },
            { key: 'telegram', label: 'Telegram Bot', icon: Bot },
            { key: 'settings', label: 'System Settings', icon: Settings },
            { key: 'backup', label: 'Database Backup', icon: Database },
          ].map((item) => {
            const Icon = item.icon;
            const isActive = section === item.key;
            return (
              <button
                key={item.key}
                onClick={() => setSection(item.key as AdminSection)}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 10,
                  padding: '10px 12px',
                  borderRadius: 10,
                  border: 'none',
                  background: isActive ? '#00F0FF' : 'transparent',
                  color: isActive ? '#070B14' : '#94A3B8',
                  fontWeight: isActive ? 900 : 700,
                  fontSize: 13,
                  cursor: 'pointer',
                  textAlign: 'left',
                  transition: 'all 0.15s ease',
                  position: 'relative',
                }}
              >
                <Icon size={16} />
                <span style={{ flex: 1 }}>{item.label}</span>
                {item.badge !== undefined && item.badge > 0 && (
                  <span
                    style={{
                      background: isActive ? '#070B14' : '#EF4444',
                      color: isActive ? '#00F0FF' : '#FFF',
                      fontSize: 10,
                      fontWeight: 900,
                      padding: '2px 6px',
                      borderRadius: 10,
                    }}
                  >
                    {item.badge}
                  </span>
                )}
                {item.count !== undefined && !item.badge && (
                  <span style={{ fontSize: 11, color: isActive ? '#070B14' : '#64748B', fontWeight: 800 }}>
                    {item.count}
                  </span>
                )}
              </button>
            );
          })}
        </nav>
      </aside>

      {/* ── MAIN CONTENT AREA ─────────────────────────────────── */}
      <div style={{ flex: 1, padding: '24px 32px', overflowY: 'auto' }}>
        {/* TOAST ALERT */}
        {toastMsg && (
          <div
            style={{
              position: 'fixed',
              top: 20,
              right: 20,
              background: '#10B981',
              color: '#070B14',
              fontWeight: 800,
              padding: '12px 20px',
              borderRadius: 10,
              zIndex: 999,
              boxShadow: '0 4px 20px rgba(16, 185, 129, 0.4)',
              display: 'flex',
              alignItems: 'center',
              gap: 8,
            }}
          >
            <CheckCircle2 size={18} />
            {toastMsg}
          </div>
        )}

        {/* 1. DASHBOARD */}
        {section === 'dashboard' && (
          <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
              <div>
                <h2 style={{ fontSize: 24, fontWeight: 900, color: '#F8FAFC' }}>Executive Platform Dashboard</h2>
                <p style={{ fontSize: 13, color: '#94A3B8', marginTop: 4 }}>
                  Real-time analytics for user deposits, payouts, token transfers, and agent commissions.
                </p>
              </div>
              <button onClick={loadAllData} className="btn-secondary">
                <RefreshCw size={14} /> Refresh Data
              </button>
            </div>

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
                  <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>APPROVED DEPOSITS</span>
                  <TrendingUp size={18} color="#10B981" />
                </div>
                <div style={{ fontSize: 28, fontWeight: 900, color: '#10B981', marginTop: 8 }}>
                  ₹{totalVolume.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                </div>
                <span style={{ fontSize: 11, color: '#94A3B8' }}>Total turnover volume</span>
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
                  <span style={{ fontSize: 12, fontWeight: 700, color: '#94A3B8' }}>QUEUED PAYOUTS</span>
                  <ArrowUpCircle size={18} color="#F59E0B" />
                </div>
                <div style={{ fontSize: 28, fontWeight: 900, color: '#F59E0B', marginTop: 8 }}>
                  {pendingPayouts}
                </div>
                <span style={{ fontSize: 11, color: '#F59E0B', fontWeight: 600 }}>Queued UPI withdrawals</span>
              </div>
            </div>

            {/* QUICK ACTIONS ROW */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }}>
              <div
                className="glass-panel"
                style={{ padding: 18, cursor: 'pointer' }}
                onClick={() => setSection('buy_orders')}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, color: '#00F0FF', fontWeight: 800 }}>
                  <ArrowDownCircle size={20} />
                  <span>Verify Pending UTRs ({pendingDeposits})</span>
                </div>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 6 }}>
                  One-click approve user bank/UPI payment references and credit tokens.
                </p>
              </div>

              <div
                className="glass-panel"
                style={{ padding: 18, cursor: 'pointer' }}
                onClick={() => setSection('sell_orders')}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, color: '#F59E0B', fontWeight: 800 }}>
                  <ArrowUpCircle size={20} />
                  <span>Process Withdrawals ({pendingPayouts})</span>
                </div>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 6 }}>
                  Send payouts to user PhonePe / Paytm / GPay handles.
                </p>
              </div>

              <div
                className="glass-panel"
                style={{ padding: 18, cursor: 'pointer' }}
                onClick={() => setSection('support_tickets')}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, color: '#10B981', fontWeight: 800 }}>
                  <MessageSquare size={20} />
                  <span>Open Support Tickets ({openTicketsCount})</span>
                </div>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 6 }}>
                  Answer member questions and handle dispute resolutions.
                </p>
              </div>
            </div>
          </div>
        )}

        {/* 2. BUY ORDERS */}
        {section === 'buy_orders' && (
          <div className="glass-panel" style={{ overflow: 'hidden' }}>
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <h3 style={{ fontSize: 18, fontWeight: 900 }}>Deposit Orders (Buy Orders)</h3>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 2 }}>
                  Verify user-submitted 12-digit UTR numbers and approve deposits.
                </p>
              </div>
              <div style={{ display: 'flex', gap: 8 }}>
                <span style={{ fontSize: 12, fontWeight: 800, background: 'rgba(0, 240, 255, 0.1)', color: '#00F0FF', padding: '6px 12px', borderRadius: 8 }}>
                  Pending: {pendingDeposits}
                </span>
              </div>
            </div>

            <table className="portal-table">
              <thead>
                <tr>
                  <th>Order No</th>
                  <th>Member</th>
                  <th>Method</th>
                  <th>Deposit Amount</th>
                  <th>Reward Bonus</th>
                  <th>UTR Reference</th>
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
                      <span style={{ padding: '3px 8px', borderRadius: 6, background: '#15223C', fontSize: 11, fontWeight: 800 }}>
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
                        <span style={{ fontSize: 11, color: '#64748B' }}>Settled</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* 3. SELL ORDERS */}
        {section === 'sell_orders' && (
          <div className="glass-panel" style={{ overflow: 'hidden' }}>
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <h3 style={{ fontSize: 18, fontWeight: 900 }}>Withdrawal Requests (Sell Orders)</h3>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 2 }}>
                  Process user payouts to their linked PhonePe, GPay, or Paytm accounts.
                </p>
              </div>
              <span style={{ fontSize: 12, fontWeight: 800, background: 'rgba(245, 158, 11, 0.1)', color: '#F59E0B', padding: '6px 12px', borderRadius: 8 }}>
                Queued: {pendingPayouts}
              </span>
            </div>

            <table className="portal-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Member</th>
                  <th>Amount</th>
                  <th>Payment Partner</th>
                  <th>Target UPI ID</th>
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
                          background:
                            ord.status === 'Success'
                              ? 'rgba(16, 185, 129, 0.15)'
                              : ord.status === 'Paying'
                              ? 'rgba(245, 158, 11, 0.15)'
                              : 'rgba(239, 68, 68, 0.15)',
                          color:
                            ord.status === 'Success'
                              ? '#10B981'
                              : ord.status === 'Paying'
                              ? '#F59E0B'
                              : '#EF4444',
                        }}
                      >
                        {ord.status}
                      </span>
                    </td>
                    <td>
                      {ord.status === 'Paying' ? (
                        <div style={{ display: 'flex', gap: 6 }}>
                          <button
                            onClick={() => handleApprovePayout(ord.id)}
                            className="btn-primary"
                            style={{ fontSize: 12, padding: '6px 12px' }}
                          >
                            <Check size={14} /> Mark Paid
                          </button>
                          <button
                            onClick={() => handleRejectPayout(ord.id)}
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
                            Reject & Refund
                          </button>
                        </div>
                      ) : (
                        <span style={{ fontSize: 11, color: '#10B981', fontWeight: 700 }}>Complete</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* 4. USERS MANAGEMENT */}
        {section === 'users' && (
          <div className="glass-panel" style={{ overflow: 'hidden' }}>
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <h3 style={{ fontSize: 18, fontWeight: 900 }}>User & Agent Directory</h3>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 2 }}>
                  Manage roles, adjust balances, ban/unban, and inspect downlines.
                </p>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, background: '#0E1728', border: '1px solid #1E2E4E', padding: '6px 12px', borderRadius: 8 }}>
                <Search size={14} color="#64748B" />
                <input
                  type="text"
                  placeholder="Search phone or name..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  style={{ background: 'transparent', border: 'none', color: '#F8FAFC', outline: 'none', fontSize: 12 }}
                />
              </div>
            </div>

            <table className="portal-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Member Name / Phone</th>
                  <th>Role</th>
                  <th>Wallet Balance</th>
                  <th>Referral Code</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {users
                  .filter((u) => u.phone.includes(searchQuery) || u.name.toLowerCase().includes(searchQuery.toLowerCase()))
                  .map((u) => (
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
                          <option value="user">Member</option>
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
                        <button
                          onClick={() => handleToggleUserStatus(u.id)}
                          style={{
                            background: u.status === 'Active' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)',
                            color: u.status === 'Active' ? '#10B981' : '#EF4444',
                            border: 'none',
                            padding: '4px 8px',
                            borderRadius: 6,
                            fontWeight: 800,
                            fontSize: 11,
                            cursor: 'pointer',
                          }}
                        >
                          {u.status}
                        </button>
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

        {/* 5. TOKEN TRANSFERS */}
        {section === 'token_transfers' && (
          <div className="glass-panel" style={{ overflow: 'hidden' }}>
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E' }}>
              <h3 style={{ fontSize: 18, fontWeight: 900 }}>Agent Token Transfers Audit Log</h3>
              <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 2 }}>
                Full record of wallet-to-wallet token transfers sent by agents to downline users.
              </p>
            </div>

            <table className="portal-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Sender Agent Phone</th>
                  <th>Recipient Phone</th>
                  <th>Recipient Name</th>
                  <th>Amount (iTokens)</th>
                  <th>Status</th>
                  <th>Note</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {transfers.map((tx) => (
                  <tr key={tx.id}>
                    <td>#{tx.id}</td>
                    <td style={{ fontFamily: 'monospace', color: '#F59E0B' }}>{tx.agent_phone}</td>
                    <td style={{ fontFamily: 'monospace', color: '#00F0FF' }}>{tx.target_phone}</td>
                    <td>{tx.target_name || 'Member'}</td>
                    <td style={{ fontWeight: 800, color: '#10B981' }}>₹{tx.amount.toFixed(2)}</td>
                    <td>
                      <span style={{ padding: '3px 6px', borderRadius: 4, background: 'rgba(16, 185, 129, 0.15)', color: '#10B981', fontSize: 11, fontWeight: 700 }}>
                        {tx.status}
                      </span>
                    </td>
                    <td style={{ fontSize: 12, color: '#94A3B8' }}>{tx.note}</td>
                    <td style={{ fontSize: 11, color: '#64748B' }}>{new Date(tx.created_at).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* 6. TRANSFER REFERRER (OLD transfer_user.php) */}
        {section === 'transfer_user' && (
          <div className="glass-panel" style={{ padding: 24, maxWidth: 640 }}>
            <h3 style={{ fontSize: 18, fontWeight: 900, marginBottom: 6 }}>Transfer Member Referrer</h3>
            <p style={{ fontSize: 13, color: '#94A3B8', marginBottom: 20 }}>
              Move any existing user from their current agent/referrer to a new agent downline. All future deposit commissions will automatically flow to the new agent.
            </p>

            <form onSubmit={handleTransferReferrer}>
              <div style={{ marginBottom: 14 }}>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#94A3B8', marginBottom: 6 }}>
                  Target Member Phone Number
                </label>
                <input
                  type="text"
                  placeholder="e.g. 7777777777"
                  value={transferTargetPhone}
                  onChange={(e) => setTransferTargetPhone(e.target.value)}
                  style={{ width: '100%', background: '#0E1728', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC', fontWeight: 700 }}
                />
              </div>

              <div style={{ marginBottom: 20 }}>
                <label style={{ display: 'block', fontSize: 12, fontWeight: 700, color: '#94A3B8', marginBottom: 6 }}>
                  New Agent Phone Number (New Referrer)
                </label>
                <input
                  type="text"
                  placeholder="e.g. 8888888888"
                  value={transferNewAgentPhone}
                  onChange={(e) => setTransferNewAgentPhone(e.target.value)}
                  style={{ width: '100%', background: '#0E1728', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F59E0B', fontWeight: 700 }}
                />
              </div>

              <button type="submit" className="btn-primary" style={{ padding: '12px 20px' }}>
                <UserCheck size={16} /> Execute Referrer Reassignment
              </button>
            </form>
          </div>
        )}

        {/* 7. SUPPORT TICKETS */}
        {section === 'support_tickets' && (
          <div className="glass-panel" style={{ overflow: 'hidden' }}>
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #1E2E4E', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <h3 style={{ fontSize: 18, fontWeight: 900 }}>Customer Support Helpdesk</h3>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 2 }}>
                  Respond to member queries regarding deposits, payouts, and commissions.
                </p>
              </div>
              <span style={{ fontSize: 12, fontWeight: 800, background: 'rgba(0, 240, 255, 0.1)', color: '#00F0FF', padding: '6px 12px', borderRadius: 8 }}>
                Open Tickets: {openTicketsCount}
              </span>
            </div>

            <table className="portal-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Member</th>
                  <th>Subject</th>
                  <th>Message</th>
                  <th>Status</th>
                  <th>Reply</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {tickets.map((t) => (
                  <tr key={t.id}>
                    <td>#{t.id}</td>
                    <td>
                      <div style={{ fontWeight: 700 }}>{t.user_name}</div>
                      <div style={{ fontSize: 11, color: '#64748B' }}>{t.user_phone}</div>
                    </td>
                    <td style={{ fontWeight: 800, color: '#00F0FF' }}>{t.subject}</td>
                    <td style={{ maxWidth: 280, fontSize: 12, color: '#94A3B8' }}>{t.message}</td>
                    <td>
                      <span
                        style={{
                          padding: '3px 8px',
                          borderRadius: 6,
                          fontSize: 11,
                          fontWeight: 800,
                          background: t.status === 'Open' ? 'rgba(245, 158, 11, 0.15)' : 'rgba(16, 185, 129, 0.15)',
                          color: t.status === 'Open' ? '#F59E0B' : '#10B981',
                        }}
                      >
                        {t.status}
                      </span>
                    </td>
                    <td style={{ maxWidth: 200, fontSize: 12, color: '#10B981' }}>{t.reply || '—'}</td>
                    <td>
                      <button
                        onClick={() => {
                          setReplyTicketId(t.id);
                          setReplyText(t.reply || '');
                        }}
                        className="btn-secondary"
                        style={{ fontSize: 11, padding: '4px 10px' }}
                      >
                        Reply
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* REPLY MODAL */}
            {replyTicketId && (
              <div style={{ padding: 20, background: '#0E1728', borderTop: '1px solid #1E2E4E' }}>
                <h4 style={{ fontSize: 14, fontWeight: 800, marginBottom: 8 }}>Reply to Ticket #{replyTicketId}</h4>
                <textarea
                  rows={3}
                  value={replyText}
                  onChange={(e) => setReplyText(e.target.value)}
                  placeholder="Type official admin response..."
                  style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 10, borderRadius: 8, color: '#F8FAFC', marginBottom: 10 }}
                />
                <div style={{ display: 'flex', gap: 8 }}>
                  <button onClick={() => handleReplyTicket(replyTicketId)} className="btn-primary" style={{ fontSize: 12 }}>
                    Send Response
                  </button>
                  <button onClick={() => setReplyTicketId(null)} className="btn-secondary" style={{ fontSize: 12 }}>
                    Cancel
                  </button>
                </div>
              </div>
            )}
          </div>
        )}

        {/* 8. PAYMENT PARTNERS */}
        {section === 'partners' && (
          <div className="glass-panel" style={{ padding: 24 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
              <div>
                <h3 style={{ fontSize: 18, fontWeight: 900 }}>UPI Payment Partners</h3>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 2 }}>
                  Configure active payment gateways (PhonePe, Paytm, Google Pay, etc.) for deposits & payouts.
                </p>
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 340px', gap: 24 }}>
              <div>
                <table className="portal-table">
                  <thead>
                    <tr>
                      <th>Partner Name</th>
                      <th>Type</th>
                      <th>Status</th>
                      <th>Toggle</th>
                    </tr>
                  </thead>
                  <tbody>
                    {partners.map((p) => (
                      <tr key={p.id}>
                        <td style={{ fontWeight: 800 }}>{p.name}</td>
                        <td>
                          <span style={{ padding: '3px 8px', borderRadius: 4, background: '#15223C', fontSize: 11, fontWeight: 700 }}>
                            {p.type}
                          </span>
                        </td>
                        <td>
                          <span style={{ color: p.active ? '#10B981' : '#EF4444', fontWeight: 800, fontSize: 12 }}>
                            {p.active ? '● Active' : '○ Disabled'}
                          </span>
                        </td>
                        <td>
                          <button
                            onClick={() => handleTogglePartner(p.id)}
                            className="btn-secondary"
                            style={{ fontSize: 11, padding: '4px 8px' }}
                          >
                            {p.active ? 'Disable' : 'Enable'}
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div style={{ background: '#0E1728', padding: 18, borderRadius: 12, border: '1px solid #1E2E4E', height: 'fit-content' }}>
                <h4 style={{ fontSize: 14, fontWeight: 800, marginBottom: 12 }}>Add New Partner</h4>
                <form onSubmit={handleAddPartner}>
                  <div style={{ marginBottom: 12 }}>
                    <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                      Partner Name
                    </label>
                    <input
                      type="text"
                      placeholder="e.g. Freecharge"
                      value={newPartnerName}
                      onChange={(e) => setNewPartnerName(e.target.value)}
                      style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 8, borderRadius: 6, color: '#F8FAFC' }}
                    />
                  </div>

                  <div style={{ marginBottom: 14 }}>
                    <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                      Type
                    </label>
                    <select
                      value={newPartnerType}
                      onChange={(e) => setNewPartnerType(e.target.value)}
                      style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 8, borderRadius: 6, color: '#F8FAFC' }}
                    >
                      <option value="Sell">Withdrawal Only (Sell)</option>
                      <option value="Buy">Deposit Only (Buy)</option>
                      <option value="Both">Both (Buy & Sell)</option>
                    </select>
                  </div>

                  <button type="submit" className="btn-primary" style={{ width: '100%', justifyContent: 'center' }}>
                    <Plus size={14} /> Add Payment Partner
                  </button>
                </form>
              </div>
            </div>
          </div>
        )}

        {/* 9. ANNOUNCEMENTS */}
        {section === 'announcements' && (
          <div className="glass-panel" style={{ padding: 24 }}>
            <h3 style={{ fontSize: 18, fontWeight: 900, marginBottom: 4 }}>Site Announcements & Popups</h3>
            <p style={{ fontSize: 12, color: '#94A3B8', marginBottom: 20 }}>
              Broadcast real-time announcements to the Nova Pay Android app and user dashboard.
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 360px', gap: 24 }}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {announcements.map((a) => (
                  <div key={a.id} style={{ background: '#0E1728', padding: 16, borderRadius: 12, border: '1px solid #1E2E4E', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                    <div>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
                        <span style={{ fontSize: 10, fontWeight: 900, background: 'rgba(0, 240, 255, 0.2)', color: '#00F0FF', padding: '2px 6px', borderRadius: 4 }}>
                          {a.badge}
                        </span>
                        <strong style={{ fontSize: 14 }}>{a.title}</strong>
                      </div>
                      <p style={{ fontSize: 12, color: '#94A3B8', lineHeight: '18px' }}>{a.body}</p>
                      <span style={{ fontSize: 10, color: '#64748B', marginTop: 6, display: 'block' }}>
                        {new Date(a.created_at).toLocaleDateString()}
                      </span>
                    </div>

                    <button onClick={() => handleDeleteAnnouncement(a.id)} style={{ background: 'none', border: 'none', color: '#EF4444', cursor: 'pointer' }}>
                      <Trash2 size={16} />
                    </button>
                  </div>
                ))}
              </div>

              <div style={{ background: '#0E1728', padding: 18, borderRadius: 12, border: '1px solid #1E2E4E', height: 'fit-content' }}>
                <h4 style={{ fontSize: 14, fontWeight: 800, marginBottom: 12 }}>Publish New Notice</h4>
                <form onSubmit={handleAddAnnouncement}>
                  <div style={{ marginBottom: 10 }}>
                    <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                      Badge Tag
                    </label>
                    <select
                      value={newAnnBadge}
                      onChange={(e) => setNewAnnBadge(e.target.value)}
                      style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 8, borderRadius: 6, color: '#F8FAFC' }}
                    >
                      <option value="HOT">HOT</option>
                      <option value="NEW">NEW</option>
                      <option value="NOTICE">NOTICE</option>
                      <option value="BONUS">BONUS</option>
                    </select>
                  </div>

                  <div style={{ marginBottom: 10 }}>
                    <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                      Announcement Title
                    </label>
                    <input
                      type="text"
                      placeholder="e.g. Weekend Deposit Bonus Active"
                      value={newAnnTitle}
                      onChange={(e) => setNewAnnTitle(e.target.value)}
                      style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 8, borderRadius: 6, color: '#F8FAFC' }}
                    />
                  </div>

                  <div style={{ marginBottom: 14 }}>
                    <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                      Message Content
                    </label>
                    <textarea
                      rows={3}
                      placeholder="Detail your broadcast notification..."
                      value={newAnnBody}
                      onChange={(e) => setNewAnnBody(e.target.value)}
                      style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 8, borderRadius: 6, color: '#F8FAFC' }}
                    />
                  </div>

                  <button type="submit" className="btn-primary" style={{ width: '100%', justifyContent: 'center' }}>
                    <Send size={14} /> Broadcast to App
                  </button>
                </form>
              </div>
            </div>
          </div>
        )}

        {/* 10. BANNERS & MEDIA */}
        {section === 'banners' && (
          <div className="glass-panel" style={{ padding: 24 }}>
            <h3 style={{ fontSize: 18, fontWeight: 900, marginBottom: 4 }}>Home Carousel Banners</h3>
            <p style={{ fontSize: 12, color: '#94A3B8', marginBottom: 20 }}>
              Control slider banners visible on the Nova Pay mobile home screen.
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 16 }}>
              {banners.map((b) => (
                <div key={b.id} style={{ background: '#0E1728', borderRadius: 12, border: '1px solid #1E2E4E', overflow: 'hidden' }}>
                  <img src={b.image_url} alt={b.title} style={{ width: '100%', height: 160, objectFit: 'cover' }} />
                  <div style={{ padding: 14 }}>
                    <strong style={{ fontSize: 14, color: '#00F0FF' }}>{b.title}</strong>
                    <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 4 }}>{b.subtitle}</p>
                    <div style={{ marginTop: 8, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      <span style={{ fontSize: 11, color: '#10B981', fontWeight: 700 }}>● Active in App</span>
                      <span style={{ fontSize: 11, color: '#64748B' }}>Link: {b.link_url}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* 11. TELEGRAM BOT SETTINGS */}
        {section === 'telegram' && (
          <div className="glass-panel" style={{ padding: 24, maxWidth: 640 }}>
            <h3 style={{ fontSize: 18, fontWeight: 900, marginBottom: 4 }}>Telegram Bot Automation</h3>
            <p style={{ fontSize: 12, color: '#94A3B8', marginBottom: 20 }}>
              Receive instant alerts on your private Telegram channel when users submit deposits or request payouts.
            </p>

            <div style={{ background: '#0E1728', padding: 18, borderRadius: 12, border: '1px solid #1E2E4E', marginBottom: 16 }}>
              <div style={{ marginBottom: 12 }}>
                <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                  Telegram Bot API Token
                </label>
                <input
                  type="text"
                  placeholder="e.g. 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                  value={telegramConfig.bot_token || ''}
                  onChange={(e) => setTelegramConfig({ ...telegramConfig, bot_token: e.target.value })}
                  style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 8, borderRadius: 6, color: '#F8FAFC', fontFamily: 'monospace' }}
                />
              </div>

              <div style={{ marginBottom: 14 }}>
                <label style={{ display: 'block', fontSize: 11, fontWeight: 700, color: '#94A3B8', marginBottom: 4 }}>
                  Admin Chat ID / Channel ID
                </label>
                <input
                  type="text"
                  placeholder="e.g. -1001234567890"
                  value={telegramConfig.admin_chat_id || ''}
                  onChange={(e) => setTelegramConfig({ ...telegramConfig, admin_chat_id: e.target.value })}
                  style={{ width: '100%', background: '#15223C', border: '1px solid #1E2E4E', padding: 8, borderRadius: 6, color: '#F8FAFC', fontFamily: 'monospace' }}
                />
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 10 }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: '#F8FAFC' }}>
                  <input
                    type="checkbox"
                    checked={telegramConfig.notify_deposit}
                    onChange={(e) => setTelegramConfig({ ...telegramConfig, notify_deposit: e.target.checked })}
                  />
                  Notify on New Deposit Order (UTR Submitted)
                </label>

                <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: '#F8FAFC' }}>
                  <input
                    type="checkbox"
                    checked={telegramConfig.notify_withdrawal}
                    onChange={(e) => setTelegramConfig({ ...telegramConfig, notify_withdrawal: e.target.checked })}
                  />
                  Notify on Withdrawal Request (Payout Queued)
                </label>
              </div>
            </div>

            <button
              onClick={() => {
                localStorage.setItem('np_portal_telegram', JSON.stringify(telegramConfig));
                showToast('Telegram Settings Saved!');
              }}
              className="btn-primary"
            >
              <Save size={16} /> Save Telegram Configuration
            </button>
          </div>
        )}

        {/* 12. SYSTEM SETTINGS */}
        {section === 'settings' && (
          <div className="glass-panel" style={{ padding: 24 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
              <div>
                <h3 style={{ fontSize: 18, fontWeight: 900 }}>Platform Financial & Exchange Rules</h3>
                <p style={{ fontSize: 12, color: '#94A3B8', marginTop: 4 }}>
                  Configure official receiving details, crypto rates, and affiliate reward percentages.
                </p>
              </div>
              <button
                onClick={() => {
                  PortalStorage.saveSettings(settings);
                  showToast('Financial settings updated successfully!');
                }}
                className="btn-primary"
              >
                <Save size={16} /> Save Changes
              </button>
            </div>

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

        {/* 13. DATABASE BACKUP */}
        {section === 'backup' && (
          <div className="glass-panel" style={{ padding: 28, maxWidth: 600 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
              <Database size={24} color="#00F0FF" />
              <h3 style={{ fontSize: 20, fontWeight: 900 }}>Database Backup & Export</h3>
            </div>
            <p style={{ fontSize: 13, color: '#94A3B8', lineHeight: '22px', marginBottom: 20 }}>
              Export a complete snapshot of all users, deposit orders, withdrawal logs, settings, and agent commission records in structured JSON format for safety and cold-storage archives.
            </p>

            <button onClick={handleExportBackup} className="btn-primary" style={{ padding: '12px 24px' }}>
              <Database size={16} /> Download Full JSON Database Backup
            </button>
          </div>
        )}
      </div>
    </div>
  );
};
