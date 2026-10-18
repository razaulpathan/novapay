# ⚡ NOVA PAY — Full-Stack Fintech Platform

Modern Cyber-Fintech Ecosystem converted from legacy PHP into high-performance **React Native (Android/iOS)** and **React 18 + Vite (Admin & Agent Web Portal)** with **Supabase Cloud Backend**.

---

## 📂 Project Architecture

```
novapay/
├── src/                    # 📱 React Native + Expo Mobile Application
├── assets/                 # 🎨 Cyber-Fintech Brand Icons, Splash & Banners
├── web-portal/             # 💻 Modern React 18 + Vite Admin & Agent Portal (Vercel Ready)
│   ├── src/
│   │   ├── pages/
│   │   │   ├── AdminDashboard.tsx   # 🔥 All 13 Admin Panel Modules
│   │   │   └── AgentPortal.tsx      # 👥 Agent Team & Downline Management
│   │   ├── components/
│   │   │   └── AdminNavbar.tsx      # Top Navigation & Supabase Connector
│   │   └── lib/
│   │       └── supabase.ts          # Supabase Client & Local Mock Sync
│   └── vercel.json                  # Vercel SPA Routing Configuration
├── supabase/               # 🗄️ PostgreSQL Migrations & RLS Policies
├── app.json                # Expo SDK 58 Configuration
└── eas.json                # EAS Android APK Cloud Build Profile
```

---

## 🛡️ Admin Panel Features (All 13 Modules from PHP)

The Admin Web Portal is located in **`web-portal/`**. All 13 modules from the legacy PHP admin have been completely rebuilt into high-performance, reactive React 18 components:

| # | Old PHP Page | New React Module | Description |
|---|---|---|---|
| **1** | `dashboard.php` | **Executive Dashboard** | Real-time analytics, daily turnover volume, pending UTRs, active users count. |
| **2** | `buy_orders.php` | **Deposit Orders** | Live UTR verification queue, 1-click Approve with instant balance credit, Reject with reason. |
| **3** | `sell_orders.php` | **Withdrawal Orders** | Payout queue with user UPI IDs, 1-click Mark Paid, or Reject with automatic token refund. |
| **4** | `users.php` | **Users & Agents Directory** | Search by phone/name, role switcher (`User` / `Agent` / `Admin`), manual balance adjuster (+/-), and Block/Unblock toggle. |
| **5** | `token_transfers.php` | **Token Transfers** | Full audit log of all internal wallet-to-wallet and downline transfers. |
| **6** | `transfer_user.php` | **Transfer Referrer** | Reassign any member/user under a different Agent/Sponsor using phone numbers. |
| **7** | `support_tickets.php`| **Support Helpdesk** | Live customer support tickets with inline reply submission. |
| **8** | `partners.php` | **Payment Partners** | Manage enabled deposit gateways (PhonePe, Paytm, Google Pay, IndusPay). |
| **9** | `announcements.php` | **News & Broadcasts** | Publish announcements with custom badges (`HOT`, `NEW`, `NOTICE`) and active toggles. |
| **10** | `banners.php` | **Banners & Media** | Manage promotional sliders and carousel images displayed in the mobile app. |
| **11** | `telegram.php` | **Telegram Bot Alerts** | Configure Telegram Bot API token & Chat ID for instant push notifications on new orders. |
| **12** | `settings.php` | **System Settings** | Configure USDT to INR rate, agent commission rates, daily interest %, admin UPI ID & bank details. |
| **13** | `backup.php` | **Database Backup** | 1-click full JSON database export of users, orders, settings, and logs. |

---

## 🚀 How to Run the Admin & Agent Web Portal

### 1. Run Locally on your computer
```bash
# Navigate into the web-portal directory
cd web-portal

# Install dependencies (if not done)
npm install

# Start the Vite local server
npm run dev
```
Open **`http://localhost:5173`** in your browser. You can toggle between **Admin Dashboard** and **Agent Portal** using the top navigation bar.

---

### 2. Deploy to Vercel (Free Hosting)
1. Go to [vercel.com](https://vercel.com) and click **"Add New Project"**.
2. Select your repository: **`razaulpathan/novapay`**.
3. In **Root Directory**, click **Edit** and choose:
   ```
   web-portal
   ```
   *(⚠️ Important: Do not leave as root `/`, select `web-portal`)*
4. Framework Preset: **Vite** (automatically detected).
5. Click **Deploy**. Vercel will build and provide a live `https://novapay-*.vercel.app` URL.

---

## 📱 Mobile App (Nova Pay Android APK)

- **Framework**: React Native + Expo SDK 58
- **EAS Cloud Build**: Profile configured in `eas.json` for standalone `.apk` distribution.
- **Run Locally**:
  ```bash
  npx expo start
  ```
