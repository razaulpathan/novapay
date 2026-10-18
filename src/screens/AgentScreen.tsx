import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Alert,
  Modal,
  ActivityIndicator,
} from 'react-native';
import * as Clipboard from 'expo-clipboard';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';
import { DatabaseService } from '../services/supabase';
import { CommissionPayout, TokenTransfer, User } from '../types';

export const AgentScreen: React.FC = () => {
  const { currentUser, refreshUser } = useAuth();
  const [commissions, setCommissions] = useState<CommissionPayout[]>([]);
  const [transfers, setTransfers] = useState<TokenTransfer[]>([]);
  const [downlines, setDownlines] = useState<User[]>([]);
  const [copied, setCopied] = useState<boolean>(false);

  // Transfer Modal State
  const [isTransferModalOpen, setIsTransferModalOpen] = useState(false);
  const [targetPhone, setTargetPhone] = useState('');
  const [transferAmount, setTransferAmount] = useState('');
  const [transferNote, setTransferNote] = useState('');
  const [isTransferring, setIsTransferring] = useState(false);

  useEffect(() => {
    loadAgentData();
  }, [currentUser]);

  const loadAgentData = async () => {
    if (!currentUser) return;
    try {
      const commList = await DatabaseService.getCommissions(currentUser.id);
      setCommissions(commList);
      const txList = await DatabaseService.getTransfers(currentUser.id);
      setTransfers(txList);

      const allUsers = await DatabaseService.getAllUsers();
      const myDownlines = allUsers.filter((u) => u.referred_by === currentUser.id);
      setDownlines(myDownlines);
    } catch (e) {
      console.error(e);
    }
  };

  const referralCode = currentUser?.referral_code || 'NOVAVIP';
  const referralLink = `https://novapay.app/join?ref=${referralCode}`;

  const copyReferral = async (text: string) => {
    await Clipboard.setStringAsync(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleExecuteTransfer = async () => {
    if (!currentUser) return;
    const amountNum = parseFloat(transferAmount);
    if (!targetPhone.trim()) {
      Alert.alert('Missing Phone', 'Please enter recipient registered phone number.');
      return;
    }
    if (isNaN(amountNum) || amountNum <= 0) {
      Alert.alert('Invalid Amount', 'Please enter a valid transfer amount.');
      return;
    }
    if (amountNum > currentUser.itoken_balance) {
      Alert.alert('Insufficient Balance', 'You do not have enough iTokens to transfer.');
      return;
    }

    setIsTransferring(true);
    try {
      await DatabaseService.transferTokens(
        currentUser.id,
        targetPhone.trim(),
        amountNum,
        transferNote.trim()
      );
      await refreshUser();
      await loadAgentData();
      setIsTransferModalOpen(false);
      setTargetPhone('');
      setTransferAmount('');
      setTransferNote('');
      Alert.alert('Transfer Successful!', `₹${amountNum.toFixed(2)} iTokens transferred to ${targetPhone}.`);
    } catch (e: any) {
      Alert.alert('Transfer Failed', e.message || 'Could not complete transfer');
    } finally {
      setIsTransferring(false);
    }
  };

  const totalCommissionEarned = commissions.reduce((sum, c) => sum + c.amount, 0);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      {/* AGENT STATUS HERO */}
      <View style={styles.heroCard}>
        <View style={styles.heroTop}>
          <View>
            <View style={styles.vipTag}>
              <Ionicons name="shield-checkmark" size={12} color={Colors.gold} />
              <Text style={styles.vipTagText}>
                {currentUser?.role === 'agent' ? 'VIP AGENT TIER 1' : 'AFFILIATE PROGRAM'}
              </Text>
            </View>
            <Text style={styles.agentTitle}>Commission Center</Text>
          </View>
          <TouchableOpacity
            style={styles.transferButton}
            onPress={() => setIsTransferModalOpen(true)}
            activeOpacity={0.8}
          >
            <Ionicons name="paper-plane" size={14} color="#070B14" />
            <Text style={styles.transferBtnText}>Transfer Tokens</Text>
          </TouchableOpacity>
        </View>

        {/* METRICS ROW */}
        <View style={styles.kpiRow}>
          <View style={styles.kpiItem}>
            <Text style={styles.kpiLabel}>Team Members</Text>
            <Text style={styles.kpiValue}>{downlines.length}</Text>
          </View>
          <View style={styles.kpiDivider} />
          <View style={styles.kpiItem}>
            <Text style={styles.kpiLabel}>Commission Earned</Text>
            <Text style={[styles.kpiValue, { color: Colors.gold }]}>
              ₹{totalCommissionEarned.toFixed(2)}
            </Text>
          </View>
          <View style={styles.kpiDivider} />
          <View style={styles.kpiItem}>
            <Text style={styles.kpiLabel}>Commission Rate</Text>
            <Text style={[styles.kpiValue, { color: Colors.accent }]}>1.5% + 0.5%</Text>
          </View>
        </View>
      </View>

      {/* REFERRAL CODE & LINK CARD */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>My Referral Code & Invitation Link</Text>
        <Text style={styles.cardSubtitle}>Share your link with new members to earn automated commissions on every deposit they make!</Text>

        <View style={styles.codeRow}>
          <View style={styles.codeBox}>
            <Text style={styles.codeLabel}>INVITE CODE</Text>
            <Text style={styles.codeText}>{referralCode}</Text>
          </View>
          <TouchableOpacity
            style={styles.copyBtn}
            onPress={() => copyReferral(referralCode)}
            activeOpacity={0.7}
          >
            <Ionicons name={copied ? 'checkmark' : 'copy'} size={16} color={Colors.primary} />
            <Text style={styles.copyBtnLabel}>{copied ? 'COPIED' : 'COPY'}</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.linkRow}>
          <Text style={styles.linkText} numberOfLines={1}>{referralLink}</Text>
          <TouchableOpacity
            style={styles.shareBtn}
            onPress={() => copyReferral(referralLink)}
          >
            <Ionicons name="share-social-outline" size={16} color={Colors.text} />
          </TouchableOpacity>
        </View>
      </View>

      {/* TIER REWARDS EXPLANATION */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Multi-Tier Commission Structure</Text>
        <View style={styles.tierList}>
          <View style={styles.tierItem}>
            <View style={styles.tierBadge}>
              <Text style={styles.tierBadgeText}>LV 1</Text>
            </View>
            <View style={{ flex: 1, marginLeft: 10 }}>
              <Text style={styles.tierTitle}>Direct Downline (1.50%)</Text>
              <Text style={styles.tierDesc}>Earn 1.5% instant profit on every deposit made by your direct invites.</Text>
            </View>
          </View>

          <View style={styles.tierItem}>
            <View style={[styles.tierBadge, { backgroundColor: 'rgba(139, 92, 246, 0.2)', borderColor: Colors.secondary }]}>
              <Text style={[styles.tierBadgeText, { color: Colors.secondary }]}>LV 2</Text>
            </View>
            <View style={{ flex: 1, marginLeft: 10 }}>
              <Text style={styles.tierTitle}>Indirect Downline (0.50%)</Text>
              <Text style={styles.tierDesc}>Earn 0.5% bonus on deposits made by members invited by your downline.</Text>
            </View>
          </View>
        </View>
      </View>

      {/* TEAM MEMBERS LIST */}
      <View style={styles.card}>
        <View style={styles.cardTitleRow}>
          <Text style={styles.cardTitle}>Team Members ({downlines.length})</Text>
          <Text style={styles.activeTag}>Live Network</Text>
        </View>

        {downlines.length === 0 ? (
          <Text style={styles.emptyText}>No registered downline members yet. Share your code above to build your team!</Text>
        ) : (
          downlines.map((member) => (
            <View key={member.id} style={styles.memberItem}>
              <View style={styles.memberLeft}>
                <View style={styles.avatarBox}>
                  <Ionicons name="person" size={16} color={Colors.primary} />
                </View>
                <View>
                  <Text style={styles.memberName}>{member.name} ({member.phone})</Text>
                  <Text style={styles.memberStatus}>Role: {member.role.toUpperCase()} • Balance: ₹{member.itoken_balance.toFixed(2)}</Text>
                </View>
              </View>
              <TouchableOpacity
                style={styles.quickSendBtn}
                onPress={() => {
                  setTargetPhone(member.phone);
                  setIsTransferModalOpen(true);
                }}
              >
                <Ionicons name="send" size={12} color={Colors.primary} />
                <Text style={styles.quickSendText}>Send</Text>
              </TouchableOpacity>
            </View>
          ))
        )}
      </View>

      {/* RECENT TRANSFERS */}
      {transfers.length > 0 && (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Token Transfers Sent</Text>
          {transfers.map((tx) => (
            <View key={tx.id} style={styles.txRow}>
              <View>
                <Text style={styles.txPhone}>To: {tx.target_phone}</Text>
                <Text style={styles.txDate}>{new Date(tx.created_at).toLocaleString()}</Text>
              </View>
              <Text style={styles.txAmount}>-₹{tx.amount.toFixed(2)}</Text>
            </View>
          ))}
        </View>
      )}

      {/* TOKEN TRANSFER MODAL */}
      <Modal
        visible={isTransferModalOpen}
        transparent
        animationType="slide"
        onRequestClose={() => setIsTransferModalOpen(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Direct Token Transfer</Text>
              <TouchableOpacity onPress={() => setIsTransferModalOpen(false)}>
                <Ionicons name="close" size={24} color={Colors.textDim} />
              </TouchableOpacity>
            </View>

            <Text style={styles.modalSubtitle}>
              Transfer iTokens directly from your wallet to any member's phone number instantly.
            </Text>

            <Text style={styles.inputLabel}>Recipient Phone Number</Text>
            <TextInput
              style={styles.modalInput}
              value={targetPhone}
              onChangeText={setTargetPhone}
              placeholder="e.g. 7777777777"
              placeholderTextColor={Colors.textDim}
              keyboardType="phone-pad"
            />

            <Text style={styles.inputLabel}>Transfer Amount (iTokens / INR)</Text>
            <TextInput
              style={styles.modalInput}
              value={transferAmount}
              onChangeText={setTransferAmount}
              placeholder="e.g. 500"
              placeholderTextColor={Colors.textDim}
              keyboardType="numeric"
            />

            <Text style={styles.inputLabel}>Note (Optional)</Text>
            <TextInput
              style={styles.modalInput}
              value={transferNote}
              onChangeText={setTransferNote}
              placeholder="e.g. Weekly agent payout"
              placeholderTextColor={Colors.textDim}
            />

            <TouchableOpacity
              style={styles.modalSubmitBtn}
              onPress={handleExecuteTransfer}
              disabled={isTransferring}
            >
              {isTransferring ? (
                <ActivityIndicator color="#070B14" />
              ) : (
                <Text style={styles.modalSubmitText}>Confirm & Transfer</Text>
              )}
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
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
  heroCard: {
    backgroundColor: Colors.surface,
    borderRadius: 18,
    padding: 18,
    borderWidth: 1,
    borderColor: 'rgba(245, 158, 11, 0.3)',
    marginBottom: 16,
  },
  heroTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  vipTag: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(245, 158, 11, 0.15)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    alignSelf: 'flex-start',
    marginBottom: 4,
  },
  vipTagText: {
    fontSize: 10,
    fontWeight: '800',
    color: Colors.gold,
  },
  agentTitle: {
    fontSize: 20,
    fontWeight: '900',
    color: Colors.text,
  },
  transferButton: {
    backgroundColor: Colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 10,
  },
  transferBtnText: {
    fontSize: 11,
    fontWeight: '800',
    color: '#070B14',
  },
  kpiRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 12,
    padding: 12,
  },
  kpiItem: {
    flex: 1,
    alignItems: 'center',
  },
  kpiLabel: {
    fontSize: 10,
    color: Colors.textDim,
    marginBottom: 4,
    fontWeight: '600',
  },
  kpiValue: {
    fontSize: 15,
    fontWeight: '900',
    color: Colors.text,
  },
  kpiDivider: {
    width: 1,
    height: 24,
    backgroundColor: Colors.surfaceBorder,
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
    marginBottom: 4,
  },
  cardSubtitle: {
    fontSize: 11,
    color: Colors.textMuted,
    lineHeight: 16,
    marginBottom: 12,
  },
  activeTag: {
    fontSize: 11,
    color: Colors.accent,
    fontWeight: '700',
  },
  codeRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 10,
  },
  codeBox: {
    flex: 1,
    backgroundColor: Colors.backgroundSecondary,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  codeLabel: {
    fontSize: 9,
    color: Colors.textDim,
    fontWeight: '700',
  },
  codeText: {
    fontSize: 16,
    fontWeight: '900',
    color: Colors.primary,
    letterSpacing: 2,
    marginTop: 2,
  },
  copyBtn: {
    backgroundColor: 'rgba(0, 240, 255, 0.15)',
    paddingHorizontal: 16,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 6,
    borderWidth: 1,
    borderColor: Colors.primary,
  },
  copyBtnLabel: {
    color: Colors.primary,
    fontSize: 12,
    fontWeight: '800',
  },
  linkRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.backgroundSecondary,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    gap: 8,
  },
  linkText: {
    flex: 1,
    fontSize: 11,
    color: Colors.textMuted,
  },
  shareBtn: {
    padding: 6,
  },
  tierList: {
    gap: 12,
    marginTop: 8,
  },
  tierItem: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: Colors.backgroundSecondary,
    padding: 12,
    borderRadius: 10,
  },
  tierBadge: {
    backgroundColor: 'rgba(0, 240, 255, 0.2)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: Colors.primary,
  },
  tierBadgeText: {
    fontSize: 11,
    fontWeight: '900',
    color: Colors.primary,
  },
  tierTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.text,
  },
  tierDesc: {
    fontSize: 11,
    color: Colors.textMuted,
    marginTop: 2,
  },
  emptyText: {
    fontSize: 12,
    color: Colors.textDim,
    fontStyle: 'italic',
  },
  memberItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: Colors.surfaceBorder,
  },
  memberLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  avatarBox: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: Colors.backgroundSecondary,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  memberName: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.text,
  },
  memberStatus: {
    fontSize: 10,
    color: Colors.textDim,
    marginTop: 2,
  },
  quickSendBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(0, 240, 255, 0.1)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  quickSendText: {
    fontSize: 10,
    fontWeight: '700',
    color: Colors.primary,
  },
  txRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: Colors.surfaceBorder,
  },
  txPhone: {
    fontSize: 12,
    fontWeight: '700',
    color: Colors.text,
  },
  txDate: {
    fontSize: 10,
    color: Colors.textDim,
    marginTop: 2,
  },
  txAmount: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.ruby,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.75)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: Colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 20,
    paddingBottom: 36,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '900',
    color: Colors.text,
  },
  modalSubtitle: {
    fontSize: 12,
    color: Colors.textMuted,
    marginBottom: 16,
  },
  inputLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.textDim,
    marginBottom: 6,
  },
  modalInput: {
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    color: Colors.text,
    fontSize: 14,
    fontWeight: '700',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 14,
  },
  modalSubmitBtn: {
    backgroundColor: Colors.primary,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 6,
  },
  modalSubmitText: {
    color: '#070B14',
    fontSize: 15,
    fontWeight: '900',
  },
});
