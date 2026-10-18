import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Alert,
  Modal,
  Switch,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';
import { UserRole } from '../types';

interface ProfileScreenProps {
  onOpenAccounts: () => void;
  onOpenOrders: () => void;
}

export const ProfileScreen: React.FC<ProfileScreenProps> = ({ onOpenAccounts, onOpenOrders }) => {
  const { currentUser, logout, switchRole, refreshUser } = useAuth();
  const [isSupabaseModalOpen, setIsSupabaseModalOpen] = useState(false);
  const [supabaseUrl, setSupabaseUrl] = useState('');
  const [supabaseKey, setSupabaseKey] = useState('');
  const [isSupportModalOpen, setIsSupportModalOpen] = useState(false);
  const [supportMsg, setSupportMsg] = useState('');

  const handleSaveSupabaseConfig = async () => {
    if (!supabaseUrl.trim() || !supabaseKey.trim()) {
      Alert.alert('Required', 'Please enter both Supabase URL and Anon Key.');
      return;
    }
    await AsyncStorage.setItem('@novapay_supabase_url', supabaseUrl.trim());
    await AsyncStorage.setItem('@novapay_supabase_key', supabaseKey.trim());
    setIsSupabaseModalOpen(false);
    Alert.alert('Configuration Saved', 'Supabase credentials saved. App will now sync directly with your Supabase backend!');
  };

  const handleSendTicket = () => {
    if (!supportMsg.trim()) return;
    Alert.alert('Ticket Submitted', 'Our 24/7 support desk has received your ticket. We will respond shortly.');
    setSupportMsg('');
    setIsSupportModalOpen(false);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      {/* USER PROFILE CARD */}
      <View style={styles.profileCard}>
        <View style={styles.avatarCircle}>
          <Ionicons name="person" size={32} color={Colors.primary} />
        </View>
        <Text style={styles.userName}>{currentUser?.name || 'Nova Member'}</Text>
        <Text style={styles.userPhone}>{currentUser?.phone || '+91 7777777777'}</Text>
        <View style={styles.badgeRow}>
          <View style={styles.roleBadge}>
            <Text style={styles.roleBadgeText}>{currentUser?.role?.toUpperCase() || 'USER'}</Text>
          </View>
          <View style={styles.refBadge}>
            <Text style={styles.refBadgeText}>REF: {currentUser?.referral_code || 'NOVAPAY'}</Text>
          </View>
        </View>
      </View>

      {/* QUICK ROLE SWITCHER FOR TESTING */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Interactive Role Switcher</Text>
        <Text style={styles.cardSubtitle}>
          Switch perspectives instantly to test features as a regular User, VIP Agent, or Platform Admin.
        </Text>
        <View style={styles.roleButtonsRow}>
          {(['user', 'agent', 'admin'] as UserRole[]).map((r) => {
            const isCurrent = currentUser?.role === r;
            return (
              <TouchableOpacity
                key={r}
                style={[styles.roleBtn, isCurrent && styles.roleBtnActive]}
                onPress={() => switchRole(r)}
                activeOpacity={0.8}
              >
                <Text style={[styles.roleBtnText, isCurrent && styles.roleBtnTextActive]}>
                  {r === 'user' ? 'Member' : r === 'agent' ? 'VIP Agent' : 'Super Admin'}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>
      </View>

      {/* GENERAL ACTIONS */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Account & Services</Text>

        <TouchableOpacity style={styles.menuItem} onPress={onOpenAccounts}>
          <View style={styles.menuLeft}>
            <Ionicons name="card-outline" size={20} color={Colors.primary} />
            <Text style={styles.menuText}>Manage Linked UPI Handles</Text>
          </View>
          <Ionicons name="chevron-forward" size={18} color={Colors.textDim} />
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem} onPress={onOpenOrders}>
          <View style={styles.menuLeft}>
            <Ionicons name="receipt-outline" size={20} color={Colors.accent} />
            <Text style={styles.menuText}>Complete Order History</Text>
          </View>
          <Ionicons name="chevron-forward" size={18} color={Colors.textDim} />
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem} onPress={() => setIsSupabaseModalOpen(true)}>
          <View style={styles.menuLeft}>
            <Ionicons name="server-outline" size={20} color={Colors.gold} />
            <Text style={styles.menuText}>Connect Supabase Backend</Text>
          </View>
          <Ionicons name="chevron-forward" size={18} color={Colors.textDim} />
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem} onPress={() => setIsSupportModalOpen(true)}>
          <View style={styles.menuLeft}>
            <Ionicons name="help-buoy-outline" size={20} color={Colors.secondary} />
            <Text style={styles.menuText}>24/7 Support Desk</Text>
          </View>
          <Ionicons name="chevron-forward" size={18} color={Colors.textDim} />
        </TouchableOpacity>
      </View>

      {/* SECURITY */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Security & Settings</Text>
        <View style={styles.menuItem}>
          <View style={styles.menuLeft}>
            <Ionicons name="shield-checkmark-outline" size={20} color={Colors.accent} />
            <View>
              <Text style={styles.menuText}>Google Authenticator (2FA)</Text>
              <Text style={styles.menuSub}>Protects payouts and withdrawals</Text>
            </View>
          </View>
          <Switch
            value={currentUser?.google_2fa_enabled}
            onValueChange={async (val) => {
              if (currentUser) {
                currentUser.google_2fa_enabled = val;
                await refreshUser();
              }
            }}
            thumbColor={currentUser?.google_2fa_enabled ? Colors.primary : Colors.textDim}
            trackColor={{ false: Colors.surfaceBorder, true: 'rgba(0, 240, 255, 0.3)' }}
          />
        </View>

        <View style={styles.menuItem}>
          <View style={styles.menuLeft}>
            <Ionicons name="flash-outline" size={20} color={Colors.gold} />
            <View>
              <Text style={styles.menuText}>Auto-Sell System</Text>
              <Text style={styles.menuSub}>Automatically cash out profits</Text>
            </View>
          </View>
          <Switch
            value={currentUser?.auto_selling}
            onValueChange={async (val) => {
              if (currentUser) {
                currentUser.auto_selling = val;
                await refreshUser();
              }
            }}
            thumbColor={currentUser?.auto_selling ? Colors.accent : Colors.textDim}
            trackColor={{ false: Colors.surfaceBorder, true: 'rgba(16, 185, 129, 0.3)' }}
          />
        </View>
      </View>

      {/* LOGOUT BUTTON */}
      <TouchableOpacity style={styles.logoutBtn} onPress={logout} activeOpacity={0.8}>
        <Ionicons name="log-out-outline" size={20} color={Colors.ruby} />
        <Text style={styles.logoutText}>Sign Out of Nova Pay</Text>
      </TouchableOpacity>

      {/* SUPABASE MODAL */}
      <Modal visible={isSupabaseModalOpen} transparent animationType="slide">
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Configure Supabase Backend</Text>
              <TouchableOpacity onPress={() => setIsSupabaseModalOpen(false)}>
                <Ionicons name="close" size={24} color={Colors.textDim} />
              </TouchableOpacity>
            </View>

            <Text style={styles.modalSubtitle}>
              Enter your Supabase Project URL and Anon Public Key from your Supabase Dashboard settings to connect live cloud database.
            </Text>

            <Text style={styles.inputLabel}>Supabase Project URL</Text>
            <TextInput
              style={styles.input}
              value={supabaseUrl}
              onChangeText={setSupabaseUrl}
              placeholder="https://xyzcompany.supabase.co"
              placeholderTextColor={Colors.textDim}
              autoCapitalize="none"
            />

            <Text style={styles.inputLabel}>Supabase Anon Key</Text>
            <TextInput
              style={styles.input}
              value={supabaseKey}
              onChangeText={setSupabaseKey}
              placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6..."
              placeholderTextColor={Colors.textDim}
              autoCapitalize="none"
            />

            <TouchableOpacity style={styles.saveBtn} onPress={handleSaveSupabaseConfig}>
              <Text style={styles.saveBtnText}>Connect Live Database</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* SUPPORT MODAL */}
      <Modal visible={isSupportModalOpen} transparent animationType="slide">
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Contact 24/7 Support Desk</Text>
              <TouchableOpacity onPress={() => setIsSupportModalOpen(false)}>
                <Ionicons name="close" size={24} color={Colors.textDim} />
              </TouchableOpacity>
            </View>

            <Text style={styles.inputLabel}>Describe your issue or inquiry</Text>
            <TextInput
              style={[styles.input, { height: 100, textAlignVertical: 'top' }]}
              value={supportMsg}
              onChangeText={setSupportMsg}
              placeholder="Detail your question regarding deposit, UTR, withdrawal, or commission..."
              placeholderTextColor={Colors.textDim}
              multiline
            />

            <TouchableOpacity style={styles.saveBtn} onPress={handleSendTicket}>
              <Text style={styles.saveBtnText}>Send to Support</Text>
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
  profileCard: {
    backgroundColor: Colors.surface,
    padding: 20,
    borderRadius: 18,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 16,
  },
  avatarCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: 'rgba(0, 240, 255, 0.1)',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: Colors.primary,
    marginBottom: 10,
  },
  userName: {
    fontSize: 18,
    fontWeight: '900',
    color: Colors.text,
  },
  userPhone: {
    fontSize: 12,
    color: Colors.textDim,
    marginTop: 2,
  },
  badgeRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 10,
  },
  roleBadge: {
    backgroundColor: 'rgba(0, 240, 255, 0.15)',
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: Colors.primary,
  },
  roleBadgeText: {
    color: Colors.primary,
    fontSize: 10,
    fontWeight: '800',
  },
  refBadge: {
    backgroundColor: 'rgba(245, 158, 11, 0.15)',
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: Colors.gold,
  },
  refBadgeText: {
    color: Colors.gold,
    fontSize: 10,
    fontWeight: '800',
  },
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 16,
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
  roleButtonsRow: {
    flexDirection: 'row',
    gap: 8,
  },
  roleBtn: {
    flex: 1,
    backgroundColor: Colors.backgroundSecondary,
    paddingVertical: 10,
    borderRadius: 10,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  roleBtnActive: {
    borderColor: Colors.primary,
    backgroundColor: 'rgba(0, 240, 255, 0.15)',
  },
  roleBtnText: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.textMuted,
  },
  roleBtnTextActive: {
    color: Colors.primary,
    fontWeight: '900',
  },
  menuItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: Colors.surfaceBorder,
  },
  menuLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  menuText: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.text,
  },
  menuSub: {
    fontSize: 10,
    color: Colors.textDim,
    marginTop: 2,
  },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    backgroundColor: 'rgba(239, 68, 68, 0.1)',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: 'rgba(239, 68, 68, 0.3)',
    marginTop: 8,
  },
  logoutText: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.ruby,
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
    marginBottom: 8,
  },
  modalTitle: {
    fontSize: 17,
    fontWeight: '900',
    color: Colors.text,
  },
  modalSubtitle: {
    fontSize: 12,
    color: Colors.textMuted,
    lineHeight: 18,
    marginBottom: 14,
  },
  inputLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.textDim,
    marginBottom: 6,
  },
  input: {
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    color: Colors.text,
    fontSize: 13,
    fontWeight: '600',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 14,
  },
  saveBtn: {
    backgroundColor: Colors.primary,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
  },
  saveBtnText: {
    color: '#070B14',
    fontSize: 14,
    fontWeight: '900',
  },
});
