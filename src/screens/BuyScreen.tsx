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
import * as Clipboard from 'expo-clipboard';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';
import { DatabaseService, DEFAULT_SETTINGS } from '../services/supabase';
import { BuyOrder, PlatformSettings } from '../types';

interface BuyScreenProps {
  onSuccessOrder?: () => void;
}

export const BuyScreen: React.FC<BuyScreenProps> = ({ onSuccessOrder }) => {
  const { currentUser, refreshUser } = useAuth();
  const [channel, setChannel] = useState<'UPI_PLUS' | 'UPI' | 'USDT'>('UPI_PLUS');
  const [amount, setAmount] = useState<string>('2000');
  const [utr, setUtr] = useState<string>('');
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [settings, setSettings] = useState<PlatformSettings>(DEFAULT_SETTINGS);
  const [userOrders, setUserOrders] = useState<BuyOrder[]>([]);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);

  const amountPresets = [500, 1000, 2000, 5000, 10000, 25000];

  useEffect(() => {
    loadSettingsAndOrders();
  }, [currentUser]);

  const loadSettingsAndOrders = async () => {
    const s = await DatabaseService.getSettings();
    setSettings(s);
    if (currentUser) {
      const orders = await DatabaseService.getBuyOrders(currentUser.id);
      setUserOrders(orders);
    }
  };

  const parsedAmount = parseFloat(amount) || 0;
  const rewardRate = currentUser?.reward_rate || 2.5;
  const rewardBonus = Number(((parsedAmount * rewardRate) / 100).toFixed(2));
  const totalTokens = Number((parsedAmount + rewardBonus).toFixed(2));

  const copyToClipboard = async (text: string, key: string) => {
    await Clipboard.setStringAsync(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2500);
  };

  const handleSubmitDeposit = async () => {
    if (!currentUser) return;
    if (parsedAmount < 100) {
      Alert.alert('Invalid Amount', 'Minimum deposit is ₹100.');
      return;
    }
    if (!utr.trim() || utr.trim().length < 6) {
      Alert.alert('Missing UTR', 'Please enter a valid 12-digit UTR or Transaction Reference number.');
      return;
    }

    setIsSubmitting(true);
    try {
      const order = await DatabaseService.createBuyOrder({
        user_id: currentUser.id,
        type: channel,
        price: parsedAmount,
        reward: rewardBonus,
        itokens: totalTokens,
        status: 'Pending',
        utr: utr.trim(),
      });

      setUtr('');
      await loadSettingsAndOrders();
      await refreshUser();
      Alert.alert(
        'Order Submitted Successfully!',
        `Your deposit order #${order.order_no} for ₹${parsedAmount} has been recorded.\n\nUTR: ${order.utr}\nIt will be credited once verified by admin.`
      );
      if (onSuccessOrder) onSuccessOrder();
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Failed to submit order');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      {/* CHANNEL SELECTOR */}
      <View style={styles.channelRow}>
        <TouchableOpacity
          style={[styles.channelButton, channel === 'UPI_PLUS' && styles.channelButtonActive]}
          onPress={() => setChannel('UPI_PLUS')}
          activeOpacity={0.8}
        >
          <Ionicons
            name="flash"
            size={16}
            color={channel === 'UPI_PLUS' ? Colors.primary : Colors.textMuted}
          />
          <Text style={[styles.channelText, channel === 'UPI_PLUS' && styles.channelTextActive]}>
            UPI Instant
          </Text>
          <View style={styles.fastBadge}>
            <Text style={styles.fastBadgeText}>2.5% BONUS</Text>
          </View>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.channelButton, channel === 'UPI' && styles.channelButtonActive]}
          onPress={() => setChannel('UPI')}
          activeOpacity={0.8}
        >
          <Ionicons
            name="card"
            size={16}
            color={channel === 'UPI' ? Colors.accent : Colors.textMuted}
          />
          <Text style={[styles.channelText, channel === 'UPI' && styles.channelTextActive]}>
            Bank / UPI
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.channelButton, channel === 'USDT' && styles.channelButtonActive]}
          onPress={() => setChannel('USDT')}
          activeOpacity={0.8}
        >
          <Ionicons
            name="logo-bitcoin"
            size={16}
            color={channel === 'USDT' ? Colors.gold : Colors.textMuted}
          />
          <Text style={[styles.channelText, channel === 'USDT' && styles.channelTextActive]}>
            USDT (TRC20)
          </Text>
        </TouchableOpacity>
      </View>

      {/* AMOUNT INPUT & PRESETS */}
      <View style={styles.card}>
        <Text style={styles.cardHeader}>Enter Deposit Amount (INR)</Text>
        <View style={styles.inputRow}>
          <Text style={styles.inputPrefix}>₹</Text>
          <TextInput
            style={styles.amountInput}
            value={amount}
            onChangeText={setAmount}
            keyboardType="numeric"
            placeholder="0"
            placeholderTextColor={Colors.textDim}
          />
        </View>

        {/* PRESET CHIPS */}
        <View style={styles.presetsGrid}>
          {amountPresets.map((val) => (
            <TouchableOpacity
              key={val}
              style={[styles.presetChip, parsedAmount === val && styles.presetChipActive]}
              onPress={() => setAmount(val.toString())}
            >
              <Text style={[styles.presetText, parsedAmount === val && styles.presetTextActive]}>
                +₹{val.toLocaleString('en-IN')}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* REWARD SUMMARY BOX */}
        <View style={styles.summaryBox}>
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Deposit Value:</Text>
            <Text style={styles.summaryValue}>₹{parsedAmount.toFixed(2)}</Text>
          </View>
          <View style={styles.summaryRow}>
            <Text style={styles.summaryLabel}>Instant Bonus ({rewardRate}%):</Text>
            <Text style={[styles.summaryValue, { color: Colors.accent }]}>+₹{rewardBonus.toFixed(2)}</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryRow}>
            <Text style={styles.totalLabel}>You Receive:</Text>
            <Text style={styles.totalValue}>{totalTokens.toFixed(2)} iTokens</Text>
          </View>
        </View>
      </View>

      {/* PAYMENT INSTRUCTIONS BOX */}
      <View style={styles.card}>
        <View style={styles.paymentHeaderRow}>
          <Ionicons name="information-circle-outline" size={20} color={Colors.primary} />
          <Text style={styles.paymentHeader}>Payment Transfer Details</Text>
        </View>

        {channel === 'USDT' ? (
          <View style={styles.infoBlock}>
            <Text style={styles.infoLabel}>Official USDT TRC-20 Address:</Text>
            <View style={styles.copyRow}>
              <Text style={styles.copyText} numberOfLines={1}>
                {settings.admin_usdt_address}
              </Text>
              <TouchableOpacity
                style={styles.copyButton}
                onPress={() => copyToClipboard(settings.admin_usdt_address, 'usdt')}
              >
                <Ionicons
                  name={copiedKey === 'usdt' ? 'checkmark' : 'copy-outline'}
                  size={16}
                  color={Colors.primary}
                />
                <Text style={styles.copyBtnText}>
                  {copiedKey === 'usdt' ? 'Copied' : 'Copy'}
                </Text>
              </TouchableOpacity>
            </View>
            <Text style={styles.rateNotice}>
              Rate: 1 USDT = ₹{settings.exchange_rate_usdt} | Approx: {(parsedAmount / settings.exchange_rate_usdt).toFixed(2)} USDT
            </Text>
          </View>
        ) : (
          <View style={styles.infoBlock}>
            <Text style={styles.infoLabel}>Official Payment UPI ID:</Text>
            <View style={styles.copyRow}>
              <Text style={styles.copyText}>{settings.admin_upi_id}</Text>
              <TouchableOpacity
                style={styles.copyButton}
                onPress={() => copyToClipboard(settings.admin_upi_id, 'upi')}
              >
                <Ionicons
                  name={copiedKey === 'upi' ? 'checkmark' : 'copy-outline'}
                  size={16}
                  color={Colors.primary}
                />
                <Text style={styles.copyBtnText}>
                  {copiedKey === 'upi' ? 'Copied' : 'Copy'}
                </Text>
              </TouchableOpacity>
            </View>

            <View style={styles.bankBox}>
              <Text style={styles.bankDetail}>Account Holder: {settings.admin_bank_holder}</Text>
              <Text style={styles.bankDetail}>A/C: {settings.admin_bank_account} • IFSC: {settings.admin_bank_ifsc}</Text>
            </View>
          </View>
        )}

        {/* UTR SUBMIT SECTION */}
        <Text style={[styles.cardHeader, { marginTop: 16 }]}>Enter 12-Digit UTR / Transaction Ref</Text>
        <TextInput
          style={styles.utrInput}
          value={utr}
          onChangeText={setUtr}
          placeholder="e.g. 428819204918"
          placeholderTextColor={Colors.textDim}
          keyboardType="numeric"
          maxLength={24}
        />

        <TouchableOpacity
          style={styles.submitButton}
          onPress={handleSubmitDeposit}
          disabled={isSubmitting}
          activeOpacity={0.8}
        >
          {isSubmitting ? (
            <ActivityIndicator color="#070B14" />
          ) : (
            <>
              <Ionicons name="shield-checkmark" size={18} color="#070B14" />
              <Text style={styles.submitButtonText}>Submit Deposit Order</Text>
            </>
          )}
        </TouchableOpacity>
      </View>

      {/* RECENT DEPOSITS LIST */}
      <View style={styles.historySection}>
        <Text style={styles.historyTitle}>My Deposit History</Text>
        {userOrders.length === 0 ? (
          <Text style={styles.noHistoryText}>No deposits recorded yet.</Text>
        ) : (
          userOrders.map((ord) => (
            <View key={ord.id} style={styles.orderItem}>
              <View>
                <Text style={styles.orderNo}>{ord.order_no}</Text>
                <Text style={styles.orderUtr}>UTR: {ord.utr || 'N/A'}</Text>
                <Text style={styles.orderDate}>{new Date(ord.created_at).toLocaleString()}</Text>
              </View>
              <View style={{ alignItems: 'flex-end' }}>
                <Text style={styles.orderAmount}>₹{ord.price.toFixed(2)}</Text>
                <View
                  style={[
                    styles.orderBadge,
                    ord.status === 'Success'
                      ? styles.badgeSuccess
                      : ord.status === 'Pending'
                      ? styles.badgePending
                      : styles.badgeFailed,
                  ]}
                >
                  <Text style={styles.badgeLabel}>{ord.status}</Text>
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
  channelRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 16,
  },
  channelButton: {
    flex: 1,
    backgroundColor: Colors.surface,
    paddingVertical: 10,
    paddingHorizontal: 6,
    borderRadius: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  channelButtonActive: {
    borderColor: Colors.primary,
    backgroundColor: 'rgba(0, 240, 255, 0.08)',
  },
  channelText: {
    fontSize: 11,
    color: Colors.textMuted,
    fontWeight: '700',
    marginTop: 4,
  },
  channelTextActive: {
    color: Colors.text,
  },
  fastBadge: {
    backgroundColor: 'rgba(16, 185, 129, 0.2)',
    paddingHorizontal: 4,
    paddingVertical: 2,
    borderRadius: 4,
    marginTop: 4,
  },
  fastBadgeText: {
    fontSize: 8,
    fontWeight: '800',
    color: Colors.accent,
  },
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 16,
  },
  cardHeader: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.textMuted,
    marginBottom: 10,
  },
  inputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  inputPrefix: {
    fontSize: 24,
    fontWeight: '900',
    color: Colors.primary,
    marginRight: 8,
  },
  amountInput: {
    flex: 1,
    fontSize: 24,
    fontWeight: '800',
    color: Colors.text,
  },
  presetsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 12,
  },
  presetChip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: Colors.backgroundSecondary,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  presetChipActive: {
    borderColor: Colors.primary,
    backgroundColor: 'rgba(0, 240, 255, 0.1)',
  },
  presetText: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.textMuted,
  },
  presetTextActive: {
    color: Colors.primary,
  },
  summaryBox: {
    marginTop: 16,
    padding: 12,
    borderRadius: 10,
    backgroundColor: 'rgba(7, 11, 20, 0.6)',
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 4,
  },
  summaryLabel: {
    fontSize: 12,
    color: Colors.textDim,
  },
  summaryValue: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.text,
  },
  summaryDivider: {
    height: 1,
    backgroundColor: Colors.surfaceBorder,
    marginVertical: 6,
  },
  totalLabel: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.primary,
  },
  totalValue: {
    fontSize: 14,
    fontWeight: '900',
    color: Colors.primary,
  },
  paymentHeaderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 12,
  },
  paymentHeader: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.text,
  },
  infoBlock: {
    backgroundColor: Colors.backgroundSecondary,
    padding: 12,
    borderRadius: 10,
  },
  infoLabel: {
    fontSize: 11,
    color: Colors.textDim,
    marginBottom: 6,
  },
  copyRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: Colors.surface,
    padding: 10,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  copyText: {
    flex: 1,
    fontSize: 13,
    fontWeight: '800',
    color: Colors.text,
  },
  copyButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(0, 240, 255, 0.1)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  copyBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.primary,
  },
  bankBox: {
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: Colors.surfaceBorder,
  },
  bankDetail: {
    fontSize: 11,
    color: Colors.textMuted,
    lineHeight: 18,
  },
  rateNotice: {
    fontSize: 11,
    color: Colors.gold,
    marginTop: 6,
    fontWeight: '600',
  },
  utrInput: {
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    fontWeight: '700',
    color: Colors.text,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 14,
  },
  submitButton: {
    backgroundColor: Colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    borderRadius: 12,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
  },
  submitButtonText: {
    fontSize: 15,
    fontWeight: '900',
    color: '#070B14',
    letterSpacing: 0.5,
  },
  historySection: {
    marginTop: 10,
  },
  historyTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: Colors.text,
    marginBottom: 10,
  },
  noHistoryText: {
    fontSize: 12,
    color: Colors.textDim,
    fontStyle: 'italic',
  },
  orderItem: {
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
  orderNo: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.text,
  },
  orderUtr: {
    fontSize: 11,
    color: Colors.textDim,
    marginTop: 2,
  },
  orderDate: {
    fontSize: 10,
    color: Colors.textDim,
    marginTop: 2,
  },
  orderAmount: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.primary,
  },
  orderBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    marginTop: 4,
  },
  badgeSuccess: {
    backgroundColor: 'rgba(16, 185, 129, 0.2)',
  },
  badgePending: {
    backgroundColor: 'rgba(245, 158, 11, 0.2)',
  },
  badgeFailed: {
    backgroundColor: 'rgba(239, 68, 68, 0.2)',
  },
  badgeLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: Colors.text,
  },
});
