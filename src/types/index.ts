export type UserRole = 'user' | 'agent' | 'sub_admin' | 'admin';

export interface User {
  id: number;
  phone: string;
  name: string;
  password?: string;
  itoken_balance: number;
  today_profit: number;
  reward_rate: number;
  auto_selling: boolean;
  order_min: number;
  order_max: number;
  referred_by: number | null;
  referral_code: string;
  role: UserRole;
  status: 'Active' | 'Blocked' | 'Suspended';
  google_2fa_enabled: boolean;
  created_at?: string;
}

export interface UpiAccount {
  id: number;
  user_id: number;
  partner: string;
  name: string;
  upi_no: string;
  is_default: boolean;
  status: 'Active' | 'UnLink';
  created_at?: string;
}

export interface BuyOrder {
  id: number;
  order_no: string;
  user_id: number;
  type: 'UPI_PLUS' | 'UPI' | 'USDT';
  price: number;
  reward: number;
  itokens: number;
  status: 'Pending' | 'Success' | 'Cancelled';
  utr?: string;
  cancel_reason?: string;
  created_at: string;
}

export interface SellOrder {
  id: number;
  user_id: number;
  amount: number;
  upi_no: string;
  partner: string;
  status: 'Paying' | 'Success' | 'Failed';
  created_at: string;
}

export interface TokenTransfer {
  id: number;
  agent_id: number;
  target_phone: string;
  target_uid?: number;
  amount: number;
  status: 'Pending' | 'Success' | 'Cancelled';
  note?: string;
  created_at: string;
}

export interface CommissionPayout {
  id: number;
  earner_id: number;
  source_id: number;
  source_phone?: string;
  level: number;
  amount: number;
  order_no: string;
  created_at: string;
}

export interface Announcement {
  id: number;
  title: string;
  body: string;
  badge?: string;
  is_active: boolean;
  created_at: string;
}

export interface SupportTicket {
  id: number;
  user_id: number;
  subject: string;
  message: string;
  status: 'Open' | 'Replied' | 'Closed';
  reply?: string;
  created_at: string;
}

export interface PlatformSettings {
  admin_upi_id: string;
  admin_usdt_address: string;
  admin_bank_holder: string;
  admin_bank_account: string;
  admin_bank_ifsc: string;
  exchange_rate_usdt: number;
  default_reward_percentage: number;
  commission_level1_percent: number;
  commission_level2_percent: number;
  newbie_bonus_amount: number;
}
