import React from 'react';
import { View, Text, StyleSheet, Image, TouchableOpacity } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Colors } from '../theme/colors';
import { useAuth } from '../services/authContext';

interface HeaderProps {
  onOpenProfile?: () => void;
  onOpenAnnouncements?: () => void;
}

export const Header: React.FC<HeaderProps> = ({ onOpenProfile, onOpenAnnouncements }) => {
  const { currentUser } = useAuth();

  return (
    <View style={styles.container}>
      <View style={styles.leftSection}>
        <Image
          source={require('../../assets/icon.png')}
          style={styles.logo}
          resizeMode="contain"
        />
        <View style={styles.titleContainer}>
          <Text style={styles.title}>NOVA PAY</Text>
          <View style={styles.badgeRow}>
            <View style={styles.liveDot} />
            <Text style={styles.roleBadge}>
              {currentUser?.role?.toUpperCase() || 'MEMBER'}
            </Text>
          </View>
        </View>
      </View>

      <View style={styles.rightSection}>
        {currentUser?.role === 'agent' && (
          <View style={styles.agentTag}>
            <Text style={styles.agentTagText}>VIP AGENT</Text>
          </View>
        )}

        <TouchableOpacity 
          style={styles.iconButton} 
          onPress={onOpenAnnouncements}
          activeOpacity={0.7}
        >
          <Ionicons name="notifications-outline" size={20} color={Colors.text} />
          <View style={styles.notificationDot} />
        </TouchableOpacity>

        <TouchableOpacity 
          style={styles.iconButton} 
          onPress={onOpenProfile}
          activeOpacity={0.7}
        >
          <Ionicons name="person-circle-outline" size={24} color={Colors.primary} />
        </TouchableOpacity>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 12,
    backgroundColor: Colors.background,
    borderBottomWidth: 1,
    borderBottomColor: Colors.surfaceBorder,
  },
  leftSection: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  logo: {
    width: 40,
    height: 40,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: Colors.primary,
  },
  titleContainer: {
    marginLeft: 10,
  },
  title: {
    fontSize: 18,
    fontWeight: '800',
    color: Colors.text,
    letterSpacing: 1.2,
  },
  badgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 2,
  },
  liveDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: Colors.accent,
    marginRight: 4,
  },
  roleBadge: {
    fontSize: 10,
    fontWeight: '700',
    color: Colors.textMuted,
    letterSpacing: 0.8,
  },
  rightSection: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  agentTag: {
    backgroundColor: 'rgba(245, 158, 11, 0.15)',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: Colors.gold,
  },
  agentTagText: {
    color: Colors.gold,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  iconButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: Colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
    borderWidth: 1,
    borderColor: Colors.surfaceBorder,
  },
  notificationDot: {
    position: 'absolute',
    top: 8,
    right: 8,
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: Colors.ruby,
  },
});
