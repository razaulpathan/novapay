import { createClient } from '@supabase/supabase-js';

// Pre-seeded demo dataset
const DEMO_USERS = [
  {
    id: 1,
    phone: '9999999999',
    name: 'Super Administrator',
    role: 'admin',
    itoken_balance: 500000.0,
    today_profit: 3450.0,
    referral_code: 'NOVAADMIN',
    status: 'Active',
    created_at: '2026-10-18T12:00:00Z',
  },
  {
    id: 2,
    phone: '8888888888',
    name: 'Master Agent Rahul',
    role: 'agent',
    itoken_balance: 48500.0,
    today_profit: 1820.0,
    referral_code: 'AGENTVIP',
    status: 'Active',
    referred_by: 1,
    created_at: '2026-10-18T13:30:00Z',
  },
  {
    id: 3,
    phone: '7777777777',
    name: 'Vikram Sharma',
    role: 'user',
    itoken_balance: 4250.0,
    today_profit: 106.25,
    referral_code: 'VIKRAM99',
    status: 'Active',
    referred_by: 2,
    created_at: '2026-10-18T15:10:00Z',
  },
  {
    id: 4,
    phone: '9123456780',
    name: 'Amit Patel',
    role: 'user',
    itoken_balance: 15200.0,
    today_profit: 380.0,
    referral_code: 'AMIT88',
    status: 'Active',
    referred_by: 2,
    created_at: '2026-10-18T16:00:00Z',
  },
];

const DEMO_BUY_ORDERS = [
  {
    id: 1,
    order_no: 'NP-20261018-01',
    user_id: 3,
    user_name: 'Vikram Sharma',
    user_phone: '7777777777',
    type: 'UPI_PLUS',
    price: 2000.0,
    reward: 50.0,
    itokens: 2050.0,
    status: 'Success',
    utr: '428819204918',
    created_at: '2026-10-18T16:20:00Z',
  },
  {
    id: 2,
    order_no: 'NP-20261018-02',
    user_id: 3,
    user_name: 'Vikram Sharma',
    user_phone: '7777777777',
    type: 'UPI',
    price: 5000.0,
    reward: 125.0,
    itokens: 5125.0,
    status: 'Pending',
    utr: '428891048201',
    created_at: '2026-10-18T17:05:00Z',
  },
  {
    id: 3,
    order_no: 'NP-20261018-03',
    user_id: 4,
    user_name: 'Amit Patel',
    user_phone: '9123456780',
    type: 'USDT',
    price: 10000.0,
    reward: 250.0,
    itokens: 10250.0,
    status: 'Pending',
    utr: 'TXID-99214a1024f923b',
    created_at: '2026-10-18T17:15:00Z',
  },
];

const DEMO_SELL_ORDERS = [
  {
    id: 1,
    user_id: 3,
    user_name: 'Vikram Sharma',
    user_phone: '7777777777',
    amount: 1000.0,
    upi_no: 'vikramsharma@ybl',
    partner: 'PhonePe',
    status: 'Success',
    created_at: '2026-10-18T14:00:00Z',
  },
  {
    id: 2,
    user_id: 4,
    user_name: 'Amit Patel',
    user_phone: '9123456780',
    amount: 3500.0,
    upi_no: 'amitpatel@okaxis',
    partner: 'Google Pay',
    status: 'Paying',
    created_at: '2026-10-18T17:10:00Z',
  },
];

const DEMO_SETTINGS = {
  admin_upi_id: 'novapay.business@upi',
  admin_usdt_address: 'TY3q4UoE9sHh1eA7N2G1sW8yU7xP4mQ9zT',
  admin_bank_holder: 'NOVA PAY FINANCIAL SERVICES',
  admin_bank_account: '50200084920194',
  admin_bank_ifsc: 'HDFC0001234',
  exchange_rate_usdt: 92.5,
  default_reward_percentage: 2.5,
  commission_level1_percent: 1.5,
  commission_level2_percent: 0.5,
};

export const PortalStorage = {
  getUsers() {
    const raw = localStorage.getItem('np_portal_users');
    if (!raw) {
      localStorage.setItem('np_portal_users', JSON.stringify(DEMO_USERS));
      return DEMO_USERS;
    }
    return JSON.parse(raw);
  },
  saveUsers(users: any[]) {
    localStorage.setItem('np_portal_users', JSON.stringify(users));
  },

  getBuyOrders() {
    const raw = localStorage.getItem('np_portal_buy_orders');
    if (!raw) {
      localStorage.setItem('np_portal_buy_orders', JSON.stringify(DEMO_BUY_ORDERS));
      return DEMO_BUY_ORDERS;
    }
    return JSON.parse(raw);
  },
  saveBuyOrders(orders: any[]) {
    localStorage.setItem('np_portal_buy_orders', JSON.stringify(orders));
  },

  getSellOrders() {
    const raw = localStorage.getItem('np_portal_sell_orders');
    if (!raw) {
      localStorage.setItem('np_portal_sell_orders', JSON.stringify(DEMO_SELL_ORDERS));
      return DEMO_SELL_ORDERS;
    }
    return JSON.parse(raw);
  },
  saveSellOrders(orders: any[]) {
    localStorage.setItem('np_portal_sell_orders', JSON.stringify(orders));
  },

  getSettings() {
    const raw = localStorage.getItem('np_portal_settings');
    if (!raw) {
      localStorage.setItem('np_portal_settings', JSON.stringify(DEMO_SETTINGS));
      return DEMO_SETTINGS;
    }
    return JSON.parse(raw);
  },
  saveSettings(settings: any) {
    localStorage.setItem('np_portal_settings', JSON.stringify(settings));
  },
};
