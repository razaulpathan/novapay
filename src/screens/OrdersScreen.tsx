import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';
import { DatabaseService } from '../services/supabase';
import { BuyOrder, SellOrder, TokenTransfer, CommissionPayout } from '../types';

export const OrdersScreen: React.FC = () => {
  const { currentUser } = useAuth();
  const [filter, setFilter] = useState<'all' | 'deposits' | 'withdrawals' | 'transfers' | 'commissions'>('all');
  const [deposits, setDeposits] = useState<BuyOrder[]>([]);
  const [withdrawals, setWithdrawals] = useState<SellOrder[]>([]);
  const [transfers, setTransfers] = useState<TokenTransfer[]>([]);
  const [commissions, setCommissions] = useState<CommissionPayout[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => {
    loadAllRecords();
  }, [currentUser]);

  const loadAllRecords = async () => {
    if (!currentUser) return;
    try {
      const dep = await DatabaseService.getBuyOrders(currentUser.id);
      setDeposits(dep);
      const withdr = await DatabaseService.getSellOrders(currentUser.id);
      setWithdrawals(withdr);
      const tx = await DatabaseService.getTransfers(currentUser.id);
      setTransfers(tx);
      const comm = await DatabaseService.getCommissions(currentUser.id);
      setCommissions(comm);
    } catch (e) {
      console.error(e);
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await loadAllRecords();
    setRefreshing(false);
  };

  return (
    <View style={styles.container}>
      {/* FILTER TABS */}
      <View style={styles.filterBar}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filterScroll}>
          {[
            { key: 'all', label: 'All Records' },
            { key: 'deposits', label: 'Deposits' },
            { key: 'withdrawals', label: 'Withdrawals' },
            { key: 'transfers', label: 'Transfers' },
            { key: 'commissions', label: 'Commissions' },
          ].map((tab) => (
            <TouchableOpacity
              key={tab.key}
              style={[styles.filterChip, filter === tab.key && styles.filterChipActive]}
              onPress={() => setFilter(tab.key as any)}
            >
              <Text style={[styles.filterText, filter === tab.key && styles.filterTextActive]}>
                {tab.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      <ScrollView
        style={styles.listContainer}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={Colors.primary} />}
        showsVerticalScrollIndicator={false}
      >
        {/* DEPOSITS LIST */}
        {(filter === 'all' || filter === 'deposits') &&
          deposits.map((item) => (
            <View key={`dep-${item.id}`} style={styles.itemCard}>
              <View style={styles.itemTop}>
                <View style={styles.typeRow}>
                  <View style={[styles.iconBox, { backgroundColor: 'rgba(0, 240, 255, 0.15)' }]}>
                    <Ionicons name="arrow-down" size={16} color={Colors.primary} />
                  </View>
                  <View>
                    <Text style={styles.itemTitle}>Deposit ({item.type})</Text>
                    <Text style={styles.itemSubtitle}>Order: {item.order_no}</Text>
                  </View>
                </View>
                <Text style={styles.amountPositive}>+₹{item.price.toFixed(2)}</Text>
              </View>

              <View style={styles.itemBottom}>
                <Text style={styles.itemMeta}>UTR: {item.utr || 'N/A'}</Text>
                <View
                  style={[
                    styles.statusPill,
                    item.status === 'Success'
                      ? styles.pillSuccess
                      : item.status === 'Pending'
                      ? styles.pillPending
                      : styles.pillFailed,
                  ]}
                >
                  <Text style={styles.pillText}>{item.status}</Text>
                </View>
              </View>
              <Text style={styles.timestamp}>{new Date(item.created_at).toLocaleString()}</Text>
            </View>
          ))}

        {/* WITHDRAWALS LIST */}
        {(filter === 'all' || filter === 'withdrawals') &&
          withdrawals.map((item) => (
            <View key={`withdr-${item.id}`} style={styles.itemCard}>
              <View style={styles.itemTop}>
                <View style={styles.typeRow}>
                  <View style={[styles.iconBox, { backgroundColor: 'rgba(16, 185, 129, 0.15)' }]}>
                    <Ionicons name="arrow-up" size={16} color={Colors.accent} />
                  </View>
                  <View>
                    <Text style={styles.itemTitle}>Withdrawal ({item.partner})</Text>
                    <Text style={styles.itemSubtitle}>UPI: {item.upi_no}</Text>
                  </View>
                </View>
                <Text style={styles.amountNegative}>-₹{item.amount.toFixed(2)}</Text>
              </View>

              <View style={styles.itemBottom}>
                <Text style={styles.itemMeta}>Direct Settlement</Text>
                <View
                  style={[
                    styles.statusPill,
                    item.status === 'Success'
                      ? styles.pillSuccess
                      : item.status === 'Paying'
                      ? styles.pillPending
                      : styles.pillFailed,
                  ]}
                >
                  <Text style={styles.pillText}>{item.status}</Text>
                </View>
              </View>
              <Text style={styles.timestamp}>{new Date(item.created_at).toLocaleString()}</Text>
            </View>
          ))}

        {/* COMMISSIONS LIST */}
        {(filter === 'all' || filter === 'commissions') &&
          commissions.map((item) => (
            <View key={`comm-${item.id}`} style={styles.itemCard}>
              <View style={styles.itemTop}>
                <View style={styles.typeRow}>
                  <View style={[styles.iconBox, { backgroundColor: 'rgba(245, 158, 11, 0.15)' }]}>
                    <Ionicons name="gift" size={16} color={Colors.gold} />
                  </View>
                  <View>
                    <Text style={styles.itemTitle}>Level {item.level} Commission</Text>
                    <Text style={styles.itemSubtitle}>From Order: {item.order_no}</Text>
                  </View>
                </View>
                <Text style={styles.amountGold}>+₹{item.amount.toFixed(2)}</Text>
              </View>
              <Text style={styles.timestamp}>{new Date(item.created_at).toLocaleString()}</Text>
            </View>
          ))}

        {/* TRANSFERS LIST */}
        {(filter === 'all' || filter === 'transfers') &&
          transfers.map((item) => (
            <View key={`tx-${item.id}`} style={styles.itemCard}>
              <View style={styles.itemTop}>
                <View style={styles.typeRow}>
                  <View style={[styles.iconBox, { backgroundColor: 'rgba(139, 92, 246, 0.15)' }]}>
                    <Ionicons name="paper-plane" size={16} color={Colors.secondary} />
                  </View>
                  <View>
                    <Text style={styles.itemTitle}>Agent Token Transfer</Text>
                    <Text style={styles.itemSubtitle}>To: {item.target_phone}</Text>
                  </View>
                </View>
                <Text style={styles.amountNegative}>-₹{item.amount.toFixed(2)}</Text>
              </View>
              <Text style={styles.timestamp}>{new Date(item.created_at).toLocaleString()}</Text>
            </View>
          ))}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  filterBar: {
    borderBottomWidth: 1,
    borderBottomColor: Colors.surfaceBorder,
    backgroundColor: Colors.backgroundSecondary,
  },
  filterScroll: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    gap: 8,
  },
  filterChip: {
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 8,
    backgroundColor: Colors.surface,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  filterChipActive: {
    borderColor: Colors.primary,
    backgroundColor: 'rgba(0, 240, 255, 0.12)',
  },
  filterText: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textMuted,
  },
  filterTextActive: {
    color: Colors.primary,
  },
  listContainer: {
    flex: 1,
  },
  listContent: {
    padding: 16,
    paddingBottom: 40,
    gap: 10,
  },
  itemCard: {
    backgroundColor: Colors.surface,
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  itemTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  typeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  iconBox: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
  },
  itemTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.text,
  },
  itemSubtitle: {
    fontSize: 10,
    color: Colors.textDim,
    marginTop: 2,
  },
  amountPositive: {
    fontSize: 14,
    fontWeight: '900',
    color: Colors.accent,
  },
  amountNegative: {
    fontSize: 14,
    fontWeight: '900',
    color: Colors.ruby,
  },
  amountGold: {
    fontSize: 14,
    fontWeight: '900',
    color: Colors.gold,
  },
  itemBottom: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 10,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: Colors.surfaceBorder,
  },
  itemMeta: {
    fontSize: 11,
    color: Colors.textDim,
  },
  statusPill: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  pillSuccess: {
    backgroundColor: 'rgba(16, 185, 129, 0.2)',
  },
  pillPending: {
    backgroundColor: 'rgba(245, 158, 11, 0.2)',
  },
  pillFailed: {
    backgroundColor: 'rgba(239, 68, 68, 0.2)',
  },
  pillText: {
    fontSize: 10,
    fontWeight: '700',
    color: Colors.text,
  },
  timestamp: {
    fontSize: 9,
    color: Colors.textDim,
    marginTop: 6,
  },
});
