import AsyncStorage from '@react-native-async-storage/async-storage';
import { createClient, SupabaseClient } from '@supabase/supabase-js';
import { 
  User, 
  UpiAccount, 
  BuyOrder, 
  SellOrder, 
  TokenTransfer, 
  CommissionPayout, 
  Announcement, 
  PlatformSettings 
} from '../types';

const STORAGE_KEYS = {
  SUPABASE_URL: '@novapay_supabase_url',
  SUPABASE_KEY: '@novapay_supabase_key',
  CURRENT_USER: '@novapay_current_user',
  USERS: '@novapay_users',
  UPI_ACCOUNTS: '@novapay_upi_accounts',
  BUY_ORDERS: '@novapay_buy_orders',
  SELL_ORDERS: '@novapay_sell_orders',
  TRANSFERS: '@novapay_transfers',
  COMMISSIONS: '@novapay_commissions',
  SETTINGS: '@novapay_settings',
  ANNOUNCEMENTS: '@novapay_announcements',
};

// Default platform settings
export const DEFAULT_SETTINGS: PlatformSettings = {
  admin_upi_id: 'novapay.business@upi',
  admin_usdt_address: 'TY3q4UoE9sHh1eA7N2G1sW8yU7xP4mQ9zT',
  admin_bank_holder: 'NOVA PAY FINANCIAL SERVICES',
  admin_bank_account: '50200084920194',
  admin_bank_ifsc: 'HDFC0001234',
  exchange_rate_usdt: 92.50,
  default_reward_percentage: 2.5,
  commission_level1_percent: 1.5,
  commission_level2_percent: 0.5,
  newbie_bonus_amount: 50.00,
};

// Default pre-seeded users
export const DEFAULT_USERS: User[] = [
  {
    id: 1,
    phone: '9999999999',
    name: 'Super Administrator',
    password: 'admin',
    itoken_balance: 500000,
    today_profit: 3450,
    reward_rate: 3.5,
    auto_selling: true,
    order_min: 100,
    order_max: 100000,
    referred_by: null,
    referral_code: 'NOVAADMIN',
    role: 'admin',
    status: 'Active',
    google_2fa_enabled: true,
  },
  {
    id: 2,
    phone: '8888888888',
    name: 'Master Agent Rahul',
    password: 'agent',
    itoken_balance: 48500,
    today_profit: 1820,
    reward_rate: 3.0,
    auto_selling: true,
    order_min: 100,
    order_max: 50000,
    referred_by: 1,
    referral_code: 'AGENTVIP',
    role: 'agent',
    status: 'Active',
    google_2fa_enabled: false,
  },
  {
    id: 3,
    phone: '7777777777',
    name: 'Vikram Sharma',
    password: 'user',
    itoken_balance: 4250,
    today_profit: 106.25,
    reward_rate: 2.5,
    auto_selling: true,
    order_min: 100,
    order_max: 20000,
    referred_by: 2,
    referral_code: 'VIKRAM99',
    role: 'user',
    status: 'Active',
    google_2fa_enabled: false,
  },
];

let supabaseInstance: SupabaseClient | null = null;

export const initSupabase = async (url?: string, anonKey?: string): Promise<SupabaseClient | null> => {
  try {
    const supabaseUrl = url || (await AsyncStorage.getItem(STORAGE_KEYS.SUPABASE_URL));
    const supabaseKey = anonKey || (await AsyncStorage.getItem(STORAGE_KEYS.SUPABASE_KEY));

    if (supabaseUrl && supabaseKey) {
      supabaseInstance = createClient(supabaseUrl, supabaseKey);
      return supabaseInstance;
    }
  } catch {
    // Return null on failure and fallback to offline mock engine
  }
  return null;
};

export const getSupabase = (): SupabaseClient | null => supabaseInstance;

// Storage helper functions
export const DatabaseService = {
  async initDemoData() {
    const existingUsers = await AsyncStorage.getItem(STORAGE_KEYS.USERS);
    if (!existingUsers) {
      await AsyncStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(DEFAULT_USERS));
    }

    const existingSettings = await AsyncStorage.getItem(STORAGE_KEYS.SETTINGS);
    if (!existingSettings) {
      await AsyncStorage.setItem(STORAGE_KEYS.SETTINGS, JSON.stringify(DEFAULT_SETTINGS));
    }

    const existingUpi = await AsyncStorage.getItem(STORAGE_KEYS.UPI_ACCOUNTS);
    if (!existingUpi) {
      const demoUpi: UpiAccount[] = [
        {
          id: 1,
          user_id: 3,
          partner: 'PhonePe',
          name: 'Vikram Sharma',
          upi_no: 'vikramsharma@ybl',
          is_default: true,
          status: 'Active',
        },
        {
          id: 2,
          user_id: 3,
          partner: 'Google Pay',
          name: 'Vikram Sharma',
          upi_no: 'vikram.pay@okaxis',
          is_default: false,
          status: 'Active',
        },
      ];
      await AsyncStorage.setItem(STORAGE_KEYS.UPI_ACCOUNTS, JSON.stringify(demoUpi));
    }

    const existingOrders = await AsyncStorage.getItem(STORAGE_KEYS.BUY_ORDERS);
    if (!existingOrders) {
      const demoOrders: BuyOrder[] = [
        {
          id: 1,
          order_no: 'NP-20261018-01',
          user_id: 3,
          type: 'UPI_PLUS',
          price: 2000,
          reward: 50,
          itokens: 2050,
          status: 'Success',
          utr: '428819204918',
          created_at: new Date(Date.now() - 3600000 * 5).toISOString(),
        },
        {
          id: 2,
          order_no: 'NP-20261018-02',
          user_id: 3,
          type: 'UPI',
          price: 1000,
          reward: 25,
          itokens: 1025,
          status: 'Pending',
          utr: '428891048201',
          created_at: new Date(Date.now() - 1800000).toISOString(),
        },
      ];
      await AsyncStorage.setItem(STORAGE_KEYS.BUY_ORDERS, JSON.stringify(demoOrders));
    }

    const existingAnnouncements = await AsyncStorage.getItem(STORAGE_KEYS.ANNOUNCEMENTS);
    if (!existingAnnouncements) {
      const demoAnnouncements: Announcement[] = [
        {
          id: 1,
          title: '🔥 Nova Pay 2.0 Live Now',
          body: 'Instant UPI deposits are now live with 2.5% bonus on every recharge. Agent tier commissions updated.',
          badge: 'NEW',
          is_active: true,
          created_at: new Date().toISOString(),
        },
        {
          id: 2,
          title: '⚡ Lightning Fast Withdrawals',
          body: 'Sell orders are processed automatically 24/7. Make sure your PhonePe / GPay UPI handle is verified.',
          badge: 'NOTICE',
          is_active: true,
          created_at: new Date().toISOString(),
        },
      ];
      await AsyncStorage.setItem(STORAGE_KEYS.ANNOUNCEMENTS, JSON.stringify(demoAnnouncements));
    }
  },

  async getAllUsers(): Promise<User[]> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.USERS);
    return data ? JSON.parse(data) : DEFAULT_USERS;
  },

  async getUserByPhone(phone: string): Promise<User | null> {
    const users = await this.getAllUsers();
    return users.find((u) => u.phone === phone) || null;
  },

  async getUserById(id: number): Promise<User | null> {
    const users = await this.getAllUsers();
    return users.find((u) => u.id === id) || null;
  },

  async saveUser(user: User): Promise<User> {
    const users = await this.getAllUsers();
    const idx = users.findIndex((u) => u.id === user.id);
    if (idx >= 0) {
      users[idx] = user;
    } else {
      users.push(user);
    }
    await AsyncStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(users));
    return user;
  },

  async getUpiAccounts(userId: number): Promise<UpiAccount[]> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.UPI_ACCOUNTS);
    const accounts: UpiAccount[] = data ? JSON.parse(data) : [];
    return accounts.filter((a) => a.user_id === userId && a.status === 'Active');
  },

  async addUpiAccount(account: Omit<UpiAccount, 'id'>): Promise<UpiAccount> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.UPI_ACCOUNTS);
    const accounts: UpiAccount[] = data ? JSON.parse(data) : [];
    const newAcc: UpiAccount = { ...account, id: Date.now() };
    accounts.push(newAcc);
    await AsyncStorage.setItem(STORAGE_KEYS.UPI_ACCOUNTS, JSON.stringify(accounts));
    return newAcc;
  },

  async deleteUpiAccount(id: number): Promise<void> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.UPI_ACCOUNTS);
    const accounts: UpiAccount[] = data ? JSON.parse(data) : [];
    const filtered = accounts.filter((a) => a.id !== id);
    await AsyncStorage.setItem(STORAGE_KEYS.UPI_ACCOUNTS, JSON.stringify(filtered));
  },

  async getBuyOrders(userId?: number): Promise<BuyOrder[]> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.BUY_ORDERS);
    const orders: BuyOrder[] = data ? JSON.parse(data) : [];
    if (userId) {
      return orders.filter((o) => o.user_id === userId).sort((a, b) => b.id - a.id);
    }
    return orders.sort((a, b) => b.id - a.id);
  },

  async createBuyOrder(order: Omit<BuyOrder, 'id' | 'order_no' | 'created_at'>): Promise<BuyOrder> {
    const orders = await this.getBuyOrders();
    const newOrder: BuyOrder = {
      ...order,
      id: Date.now(),
      order_no: `NP-${Date.now().toString().slice(-8)}`,
      created_at: new Date().toISOString(),
    };
    orders.unshift(newOrder);
    await AsyncStorage.setItem(STORAGE_KEYS.BUY_ORDERS, JSON.stringify(orders));
    return newOrder;
  },

  async updateBuyOrderStatus(id: number, status: 'Success' | 'Cancelled', reason?: string): Promise<BuyOrder | null> {
    const orders = await this.getBuyOrders();
    const order = orders.find((o) => o.id === id);
    if (!order) return null;

    order.status = status;
    if (reason) order.cancel_reason = reason;

    if (status === 'Success') {
      const user = await this.getUserById(order.user_id);
      if (user) {
        user.itoken_balance += order.itokens;
        user.today_profit += order.reward;
        await this.saveUser(user);

        // Credit commission if referred
        if (user.referred_by) {
          const referrer = await this.getUserById(user.referred_by);
          if (referrer) {
            const commAmount = Number((order.price * 0.015).toFixed(2));
            referrer.itoken_balance += commAmount;
            referrer.today_profit += commAmount;
            await this.saveUser(referrer);

            // Record commission
            await this.recordCommission({
              earner_id: referrer.id,
              source_id: user.id,
              source_phone: user.phone,
              level: 1,
              amount: commAmount,
              order_no: order.order_no,
            });
          }
        }
      }
    }

    await AsyncStorage.setItem(STORAGE_KEYS.BUY_ORDERS, JSON.stringify(orders));
    return order;
  },

  async getSellOrders(userId?: number): Promise<SellOrder[]> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.SELL_ORDERS);
    const orders: SellOrder[] = data ? JSON.parse(data) : [];
    if (userId) {
      return orders.filter((o) => o.user_id === userId).sort((a, b) => b.id - a.id);
    }
    return orders.sort((a, b) => b.id - a.id);
  },

  async createSellOrder(userId: number, amount: number, upiNo: string, partner: string): Promise<SellOrder> {
    const user = await this.getUserById(userId);
    if (!user || user.itoken_balance < amount) {
      throw new Error('Insufficient iToken balance');
    }

    user.itoken_balance -= amount;
    await this.saveUser(user);

    const orders = await this.getSellOrders();
    const newOrder: SellOrder = {
      id: Date.now(),
      user_id: userId,
      amount,
      upi_no: upiNo,
      partner,
      status: 'Paying',
      created_at: new Date().toISOString(),
    };
    orders.unshift(newOrder);
    await AsyncStorage.setItem(STORAGE_KEYS.SELL_ORDERS, JSON.stringify(orders));
    return newOrder;
  },

  async updateSellOrderStatus(id: number, status: 'Success' | 'Failed'): Promise<SellOrder | null> {
    const orders = await this.getSellOrders();
    const order = orders.find((o) => o.id === id);
    if (!order) return null;

    order.status = status;
    if (status === 'Failed') {
      const user = await this.getUserById(order.user_id);
      if (user) {
        user.itoken_balance += order.amount; // refund balance
        await this.saveUser(user);
      }
    }

    await AsyncStorage.setItem(STORAGE_KEYS.SELL_ORDERS, JSON.stringify(orders));
    return order;
  },

  async getTransfers(agentId: number): Promise<TokenTransfer[]> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.TRANSFERS);
    const transfers: TokenTransfer[] = data ? JSON.parse(data) : [];
    return transfers.filter((t) => t.agent_id === agentId).sort((a, b) => b.id - a.id);
  },

  async transferTokens(agentId: number, targetPhone: string, amount: number, note?: string): Promise<TokenTransfer> {
    const agent = await this.getUserById(agentId);
    if (!agent || agent.itoken_balance < amount) {
      throw new Error('Insufficient balance to transfer');
    }

    const targetUser = await this.getUserByPhone(targetPhone);
    if (!targetUser) {
      throw new Error('Recipient phone number not found');
    }

    agent.itoken_balance -= amount;
    targetUser.itoken_balance += amount;

    await this.saveUser(agent);
    await this.saveUser(targetUser);

    const data = await AsyncStorage.getItem(STORAGE_KEYS.TRANSFERS);
    const transfers: TokenTransfer[] = data ? JSON.parse(data) : [];
    const newTransfer: TokenTransfer = {
      id: Date.now(),
      agent_id: agentId,
      target_phone: targetPhone,
      target_uid: targetUser.id,
      amount,
      status: 'Success',
      note: note || 'Agent token topup',
      created_at: new Date().toISOString(),
    };
    transfers.unshift(newTransfer);
    await AsyncStorage.setItem(STORAGE_KEYS.TRANSFERS, JSON.stringify(transfers));
    return newTransfer;
  },

  async recordCommission(comm: Omit<CommissionPayout, 'id' | 'created_at'>) {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.COMMISSIONS);
    const commissions: CommissionPayout[] = data ? JSON.parse(data) : [];
    commissions.unshift({
      ...comm,
      id: Date.now(),
      created_at: new Date().toISOString(),
    });
    await AsyncStorage.setItem(STORAGE_KEYS.COMMISSIONS, JSON.stringify(commissions));
  },

  async getCommissions(earnerId: number): Promise<CommissionPayout[]> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.COMMISSIONS);
    const commissions: CommissionPayout[] = data ? JSON.parse(data) : [];
    return commissions.filter((c) => c.earner_id === earnerId).sort((a, b) => b.id - a.id);
  },

  async getAnnouncements(): Promise<Announcement[]> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.ANNOUNCEMENTS);
    return data ? JSON.parse(data) : [];
  },

  async getSettings(): Promise<PlatformSettings> {
    const data = await AsyncStorage.getItem(STORAGE_KEYS.SETTINGS);
    return data ? JSON.parse(data) : DEFAULT_SETTINGS;
  },

  async saveSettings(settings: PlatformSettings): Promise<void> {
    await AsyncStorage.setItem(STORAGE_KEYS.SETTINGS, JSON.stringify(settings));
  },
};
