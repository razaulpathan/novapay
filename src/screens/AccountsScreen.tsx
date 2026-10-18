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
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';
import { DatabaseService } from '../services/supabase';
import { UpiAccount } from '../types';

export const AccountsScreen: React.FC = () => {
  const { currentUser } = useAuth();
  const [upiList, setUpiList] = useState<UpiAccount[]>([]);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Form states
  const [partner, setPartner] = useState('PhonePe');
  const [name, setName] = useState('');
  const [upiNo, setUpiNo] = useState('');

  const partners = ['PhonePe', 'Google Pay', 'Paytm', 'BHIM UPI', 'IndusPay', 'Other Bank'];

  useEffect(() => {
    loadAccounts();
  }, [currentUser]);

  const loadAccounts = async () => {
    if (!currentUser) return;
    const accounts = await DatabaseService.getUpiAccounts(currentUser.id);
    setUpiList(accounts);
  };

  const handleAddUpi = async () => {
    if (!currentUser) return;
    if (!name.trim()) {
      Alert.alert('Required', 'Please enter account holder name.');
      return;
    }
    if (!upiNo.trim() || !upiNo.includes('@')) {
      Alert.alert('Invalid UPI ID', 'Please enter a valid UPI ID (e.g. yourname@ybl).');
      return;
    }

    try {
      await DatabaseService.addUpiAccount({
        user_id: currentUser.id,
        partner,
        name: name.trim(),
        upi_no: upiNo.trim().toLowerCase(),
        is_default: upiList.length === 0,
        status: 'Active',
      });

      setName('');
      setUpiNo('');
      setIsModalOpen(false);
      await loadAccounts();
      Alert.alert('Success', 'UPI handle linked successfully!');
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Failed to add UPI');
    }
  };

  const handleDelete = (id: number, handle: string) => {
    Alert.alert('Remove UPI Handle', `Are you sure you want to unlink ${handle}?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Unlink',
        style: 'destructive',
        onPress: async () => {
          await DatabaseService.deleteUpiAccount(id);
          await loadAccounts();
        },
      },
    ]);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.headerRow}>
        <View>
          <Text style={styles.title}>Linked UPI Accounts</Text>
          <Text style={styles.subtitle}>Payouts from sell orders are instantly credited to these accounts.</Text>
        </View>
        <TouchableOpacity
          style={styles.addBtn}
          onPress={() => setIsModalOpen(true)}
          activeOpacity={0.8}
        >
          <Ionicons name="add" size={18} color="#070B14" />
          <Text style={styles.addBtnText}>Link UPI</Text>
        </TouchableOpacity>
      </View>

      {upiList.length === 0 ? (
        <View style={styles.emptyCard}>
          <Ionicons name="card-outline" size={40} color={Colors.textDim} />
          <Text style={styles.emptyTitle}>No UPI Handles Linked</Text>
          <Text style={styles.emptyDesc}>Add your PhonePe, Google Pay, or Paytm UPI handle to enable one-tap withdrawals.</Text>
          <TouchableOpacity
            style={styles.addFirstBtn}
            onPress={() => setIsModalOpen(true)}
          >
            <Text style={styles.addFirstBtnText}>+ Add First UPI ID</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <View style={styles.list}>
          {upiList.map((item) => (
            <View key={item.id} style={styles.card}>
              <View style={styles.cardHeader}>
                <View style={styles.partnerBadge}>
                  <Text style={styles.partnerText}>{item.partner}</Text>
                </View>
                {item.is_default && (
                  <View style={styles.defaultBadge}>
                    <Text style={styles.defaultText}>DEFAULT</Text>
                  </View>
                )}
              </View>

              <Text style={styles.upiHandle}>{item.upi_no}</Text>
              <Text style={styles.holderName}>Holder: {item.name}</Text>

              <View style={styles.cardFooter}>
                <View style={styles.verifiedRow}>
                  <Ionicons name="checkmark-circle" size={14} color={Colors.accent} />
                  <Text style={styles.verifiedText}>Active for Payouts</Text>
                </View>

                <TouchableOpacity
                  style={styles.deleteBtn}
                  onPress={() => handleDelete(item.id, item.upi_no)}
                >
                  <Ionicons name="trash-outline" size={16} color={Colors.ruby} />
                </TouchableOpacity>
              </View>
            </View>
          ))}
        </View>
      )}

      {/* ADD UPI MODAL */}
      <Modal
        visible={isModalOpen}
        transparent
        animationType="slide"
        onRequestClose={() => setIsModalOpen(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Link UPI Account</Text>
              <TouchableOpacity onPress={() => setIsModalOpen(false)}>
                <Ionicons name="close" size={24} color={Colors.textDim} />
              </TouchableOpacity>
            </View>

            <Text style={styles.label}>Select Payment Partner</Text>
            <View style={styles.partnersGrid}>
              {partners.map((p) => (
                <TouchableOpacity
                  key={p}
                  style={[styles.partnerChip, partner === p && styles.partnerChipActive]}
                  onPress={() => setPartner(p)}
                >
                  <Text style={[styles.partnerChipText, partner === p && styles.partnerChipTextActive]}>
                    {p}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            <Text style={styles.label}>Account Holder Full Name</Text>
            <TextInput
              style={styles.input}
              value={name}
              onChangeText={setName}
              placeholder="e.g. Vikram Sharma"
              placeholderTextColor={Colors.textDim}
            />

            <Text style={styles.label}>UPI Handle / VPA ID</Text>
            <TextInput
              style={styles.input}
              value={upiNo}
              onChangeText={setUpiNo}
              placeholder="e.g. username@ybl, mobile@paytm"
              placeholderTextColor={Colors.textDim}
              autoCapitalize="none"
            />

            <TouchableOpacity
              style={styles.saveBtn}
              onPress={handleAddUpi}
              activeOpacity={0.8}
            >
              <Text style={styles.saveBtnText}>Verify & Save UPI</Text>
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
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  title: {
    fontSize: 18,
    fontWeight: '900',
    color: Colors.text,
  },
  subtitle: {
    fontSize: 11,
    color: Colors.textMuted,
    marginTop: 2,
    maxWidth: 220,
    lineHeight: 16,
  },
  addBtn: {
    backgroundColor: Colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 10,
  },
  addBtnText: {
    color: '#070B14',
    fontSize: 12,
    fontWeight: '800',
  },
  emptyCard: {
    backgroundColor: Colors.surface,
    padding: 30,
    borderRadius: 16,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginTop: 20,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: Colors.text,
    marginTop: 12,
  },
  emptyDesc: {
    fontSize: 12,
    color: Colors.textDim,
    textAlign: 'center',
    marginTop: 6,
    lineHeight: 18,
  },
  addFirstBtn: {
    backgroundColor: 'rgba(0, 240, 255, 0.15)',
    borderWidth: 1,
    borderColor: Colors.primary,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 10,
    marginTop: 16,
  },
  addFirstBtnText: {
    color: Colors.primary,
    fontWeight: '800',
    fontSize: 13,
  },
  list: {
    gap: 12,
  },
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 14,
    padding: 16,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  partnerBadge: {
    backgroundColor: 'rgba(0, 240, 255, 0.1)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  partnerText: {
    color: Colors.primary,
    fontSize: 11,
    fontWeight: '800',
  },
  defaultBadge: {
    backgroundColor: 'rgba(16, 185, 129, 0.15)',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  defaultText: {
    color: Colors.accent,
    fontSize: 9,
    fontWeight: '800',
  },
  upiHandle: {
    fontSize: 16,
    fontWeight: '900',
    color: Colors.text,
  },
  holderName: {
    fontSize: 12,
    color: Colors.textDim,
    marginTop: 4,
  },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 12,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: Colors.surfaceBorder,
  },
  verifiedRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  verifiedText: {
    fontSize: 11,
    color: Colors.accent,
    fontWeight: '600',
  },
  deleteBtn: {
    padding: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.75)',
    justifyContent: 'flex-end',
  },
  modalCard: {
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
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '900',
    color: Colors.text,
  },
  label: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.textDim,
    marginBottom: 6,
  },
  partnersGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 14,
  },
  partnerChip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    backgroundColor: Colors.backgroundSecondary,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  partnerChipActive: {
    borderColor: Colors.primary,
    backgroundColor: 'rgba(0, 240, 255, 0.1)',
  },
  partnerChipText: {
    fontSize: 11,
    color: Colors.textMuted,
    fontWeight: '700',
  },
  partnerChipTextActive: {
    color: Colors.primary,
  },
  input: {
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
  saveBtn: {
    backgroundColor: Colors.primary,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 6,
  },
  saveBtnText: {
    color: '#070B14',
    fontSize: 15,
    fontWeight: '900',
  },
});
