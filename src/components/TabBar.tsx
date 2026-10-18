import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Platform } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';

export type TabKey = 'home' | 'buy' | 'sell' | 'agent' | 'more';

interface TabBarProps {
  activeTab: TabKey;
  onTabChange: (tab: TabKey) => void;
  isAgent?: boolean;
}

export const TabBar: React.FC<TabBarProps> = ({ activeTab, onTabChange, isAgent }) => {
  const tabs: { key: TabKey; label: string; icon: keyof typeof Ionicons.glyphMap; activeIcon: keyof typeof Ionicons.glyphMap }[] = [
    { key: 'home', label: 'Home', icon: 'wallet-outline', activeIcon: 'wallet' },
    { key: 'buy', label: 'Deposit', icon: 'add-circle-outline', activeIcon: 'add-circle' },
    { key: 'sell', label: 'Withdraw', icon: 'cash-outline', activeIcon: 'cash' },
    { key: 'agent', label: isAgent ? 'Agent VIP' : 'Commission', icon: 'people-outline', activeIcon: 'people' },
    { key: 'more', label: 'Hub', icon: 'grid-outline', activeIcon: 'grid' },
  ];

  return (
    <View style={styles.container}>
      {tabs.map((tab) => {
        const isActive = activeTab === tab.key;
        return (
          <TouchableOpacity
            key={tab.key}
            style={styles.tabButton}
            onPress={() => onTabChange(tab.key)}
            activeOpacity={0.7}
          >
            {isActive && <View style={styles.activeGlow} />}
            <Ionicons
              name={isActive ? tab.activeIcon : tab.icon}
              size={22}
              color={isActive ? Colors.primary : Colors.textMuted}
            />
            <Text style={[styles.tabLabel, isActive && styles.activeTabLabel]}>
              {tab.label}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    backgroundColor: '#0A0E17',
    borderTopWidth: 1,
    borderTopColor: Colors.surfaceBorder,
    paddingVertical: 8,
    paddingBottom: Platform.OS === 'ios' ? 24 : 10,
    elevation: 8,
    justifyContent: 'space-around',
    alignItems: 'center',
  },
  tabButton: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 4,
    minWidth: 58,
    position: 'relative',
  },
  activeGlow: {
    position: 'absolute',
    top: -8,
    width: 24,
    height: 3,
    backgroundColor: Colors.primary,
    borderRadius: 2,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.8,
    shadowRadius: 4,
  },
  tabLabel: {
    fontSize: 10,
    fontWeight: '600',
    color: Colors.textMuted,
    marginTop: 4,
  },
  activeTabLabel: {
    color: Colors.primary,
    fontWeight: '800',
  },
});
