import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Image,
  RefreshControl,
  Alert,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';
import { DatabaseService } from '../services/supabase';
import { BuyOrder, SellOrder } from '../types';

interface HomeScreenProps {
  onNavigate: (tab: 'home' | 'buy' | 'sell' | 'agent' | 'more') => void;
  onOpenAccounts: () => void;
}

export const HomeScreen: React.FC<HomeScreenProps> = ({ onNavigate, onOpenAccounts }) => {
  const { currentUser, refreshUser } = useAuth();
  const [refreshing, setRefreshing] = useState(false);
  const [recentOrders, setRecentOrders] = useState<BuyOrder[]>([]);
  const [recentPayouts, setRecentPayouts] = useState<SellOrder[]>([]);
  const [dailyClaimed, setDailyClaimed] = useState(false);

  useEffect(() => {
    loadData();
  }, [currentUser]);

  const loadData = async () => {
    if (!currentUser) return;
    try {
      const orders = await DatabaseService.getBuyOrders(currentUser.id);
      setRecentOrders(orders.slice(0, 3));
      const payouts = await DatabaseService.getSellOrders(currentUser.id);
      setRecentPayouts(payouts.slice(0, 3));
    } catch (e) {
      console.error(e);
    }
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    await refreshUser();
    await loadData();
    setRefreshing(false);
  };

  const claimDailyReward = async () => {
    if (!currentUser) return;
    if (dailyClaimed) {
      Alert.alert('Already Claimed', "You have already collected today's check-in bonus!");
      return;
    }
    const bonus = 10.0;
    currentUser.itoken_balance += bonus;
    currentUser.today_profit += bonus;
    await DatabaseService.saveUser(currentUser);
    await refreshUser();
    setDailyClaimed(true);
    Alert.alert('🎉 Reward Claimed!', `+₹${bonus.toFixed(2)} iTokens credited to your wallet!`);
  };

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={handleRefresh} tintColor={Colors.primary} />}
      showsVerticalScrollIndicator={false}
    >
      {/* WALLET BALANCE HERO CARD */}
      <LinearGradient
        colors={['#172544', '#0D1527']}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
        style={styles.heroCard}
      >
        <View style={styles.heroHeader}>
          <View>
            <Text style={styles.walletLabel}>TOTAL WALLET BALANCE</Text>
            <View style={styles.balanceRow}>
              <Text style={styles.currencySymbol}>₹</Text>
              <Text style={styles.balanceText}>
                {currentUser ? Number(currentUser.itoken_balance).toLocaleString('en-IN', { minimumFractionDigits: 2 }) : '0.00'}
              </Text>
            </View>
          </View>
          <View style={styles.itokenPill}>
            <Ionicons name="flash" size={14} color={Colors.primary} />
            <Text style={styles.itokenPillText}>iTokens</Text>
          </View>
        </View>

        <View style={styles.divider} />

        {/* METRICS ROW */}
        <View style={styles.metricsRow}>
          <View style={styles.metricItem}>
            <Text style={styles.metricLabel}>Today's Profit</Text>
            <Text style={[styles.metricValue, { color: Colors.accent }]}>
              +₹{currentUser ? Number(currentUser.today_profit).toFixed(2) : '0.00'}
            </Text>
          </View>

          <View style={styles.metricDivider} />

          <View style={styles.metricItem}>
            <Text style={styles.metricLabel}>Reward Rate</Text>
            <View style={styles.badgeRate}>
              <Ionicons name="trending-up" size={12} color={Colors.gold} />
              <Text style={styles.rateText}>{currentUser?.reward_rate || 2.5}% / Day</Text>
            </View>
          </View>

          <View style={styles.metricDivider} />

          <View style={styles.metricItem}>
            <Text style={styles.metricLabel}>Auto-Sell</Text>
            <View style={styles.statusActiveRow}>
              <View style={styles.activeDot} />
              <Text style={styles.statusActiveText}>Active</Text>
            </View>
          </View>
        </View>
      </LinearGradient>

      {/* QUICK ACTIONS ROW */}
      <View style={styles.actionGrid}>
        <TouchableOpacity
          style={styles.actionCard}
          onPress={() => onNavigate('buy')}
          activeOpacity={0.8}
        >
          <LinearGradient
            colors={['rgba(0, 240, 255, 0.2)', 'rgba(0, 240, 255, 0.05)']}
            style={styles.actionIconBox}
          >
            <Ionicons name="arrow-down-circle" size={24} color={Colors.primary} />
          </LinearGradient>
          <Text style={styles.actionTitle}>Deposit</Text>
          <Text style={styles.actionSubtitle}>UPI + Bonus</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.actionCard}
          onPress={() => onNavigate('sell')}
          activeOpacity={0.8}
        >
          <LinearGradient
            colors={['rgba(16, 185, 129, 0.2)', 'rgba(16, 185, 129, 0.05)']}
            style={styles.actionIconBox}
          >
            <Ionicons name="arrow-up-circle" size={24} color={Colors.accent} />
          </LinearGradient>
          <Text style={styles.actionTitle}>Withdraw</Text>
          <Text style={styles.actionSubtitle}>Instant Payout</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.actionCard}
          onPress={() => onNavigate('agent')}
          activeOpacity={0.8}
        >
          <LinearGradient
            colors={['rgba(245, 158, 11, 0.2)', 'rgba(245, 158, 11, 0.05)']}
            style={styles.actionIconBox}
          >
            <Ionicons name="people" size={24} color={Colors.gold} />
          </LinearGradient>
          <Text style={styles.actionTitle}>Commission</Text>
          <Text style={styles.actionSubtitle}>Refer & Earn</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.actionCard}
          onPress={onOpenAccounts}
          activeOpacity={0.8}
        >
          <LinearGradient
            colors={['rgba(139, 92, 246, 0.2)', 'rgba(139, 92, 246, 0.05)']}
            style={styles.actionIconBox}
          >
            <Ionicons name="card" size={24} color={Colors.secondary} />
          </LinearGradient>
          <Text style={styles.actionTitle}>UPI ID</Text>
          <Text style={styles.actionSubtitle}>Manage</Text>
        </TouchableOpacity>
      </View>

      {/* PROMO BANNER CAROUSEL */}
      <View style={styles.bannerContainer}>
        <Image
          source={require('../../assets/banner.png')}
          style={styles.bannerImage}
          resizeMode="cover"
        />
        <LinearGradient
          colors={['transparent', 'rgba(7, 11, 20, 0.85)']}
          style={styles.bannerOverlay}
        >
          <View style={styles.bannerTag}>
            <Text style={styles.bannerTagText}>EXCLUSIVE OFFER</Text>
          </View>
          <Text style={styles.bannerTitle}>Earn Up to 25% Agent Commission</Text>
          <Text style={styles.bannerSubtitle}>Invite downline members & earn automated referral rewards instantly.</Text>
        </LinearGradient>
      </View>

      {/* DAILY CHECK-IN REWARD CARD */}
      <TouchableOpacity
        style={styles.rewardCard}
        onPress={claimDailyReward}
        activeOpacity={0.85}
      >
        <LinearGradient
          colors={['#1F2942', '#141E33']}
          style={styles.rewardGradient}
        >
          <View style={styles.rewardLeft}>
            <View style={styles.giftIconBox}>
              <Ionicons name="gift" size={24} color={Colors.gold} />
            </View>
            <View>
              <Text style={styles.rewardTitle}>Daily Check-in Bonus</Text>
              <Text style={styles.rewardSubtitle}>
                {dailyClaimed ? 'Collected for today' : 'Tap to claim your free ₹10.00 daily reward'}
              </Text>
            </View>
          </View>
          <View style={[styles.claimButton, dailyClaimed && styles.claimedButton]}>
            <Text style={styles.claimButtonText}>{dailyClaimed ? 'CLAIMED' : 'CLAIM'}</Text>
          </View>
        </LinearGradient>
      </TouchableOpacity>

      {/* RECENT ACTIVITY SECTION */}
      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Recent Activity</Text>
        <TouchableOpacity onPress={() => onNavigate('more')}>
          <Text style={styles.seeAllText}>View All</Text>
        </TouchableOpacity>
      </View>

      {recentOrders.length === 0 && recentPayouts.length === 0 ? (
        <View style={styles.emptyState}>
          <Ionicons name="receipt-outline" size={32} color={Colors.textDim} />
          <Text style={styles.emptyStateText}>No recent transactions yet.</Text>
        </View>
      ) : (
        <View style={styles.transactionsList}>
          {recentOrders.map((order) => (
            <View key={`buy-${order.id}`} style={styles.transactionCard}>
              <View style={styles.txLeft}>
                <View style={[styles.txIconBox, { backgroundColor: 'rgba(0, 240, 255, 0.15)' }]}>
                  <Ionicons name="arrow-down" size={18} color={Colors.primary} />
                </View>
                <View>
                  <Text style={styles.txTitle}>Deposit ({order.type})</Text>
                  <Text style={styles.txDate}>{order.order_no} • {new Date(order.created_at).toLocaleDateString()}</Text>
                </View>
              </View>

              <View style={styles.txRight}>
                <Text style={styles.txAmountPositive}>+₹{order.price.toFixed(2)}</Text>
                <View
                  style={[
                    styles.statusBadge,
                    order.status === 'Success'
                      ? styles.statusSuccess
                      : order.status === 'Pending'
                      ? styles.statusPending
                      : styles.statusFailed,
                  ]}
                >
                  <Text style={styles.statusBadgeText}>{order.status}</Text>
                </View>
              </View>
            </View>
          ))}

          {recentPayouts.map((payout) => (
            <View key={`sell-${payout.id}`} style={styles.transactionCard}>
              <View style={styles.txLeft}>
                <View style={[styles.txIconBox, { backgroundColor: 'rgba(16, 185, 129, 0.15)' }]}>
                  <Ionicons name="arrow-up" size={18} color={Colors.accent} />
                </View>
                <View>
                  <Text style={styles.txTitle}>Withdrawal ({payout.partner})</Text>
                  <Text style={styles.txDate}>{payout.upi_no}</Text>
                </View>
              </View>

              <View style={styles.txRight}>
                <Text style={styles.txAmountNegative}>-₹{payout.amount.toFixed(2)}</Text>
                <View
                  style={[
                    styles.statusBadge,
                    payout.status === 'Success'
                      ? styles.statusSuccess
                      : styles.statusPending,
                  ]}
                >
                  <Text style={styles.statusBadgeText}>{payout.status}</Text>
                </View>
              </View>
            </View>
          ))}
        </View>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  content: {
    padding: 16,
    paddingBottom: 32,
  },
  heroCard: {
    borderRadius: 20,
    padding: 20,
    borderWidth: 1,
    borderColor: 'rgba(0, 240, 255, 0.3)',
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 10,
    marginBottom: 20,
  },
  heroHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  walletLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.textMuted,
    letterSpacing: 1,
    marginBottom: 4,
  },
  balanceRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
  },
  currencySymbol: {
    fontSize: 24,
    fontWeight: '800',
    color: Colors.primary,
    marginRight: 4,
  },
  balanceText: {
    fontSize: 32,
    fontWeight: '900',
    color: Colors.text,
    letterSpacing: 0.5,
  },
  itokenPill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(0, 240, 255, 0.15)',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: Colors.primary,
  },
  itokenPillText: {
    color: Colors.primary,
    fontSize: 12,
    fontWeight: '800',
    marginLeft: 4,
  },
  divider: {
    height: 1,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
    marginVertical: 16,
  },
  metricsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  metricItem: {
    flex: 1,
    alignItems: 'center',
  },
  metricLabel: {
    fontSize: 11,
    color: Colors.textDim,
    marginBottom: 4,
    fontWeight: '500',
  },
  metricValue: {
    fontSize: 14,
    fontWeight: '800',
  },
  badgeRate: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(245, 158, 11, 0.15)',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 6,
  },
  rateText: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.gold,
    marginLeft: 3,
  },
  metricDivider: {
    width: 1,
    height: 24,
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
  },
  statusActiveRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  activeDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: Colors.accent,
    marginRight: 4,
  },
  statusActiveText: {
    color: Colors.accent,
    fontSize: 12,
    fontWeight: '700',
  },
  actionGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 20,
  },
  actionCard: {
    flex: 1,
    backgroundColor: Colors.surface,
    paddingVertical: 14,
    paddingHorizontal: 8,
    borderRadius: 14,
    alignItems: 'center',
    marginHorizontal: 3,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  actionIconBox: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  actionTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.text,
  },
  actionSubtitle: {
    fontSize: 9,
    color: Colors.textDim,
    marginTop: 2,
  },
  bannerContainer: {
    borderRadius: 18,
    overflow: 'hidden',
    height: 140,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    position: 'relative',
  },
  bannerImage: {
    width: '100%',
    height: '100%',
  },
  bannerOverlay: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    padding: 14,
  },
  bannerTag: {
    backgroundColor: Colors.primary,
    alignSelf: 'flex-start',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    marginBottom: 4,
  },
  bannerTagText: {
    color: '#070B14',
    fontSize: 9,
    fontWeight: '900',
  },
  bannerTitle: {
    color: Colors.text,
    fontSize: 14,
    fontWeight: '800',
  },
  bannerSubtitle: {
    color: Colors.textMuted,
    fontSize: 10,
    marginTop: 2,
  },
  rewardCard: {
    marginBottom: 20,
    borderRadius: 16,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: 'rgba(245, 158, 11, 0.3)',
  },
  rewardGradient: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 14,
  },
  rewardLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  giftIconBox: {
    width: 40,
    height: 40,
    borderRadius: 10,
    backgroundColor: 'rgba(245, 158, 11, 0.15)',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  rewardTitle: {
    color: Colors.text,
    fontSize: 13,
    fontWeight: '800',
  },
  rewardSubtitle: {
    color: Colors.textMuted,
    fontSize: 10,
    marginTop: 2,
  },
  claimButton: {
    backgroundColor: Colors.gold,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
  },
  claimedButton: {
    backgroundColor: Colors.surfaceBorder,
  },
  claimButtonText: {
    color: '#070B14',
    fontSize: 11,
    fontWeight: '900',
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: 0.5,
  },
  seeAllText: {
    fontSize: 12,
    color: Colors.primary,
    fontWeight: '600',
  },
  emptyState: {
    backgroundColor: Colors.surface,
    padding: 24,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  emptyStateText: {
    color: Colors.textDim,
    fontSize: 12,
    marginTop: 8,
  },
  transactionsList: {
    gap: 8,
  },
  transactionCard: {
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  txLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  txIconBox: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  txTitle: {
    color: Colors.text,
    fontSize: 13,
    fontWeight: '700',
  },
  txDate: {
    color: Colors.textDim,
    fontSize: 10,
    marginTop: 2,
  },
  txRight: {
    alignItems: 'flex-end',
  },
  txAmountPositive: {
    color: Colors.accent,
    fontSize: 13,
    fontWeight: '800',
  },
  txAmountNegative: {
    color: Colors.ruby,
    fontSize: 13,
    fontWeight: '800',
  },
  statusBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    marginTop: 4,
  },
  statusSuccess: {
    backgroundColor: 'rgba(16, 185, 129, 0.15)',
  },
  statusPending: {
    backgroundColor: 'rgba(245, 158, 11, 0.15)',
  },
  statusFailed: {
    backgroundColor: 'rgba(239, 68, 68, 0.15)',
  },
  statusBadgeText: {
    fontSize: 9,
    fontWeight: '700',
    color: Colors.text,
  },
});
