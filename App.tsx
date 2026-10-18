import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  StyleSheet,
  View,
  Modal,
  Text,
  TouchableOpacity,
  ScrollView,
} from 'react-native';
import { StatusBar as ExpoStatusBar } from 'expo-status-bar';
import { Ionicons } from '@expo/vector-icons';
import { AuthProvider, useAuth } from './src/services/authContext';
import { Header } from './src/components/Header';
import { TabBar, TabKey } from './src/components/TabBar';
import { HomeScreen } from './src/screens/HomeScreen';
import { BuyScreen } from './src/screens/BuyScreen';
import { SellScreen } from './src/screens/SellScreen';
import { AgentScreen } from './src/screens/AgentScreen';
import { AccountsScreen } from './src/screens/AccountsScreen';
import { OrdersScreen } from './src/screens/OrdersScreen';
import { ProfileScreen } from './src/screens/ProfileScreen';
import { AuthScreen } from './src/screens/AuthScreen';
import { Colors } from './src/theme/colors';
import { DatabaseService } from './src/services/supabase';
import { Announcement } from './src/types';

function MainApp() {
  const { currentUser, isLoading } = useAuth();
  const [activeTab, setActiveTab] = useState<TabKey>('home');
  const [activeSubView, setActiveSubView] = useState<'profile' | 'accounts' | 'orders' | null>(null);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [isAnnouncementsModalOpen, setIsAnnouncementsModalOpen] = useState(false);

  useEffect(() => {
    loadAnnouncements();
  }, []);

  const loadAnnouncements = async () => {
    const list = await DatabaseService.getAnnouncements();
    setAnnouncements(list);
  };

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ExpoStatusBar style="light" />
        <Text style={styles.loadingText}>NOVA PAY</Text>
      </View>
    );
  }

  if (!currentUser) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <ExpoStatusBar style="light" />
        <AuthScreen />
      </SafeAreaView>
    );
  }

  const renderContent = () => {
    // If a subview is requested
    if (activeSubView === 'accounts') {
      return <AccountsScreen />;
    }
    if (activeSubView === 'orders') {
      return <OrdersScreen />;
    }
    if (activeSubView === 'profile') {
      return (
        <ProfileScreen
          onOpenAccounts={() => setActiveSubView('accounts')}
          onOpenOrders={() => setActiveSubView('orders')}
        />
      );
    }

    switch (activeTab) {
      case 'home':
        return (
          <HomeScreen
            onNavigate={(tab) => {
              setActiveSubView(null);
              setActiveTab(tab);
            }}
            onOpenAccounts={() => setActiveSubView('accounts')}
          />
        );
      case 'buy':
        return <BuyScreen onSuccessOrder={() => setActiveTab('home')} />;
      case 'sell':
        return <SellScreen onOpenAccounts={() => setActiveSubView('accounts')} />;
      case 'agent':
        return <AgentScreen />;
      case 'more':
        return (
          <ProfileScreen
            onOpenAccounts={() => setActiveSubView('accounts')}
            onOpenOrders={() => setActiveSubView('orders')}
          />
        );
      default:
        return <HomeScreen onNavigate={setActiveTab} onOpenAccounts={() => setActiveSubView('accounts')} />;
    }
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <ExpoStatusBar style="light" />

      {/* SUBVIEW TOP BAR OR HEADER */}
      {activeSubView ? (
        <View style={styles.subviewHeader}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => setActiveSubView(null)}
          >
            <Ionicons name="arrow-back" size={22} color={Colors.text} />
            <Text style={styles.backButtonText}>Back</Text>
          </TouchableOpacity>
          <Text style={styles.subviewTitle}>
            {activeSubView === 'accounts'
              ? 'UPI Accounts'
              : activeSubView === 'orders'
              ? 'Order History'
              : 'My Profile'}
          </Text>
          <View style={{ width: 60 }} />
        </View>
      ) : (
        <Header
          onOpenProfile={() => setActiveSubView('profile')}
          onOpenAnnouncements={() => setIsAnnouncementsModalOpen(true)}
        />
      )}

      {/* SCREEN BODY */}
      <View style={styles.body}>{renderContent()}</View>

      {/* BOTTOM TAB BAR */}
      <TabBar
        activeTab={activeSubView ? 'more' : activeTab}
        onTabChange={(tab) => {
          setActiveSubView(null);
          setActiveTab(tab);
        }}
        isAgent={currentUser.role === 'agent'}
      />

      {/* ANNOUNCEMENTS MODAL */}
      <Modal
        visible={isAnnouncementsModalOpen}
        transparent
        animationType="fade"
        onRequestClose={() => setIsAnnouncementsModalOpen(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <View style={styles.modalTitleRow}>
                <Ionicons name="notifications" size={20} color={Colors.primary} />
                <Text style={styles.modalTitle}>Announcements</Text>
              </View>
              <TouchableOpacity onPress={() => setIsAnnouncementsModalOpen(false)}>
                <Ionicons name="close" size={24} color={Colors.textDim} />
              </TouchableOpacity>
            </View>

            <ScrollView style={{ maxHeight: 360 }}>
              {announcements.map((a) => (
                <View key={a.id} style={styles.announcementItem}>
                  <View style={styles.announcementTop}>
                    <Text style={styles.announcementTitle}>{a.title}</Text>
                    {a.badge && (
                      <View style={styles.announcementBadge}>
                        <Text style={styles.badgeText}>{a.badge}</Text>
                      </View>
                    )}
                  </View>
                  <Text style={styles.announcementBody}>{a.body}</Text>
                  <Text style={styles.announcementTime}>
                    {new Date(a.created_at).toLocaleDateString()}
                  </Text>
                </View>
              ))}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <MainApp />
    </AuthProvider>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  body: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  loadingContainer: {
    flex: 1,
    backgroundColor: Colors.background,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingText: {
    fontSize: 24,
    fontWeight: '900',
    color: Colors.primary,
    letterSpacing: 2,
  },
  subviewHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 14,
    backgroundColor: Colors.background,
    borderBottomWidth: 1,
    borderBottomColor: Colors.surfaceBorder,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    width: 60,
  },
  backButtonText: {
    color: Colors.text,
    fontSize: 14,
    fontWeight: '700',
  },
  subviewTitle: {
    color: Colors.text,
    fontSize: 16,
    fontWeight: '800',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.75)',
    justifyContent: 'center',
    padding: 20,
  },
  modalCard: {
    backgroundColor: Colors.surface,
    borderRadius: 18,
    padding: 20,
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  modalTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '900',
    color: Colors.text,
  },
  announcementItem: {
    backgroundColor: Colors.backgroundSecondary,
    padding: 12,
    borderRadius: 10,
    marginBottom: 10,
  },
  announcementTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  announcementTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: Colors.text,
    flex: 1,
  },
  announcementBadge: {
    backgroundColor: 'rgba(0, 240, 255, 0.2)',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    marginLeft: 6,
  },
  badgeText: {
    color: Colors.primary,
    fontSize: 9,
    fontWeight: '800',
  },
  announcementBody: {
    fontSize: 11,
    color: Colors.textMuted,
    lineHeight: 16,
  },
  announcementTime: {
    fontSize: 9,
    color: Colors.textDim,
    marginTop: 6,
  },
});
