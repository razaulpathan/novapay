import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { User, UserRole } from '../types';
import { DatabaseService, DEFAULT_USERS } from './supabase';

interface AuthContextType {
  currentUser: User | null;
  isLoading: boolean;
  login: (phone: string, password?: string) => Promise<{ success: boolean; message?: string }>;
  register: (phone: string, password?: string, referralCode?: string, name?: string) => Promise<{ success: boolean; message?: string }>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  switchRole: (role: UserRole) => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({} as AuthContextType);

const CURRENT_USER_KEY = '@novapay_logged_in_phone';

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  useEffect(() => {
    bootstrap();
  }, []);

  const bootstrap = async () => {
    try {
      await DatabaseService.initDemoData();
      const savedPhone = await AsyncStorage.getItem(CURRENT_USER_KEY);
      if (savedPhone) {
        const user = await DatabaseService.getUserByPhone(savedPhone);
        if (user) {
          setCurrentUser(user);
          setIsLoading(false);
          return;
        }
      }
      // Default to demo user for instantaneous interactive testing
      const defaultUser = await DatabaseService.getUserByPhone('7777777777');
      if (defaultUser) {
        setCurrentUser(defaultUser);
        await AsyncStorage.setItem(CURRENT_USER_KEY, '7777777777');
      }
    } catch (e) {
      console.error('Error during bootstrap', e);
    } finally {
      setIsLoading(false);
    }
  };

  const refreshUser = async () => {
    if (!currentUser) return;
    const fresh = await DatabaseService.getUserById(currentUser.id);
    if (fresh) setCurrentUser({ ...fresh });
  };

  const login = async (phone: string, password?: string) => {
    const user = await DatabaseService.getUserByPhone(phone.trim());
    if (!user) {
      return { success: false, message: 'Account not found. Please register.' };
    }
    if (password && user.password && user.password !== password) {
      return { success: false, message: 'Incorrect password.' };
    }
    setCurrentUser(user);
    await AsyncStorage.setItem(CURRENT_USER_KEY, user.phone);
    return { success: true };
  };

  const register = async (phone: string, password = 'pass', referralCode = '', name = 'Nova User') => {
    const existing = await DatabaseService.getUserByPhone(phone.trim());
    if (existing) {
      return { success: false, message: 'Phone number is already registered.' };
    }

    let referredById: number | null = null;
    if (referralCode.trim()) {
      const allUsers = await DatabaseService.getAllUsers();
      const ref = allUsers.find(
        (u) => u.referral_code.toUpperCase() === referralCode.trim().toUpperCase()
      );
      if (ref) referredById = ref.id;
    }

    const newUser: User = {
      id: Date.now(),
      phone: phone.trim(),
      name: name.trim() || 'Nova User',
      password: password.trim(),
      itoken_balance: 50.0, // Newbie welcome bonus credited!
      today_profit: 0.0,
      reward_rate: 2.5,
      auto_selling: true,
      order_min: 100,
      order_max: 20000,
      referred_by: referredById,
      referral_code: `NOVA${phone.slice(-4)}${Math.floor(10 + Math.random() * 90)}`,
      role: 'user',
      status: 'Active',
      google_2fa_enabled: false,
    };

    await DatabaseService.saveUser(newUser);
    setCurrentUser(newUser);
    await AsyncStorage.setItem(CURRENT_USER_KEY, newUser.phone);
    return { success: true };
  };

  const logout = async () => {
    await AsyncStorage.removeItem(CURRENT_USER_KEY);
    // Switch to unauthenticated or guest
    setCurrentUser(null);
  };

  const switchRole = async (role: UserRole) => {
    if (!currentUser) return;
    const targetDemo = DEFAULT_USERS.find((u) => u.role === role);
    if (targetDemo) {
      const user = await DatabaseService.getUserByPhone(targetDemo.phone);
      if (user) {
        setCurrentUser(user);
        await AsyncStorage.setItem(CURRENT_USER_KEY, user.phone);
      }
    } else {
      currentUser.role = role;
      await DatabaseService.saveUser(currentUser);
      setCurrentUser({ ...currentUser });
    }
  };

  return (
    <AuthContext.Provider
      value={{
        currentUser,
        isLoading,
        login,
        register,
        logout,
        refreshUser,
        switchRole,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
