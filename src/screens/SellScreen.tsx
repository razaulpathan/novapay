import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';
import { DatabaseService } from '../services/supabase';
import { UpiAccount, SellOrder } from '../types';

interface SellScreenProps {
  onOpenAccounts?: () => void;
}

export const SellScreen: React.FC<SellScreenProps> = ({ onOpenAccounts }) => {
  const { currentUser, refreshUser } = useAuth();
  const [amount, setAmount] = useState<string>('500');
  const [upiAccounts, setUpiAccounts] = useState<UpiAccount[]>([]);
  const [selectedUpiId, setSelectedUpiId] = useState<number | null>(null);
  const [sellOrders, setSellOrders] = useState<SellOrder[]>([]);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);

  useEffect(() => {
    loadAccountsAndOrders();
  }, [currentUser]);

  const loadAccountsAndOrders = async () => {
    if (!currentUser) return;
    const accounts = await DatabaseService.getUpiAccounts(currentUser.id);
    setUpiAccounts(accounts);
    if (accounts.length > 0) {
      const def = accounts.find((a) => a.is_default) || accounts[0];
      setSelectedUpiId(def.id);
    }
    const orders = await DatabaseService.getSellOrders(currentUser.id);
    setSellOrders(orders);
  };

  const parsedAmount = parseFloat(amount) || 0;
  const balance = currentUser?.itoken_balance || 0;

  const handleSell = async () => {
    if (!currentUser) return;
    if (parsedAmount < 100) {
      Alert.alert('Minimum Limit', 'Minimum withdrawal amount is ₹100.');
      return;
    }
    if (parsedAmount > balance) {
      Alert.alert('Insufficient Balance', 'You do not have enough iTokens in your wallet.');
      return;
    }
    if (!selectedUpiId) {
      Alert.alert('No UPI Account', 'Please select or link a UPI account to receive your payout.');
      return;
    }

    const selectedAcc = upiAccounts.find((a) => a.id === selectedUpiId);
    if (!selectedAcc) return;

    setIsSubmitting(true);
    try {
      await DatabaseService.createSellOrder(
        currentUser.id,
        parsedAmount,
        selectedAcc.upi_no,
        selectedAcc.partner
      );
      await refreshUser();
      await loadAccountsAndOrders();
      Alert.alert(
        'Withdrawal Requested!',
        `Your request to withdraw ₹${parsedAmount.toFixed(2)} to ${selectedAcc.upi_no} is processing.`
      );
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Failed to submit withdrawal');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      {/* BALANCE OVERVIEW */}
      <View style={styles.balanceCard}>
        <Text style={styles.balanceLabel}>AVAILABLE FOR WITHDRAWAL</Text>
        <Text style={styles.balanceValue}>₹{balance.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</Text>
        <Text style={styles.balanceSub}>Zero processing fees • Instant UPI payout</Text>
      </View>

      {/* SELECT RECEIVING UPI ACCOUNT */}
      <View style={styles.card}>
        <View style={styles.cardTitleRow}>
          <Text style={styles.cardTitle}>Receiving UPI Account</Text>
          <TouchableOpacity onPress={onOpenAccounts}>
            <Text style={styles.linkText}>+ Add New</Text>
          </TouchableOpacity>
        </View>

        {upiAccounts.length === 0 ? (
          <TouchableOpacity style={styles.noUpiBox} onPress={onOpenAccounts}>
            <Ionicons name="add-circle-outline" size={24} color={Colors.primary} />
            <Text style={styles.noUpiText}>No UPI handle linked. Tap here to add one.</Text>
          </TouchableOpacity>
        ) : (
          <View style={styles.upiList}>
            {upiAccounts.map((acc) => {
              const isSelected = selectedUpiId === acc.id;
              return (
                <TouchableOpacity
                  key={acc.id}
                  style={[styles.upiItem, isSelected && styles.upiItemActive]}
                  onPress={() => setSelectedUpiId(acc.id)}
                  activeOpacity={0.8}
                >
                  <View style={styles.upiItemLeft}>
                    <Ionicons
                      name={isSelected ? 'radio-button-on' : 'radio-button-off'}
                      size={20}
                      color={isSelected ? Colors.accent : Colors.textDim}
                    />
                    <View style={{ marginLeft: 10 }}>
                      <Text style={styles.upiHolder}>{acc.name} ({acc.partner})</Text>
                      <Text style={styles.upiHandle}>{acc.upi_no}</Text>
                    </View>
                  </View>
                  {acc.is_default && (
                    <View style={styles.defaultBadge}>
                      <Text style={styles.defaultBadgeText}>DEFAULT</Text>
                    </View>
                  )}
                </TouchableOpacity>
              );
            })}
          </View>
        )}
      </View>

      {/* AMOUNT INPUT */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Withdraw Amount (INR)</Text>
        <View style={styles.inputContainer}>
          <Text style={styles.inputPrefix}>₹</Text>
          <TextInput
            style={styles.amountInput}
            value={amount}
            onChangeText={setAmount}
            keyboardType="numeric"
            placeholder="0"
            placeholderTextColor={Colors.textDim}
          />
          <TouchableOpacity
            style={styles.maxBtn}
            onPress={() => setAmount(Math.floor(balance).toString())}
          >
            <Text style={styles.maxBtnText}>MAX</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.feeBreakdown}>
          <View style={styles.breakdownRow}>
            <Text style={styles.breakdownLabel}>Platform Withdrawal Fee:</Text>
            <Text style={[styles.breakdownValue, { color: Colors.accent }]}>0% (FREE)</Text>
          </View>
          <View style={styles.breakdownRow}>
            <Text style={styles.breakdownLabel}>Estimated Settlement:</Text>
            <Text style={styles.breakdownValue}>5 - 15 Minutes</Text>
          </View>
          <View style={styles.breakdownDivider} />
          <View style={styles.breakdownRow}>
            <Text style={styles.finalLabel}>You Receive in Bank:</Text>
            <Text style={styles.finalValue}>₹{parsedAmount.toFixed(2)}</Text>
          </View>
        </View>

        <TouchableOpacity
          style={[styles.sellButton, (!selectedUpiId || parsedAmount <= 0) && styles.sellButtonDisabled]}
          onPress={handleSell}
          disabled={isSubmitting || !selectedUpiId || parsedAmount <= 0}
          activeOpacity={0.85}
        >
          {isSubmitting ? (
            <ActivityIndicator color="#070B14" />
          ) : (
            <>
              <Ionicons name="arrow-up-circle" size={20} color="#070B14" />
              <Text style={styles.sellButtonText}>Withdraw ₹{parsedAmount.toFixed(2)}</Text>
            </>
          )}
        </TouchableOpacity>
      </View>

      {/* WITHDRAWAL HISTORY */}
      <View style={styles.historySection}>
        <Text style={styles.sectionHeader}>Payout History</Text>
        {sellOrders.length === 0 ? (
          <Text style={styles.emptyText}>No payouts recorded yet.</Text>
        ) : (
          sellOrders.map((ord) => (
            <View key={ord.id} style={styles.historyItem}>
              <View>
                <Text style={styles.historyPartner}>{ord.partner} • {ord.upi_no}</Text>
                <Text style={styles.historyDate}>{new Date(ord.created_at).toLocaleString()}</Text>
              </View>
              <View style={{ alignItems: 'flex-end' }}>
                <Text style={styles.historyAmount}>-₹{ord.amount.toFixed(2)}</Text>
                <View
                  style={[
                    styles.statusPill,
                    ord.status === 'Success'
                      ? styles.pillSuccess
                      : ord.status === 'Paying'
                      ? styles.pillPaying
                      : styles.pillFailed,
                  ]}
                >
                  <Text style={styles.pillText}>{ord.status}</Text>
                </View>
              </View>
            </View>
          ))
        )}
      </View>
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
    paddingBottom: 40,
  },
  balanceCard: {
    backgroundColor: Colors.surface,
    padding: 18,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(16, 185, 129, 0.3)',
    marginBottom: 16,
    alignItems: 'center',
  },
  balanceLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.textMuted,
    letterSpacing: 1,
  },
  balanceValue: {
    fontSize: 32,
    fontWeight: '900',
    color: Colors.accent,
    marginVertical: 4,
  },
  balanceSub: {
    fontSize: 11,
    color: Colors.textDim,
  },
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 16,
  },
  cardTitleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  cardTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.text,
  },
  linkText: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.primary,
  },
  noUpiBox: {
    padding: 16,
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  noUpiText: {
    color: Colors.textMuted,
    fontSize: 12,
  },
  upiList: {
    gap: 8,
  },
  upiItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 12,
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  upiItemActive: {
    borderColor: Colors.accent,
    backgroundColor: 'rgba(16, 185, 129, 0.08)',
  },
  upiItemLeft: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  upiHolder: {
    color: Colors.text,
    fontSize: 13,
    fontWeight: '700',
  },
  upiHandle: {
    color: Colors.textDim,
    fontSize: 11,
    marginTop: 2,
  },
  defaultBadge: {
    backgroundColor: 'rgba(0, 240, 255, 0.15)',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  defaultBadgeText: {
    color: Colors.primary,
    fontSize: 9,
    fontWeight: '800',
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginTop: 8,
  },
  inputPrefix: {
    fontSize: 24,
    fontWeight: '900',
    color: Colors.accent,
    marginRight: 8,
  },
  amountInput: {
    flex: 1,
    fontSize: 24,
    fontWeight: '800',
    color: Colors.text,
  },
  maxBtn: {
    backgroundColor: 'rgba(16, 185, 129, 0.2)',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
  },
  maxBtnText: {
    color: Colors.accent,
    fontSize: 11,
    fontWeight: '900',
  },
  feeBreakdown: {
    backgroundColor: 'rgba(7, 11, 20, 0.6)',
    padding: 12,
    borderRadius: 10,
    marginTop: 14,
  },
  breakdownRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 3,
  },
  breakdownLabel: {
    fontSize: 12,
    color: Colors.textDim,
  },
  breakdownValue: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.text,
  },
  breakdownDivider: {
    height: 1,
    backgroundColor: Colors.surfaceBorder,
    marginVertical: 6,
  },
  finalLabel: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.accent,
  },
  finalValue: {
    fontSize: 14,
    fontWeight: '900',
    color: Colors.accent,
  },
  sellButton: {
    backgroundColor: Colors.accent,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    borderRadius: 12,
    marginTop: 16,
  },
  sellButtonDisabled: {
    opacity: 0.5,
  },
  sellButtonText: {
    fontSize: 15,
    fontWeight: '900',
    color: '#070B14',
  },
  historySection: {
    marginTop: 8,
  },
  sectionHeader: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
    marginBottom: 10,
  },
  emptyText: {
    color: Colors.textDim,
    fontSize: 12,
    fontStyle: 'italic',
  },
  historyItem: {
    backgroundColor: Colors.surface,
    padding: 12,
    borderRadius: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  historyPartner: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.text,
  },
  historyDate: {
    fontSize: 10,
    color: Colors.textDim,
    marginTop: 2,
  },
  historyAmount: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.ruby,
  },
  statusPill: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    marginTop: 4,
  },
  pillSuccess: {
    backgroundColor: 'rgba(16, 185, 129, 0.2)',
  },
  pillPaying: {
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
});
