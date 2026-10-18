import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  Image,
  ScrollView,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';

export const AuthScreen: React.FC = () => {
  const { login, register } = useAuth();
  const [tab, setTab] = useState<'login' | 'register'>('login');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [name, setName] = useState('');
  const [referralCode, setReferralCode] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async () => {
    if (!phone.trim()) {
      Alert.alert('Phone Required', 'Please enter your 10-digit phone number.');
      return;
    }
    if (!password.trim()) {
      Alert.alert('Password Required', 'Please enter your password.');
      return;
    }

    setLoading(true);
    try {
      if (tab === 'login') {
        const res = await login(phone, password);
        if (!res.success) {
          Alert.alert('Login Failed', res.message || 'Invalid credentials');
        }
      } else {
        const res = await register(phone, password, referralCode, name);
        if (!res.success) {
          Alert.alert('Registration Failed', res.message || 'Could not register');
        } else {
          Alert.alert('Welcome!', 'Registration successful! +₹50.00 Newbie welcome bonus credited.');
        }
      }
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Authentication error');
    } finally {
      setLoading(false);
    }
  };

  const quickLogin = async (demoPhone: string) => {
    setLoading(true);
    await login(demoPhone);
    setLoading(false);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      {/* BRAND HEADER */}
      <View style={styles.brandHeader}>
        <Image
          source={require('../../assets/icon.png')}
          style={styles.logo}
          resizeMode="contain"
        />
        <Text style={styles.brandTitle}>NOVA PAY</Text>
        <Text style={styles.brandTagline}>NEXT-GEN FINTECH & AGENT REWARDS</Text>
      </View>

      {/* AUTH CARD */}
      <View style={styles.card}>
        <View style={styles.tabRow}>
          <TouchableOpacity
            style={[styles.tabBtn, tab === 'login' && styles.tabBtnActive]}
            onPress={() => setTab('login')}
          >
            <Text style={[styles.tabText, tab === 'login' && styles.tabTextActive]}>Sign In</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.tabBtn, tab === 'register' && styles.tabBtnActive]}
            onPress={() => setTab('register')}
          >
            <Text style={[styles.tabText, tab === 'register' && styles.tabTextActive]}>Register</Text>
          </TouchableOpacity>
        </View>

        {tab === 'register' && (
          <>
            <Text style={styles.inputLabel}>Full Name</Text>
            <TextInput
              style={styles.input}
              value={name}
              onChangeText={setName}
              placeholder="e.g. Vikram Sharma"
              placeholderTextColor={Colors.textDim}
            />
          </>
        )}

        <Text style={styles.inputLabel}>Mobile Phone Number</Text>
        <View style={styles.phoneInputRow}>
          <Text style={styles.phonePrefix}>+91</Text>
          <TextInput
            style={styles.phoneInput}
            value={phone}
            onChangeText={setPhone}
            placeholder="9876543210"
            placeholderTextColor={Colors.textDim}
            keyboardType="phone-pad"
            maxLength={10}
          />
        </View>

        <Text style={styles.inputLabel}>Password</Text>
        <TextInput
          style={styles.input}
          value={password}
          onChangeText={setPassword}
          placeholder="••••••••"
          placeholderTextColor={Colors.textDim}
          secureTextEntry
        />

        {tab === 'register' && (
          <>
            <Text style={styles.inputLabel}>Referral Code (Optional)</Text>
            <TextInput
              style={styles.input}
              value={referralCode}
              onChangeText={setReferralCode}
              placeholder="e.g. AGENTVIP"
              placeholderTextColor={Colors.textDim}
              autoCapitalize="characters"
            />
          </>
        )}

        <TouchableOpacity
          style={styles.submitBtn}
          onPress={handleSubmit}
          disabled={loading}
          activeOpacity={0.85}
        >
          {loading ? (
            <ActivityIndicator color="#070B14" />
          ) : (
            <Text style={styles.submitBtnText}>
              {tab === 'login' ? 'Sign In to Account' : 'Create Nova Pay Account'}
            </Text>
          )}
        </TouchableOpacity>
      </View>

      {/* QUICK ONE-TAP DEMO LOGINS */}
      <View style={styles.demoSection}>
        <Text style={styles.demoTitle}>QUICK 1-TAP DEMO ACCESS</Text>
        <Text style={styles.demoSubtitle}>Experience different roles immediately without typing</Text>

        <View style={styles.demoButtonsList}>
          <TouchableOpacity
            style={styles.demoBtn}
            onPress={() => quickLogin('7777777777')}
            activeOpacity={0.8}
          >
            <View style={styles.demoBtnLeft}>
              <Ionicons name="person" size={16} color={Colors.primary} />
              <View>
                <Text style={styles.demoRole}>Demo Member Account</Text>
                <Text style={styles.demoPhone}>+91 7777777777 • Balance: ₹4,250</Text>
              </View>
            </View>
            <Ionicons name="arrow-forward" size={16} color={Colors.primary} />
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.demoBtn}
            onPress={() => quickLogin('8888888888')}
            activeOpacity={0.8}
          >
            <View style={styles.demoBtnLeft}>
              <Ionicons name="people" size={16} color={Colors.gold} />
              <View>
                <Text style={styles.demoRole}>Master Agent Account</Text>
                <Text style={styles.demoPhone}>+91 8888888888 • VIP Commissions</Text>
              </View>
            </View>
            <Ionicons name="arrow-forward" size={16} color={Colors.gold} />
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.demoBtn}
            onPress={() => quickLogin('9999999999')}
            activeOpacity={0.8}
          >
            <View style={styles.demoBtnLeft}>
              <Ionicons name="shield-checkmark" size={16} color={Colors.accent} />
              <View>
                <Text style={styles.demoRole}>Super Administrator</Text>
                <Text style={styles.demoPhone}>+91 9999999999 • Full Control</Text>
              </View>
            </View>
            <Ionicons name="arrow-forward" size={16} color={Colors.accent} />
          </TouchableOpacity>
        </View>
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
    padding: 20,
    paddingTop: 48,
    paddingBottom: 40,
  },
  brandHeader: {
    alignItems: 'center',
    marginBottom: 24,
  },
  logo: {
    width: 72,
    height: 72,
    borderRadius: 18,
    borderWidth: 2,
    borderColor: Colors.primary,
    marginBottom: 12,
  },
  brandTitle: {
    fontSize: 26,
    fontWeight: '900',
    color: Colors.text,
    letterSpacing: 2,
  },
  brandTagline: {
    fontSize: 10,
    fontWeight: '800',
    color: Colors.primary,
    letterSpacing: 1.5,
    marginTop: 4,
  },
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 20,
    padding: 20,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 24,
  },
  tabRow: {
    flexDirection: 'row',
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 10,
    padding: 4,
    marginBottom: 16,
  },
  tabBtn: {
    flex: 1,
    paddingVertical: 10,
    alignItems: 'center',
    borderRadius: 8,
  },
  tabBtnActive: {
    backgroundColor: Colors.surface,
  },
  tabText: {
    fontSize: 13,
    fontWeight: '700',
    color: Colors.textDim,
  },
  tabTextActive: {
    color: Colors.text,
  },
  inputLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: Colors.textDim,
    marginBottom: 6,
  },
  phoneInputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    paddingHorizontal: 12,
    marginBottom: 14,
  },
  phonePrefix: {
    fontSize: 14,
    fontWeight: '800',
    color: Colors.primary,
    marginRight: 8,
  },
  phoneInput: {
    flex: 1,
    color: Colors.text,
    fontSize: 14,
    fontWeight: '700',
    paddingVertical: 12,
  },
  input: {
    backgroundColor: Colors.backgroundSecondary,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    color: Colors.text,
    fontSize: 14,
    fontWeight: '600',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
    marginBottom: 14,
  },
  submitBtn: {
    backgroundColor: Colors.primary,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 6,
  },
  submitBtnText: {
    color: '#070B14',
    fontSize: 15,
    fontWeight: '900',
  },
  demoSection: {
    borderTopWidth: 1,
    borderTopColor: Colors.surfaceBorder,
    paddingTop: 20,
  },
  demoTitle: {
    fontSize: 11,
    fontWeight: '800',
    color: Colors.primary,
    letterSpacing: 1.5,
    textAlign: 'center',
  },
  demoSubtitle: {
    fontSize: 11,
    color: Colors.textDim,
    textAlign: 'center',
    marginTop: 2,
    marginBottom: 14,
  },
  demoButtonsList: {
    gap: 10,
  },
  demoBtn: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: Colors.surface,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  demoBtnLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  demoRole: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.text,
  },
  demoPhone: {
    fontSize: 11,
    color: Colors.textDim,
    marginTop: 2,
  },
});
