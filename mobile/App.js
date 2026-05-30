import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Button,
  FlatList,
  SafeAreaView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { api, setAuthToken } from './src/api';
import {
  addNotificationReceivedListener,
  registerForPushNotifications,
  unregisterPushToken,
} from './src/notifications';

const TABS = ['Apartments', 'Bookings', 'Payments', 'Profile'];

export default function App() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [token, setToken] = useState(null);
  const [pushToken, setPushToken] = useState(null);
  const [tab, setTab] = useState('Apartments');
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [user, setUser] = useState(null);

  useEffect(() => {
    const sub = addNotificationReceivedListener((n) => {
      Alert.alert(n.request.content.title || 'Rainier', n.request.content.body || '');
      loadTab(tab);
    });
    return () => sub.remove();
  }, [tab]);

  async function handleLogin() {
    setLoading(true);
    try {
      const login = await api('/api/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      });
      const jwt = login.data.token;
      const u = login.data.user;
      if (!u?.roles?.includes('ROLE_CUSTOMER')) {
        throw new Error('Customer accounts only.');
      }
      setToken(jwt);
      setAuthToken(jwt);
      setUser(u);
      const pt = await registerForPushNotifications();
      setPushToken(pt);
      await loadTab('Apartments');
    } catch (e) {
      Alert.alert('Login failed', e.message);
    } finally {
      setLoading(false);
    }
  }

  async function handleLogout() {
    await unregisterPushToken(pushToken);
    setToken(null);
    setAuthToken(null);
    setUser(null);
    setPushToken(null);
    setItems([]);
  }

  async function loadTab(name) {
    if (!token) return;
    setLoading(true);
    try {
      if (name === 'Apartments') {
        const res = await api('/api/customer/apartments');
        setItems(res.data.items || []);
      } else if (name === 'Bookings') {
        const res = await api('/api/customer/bookings');
        setItems(res.data.items || []);
      } else if (name === 'Payments') {
        const res = await api('/api/customer/payments');
        setItems(res.data.items || []);
      } else if (name === 'Profile') {
        const res = await api('/api/customer/profile');
        setItems([res.data]);
      }
    } catch (e) {
      Alert.alert('Error', e.message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (token) loadTab(tab);
  }, [tab, token]);

  if (!token) {
    return (
      <SafeAreaView style={styles.container}>
        <Text style={styles.title}>Rainier Rentals</Text>
        <TextInput style={styles.input} placeholder="Email" autoCapitalize="none" value={email} onChangeText={setEmail} />
        <TextInput style={styles.input} placeholder="Password" secureTextEntry value={password} onChangeText={setPassword} />
        <Button title={loading ? 'Signing in…' : 'Sign in'} onPress={handleLogin} disabled={loading} />
        <StatusBar style="light" />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Hi, {user?.fullName || user?.email}</Text>
        <Button title="Logout" onPress={handleLogout} />
      </View>
      <View style={styles.tabs}>
        {TABS.map((t) => (
          <TouchableOpacity key={t} style={[styles.tab, tab === t && styles.tabActive]} onPress={() => setTab(t)}>
            <Text style={styles.tabText}>{t}</Text>
          </TouchableOpacity>
        ))}
      </View>
      {loading ? (
        <ActivityIndicator size="large" color="#20b2aa" />
      ) : (
        <FlatList
          data={items}
          keyExtractor={(_, i) => String(i)}
          renderItem={({ item }) => (
            <View style={styles.card}>
              <Text style={styles.cardTitle}>{item.name || item.apartment || item.email || 'Item'}</Text>
              <Text style={styles.cardMeta}>
                {item.address || item.status || item.amount ? `₱${item.amount}` : JSON.stringify(item.user?.email || '')}
              </Text>
            </View>
          )}
          ListEmptyComponent={<Text style={styles.empty}>Nothing here yet.</Text>}
        />
      )}
      <StatusBar style="light" />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0a1a2e', padding: 16 },
  title: { color: '#fff', fontSize: 22, fontWeight: '700', marginBottom: 12 },
  input: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginBottom: 10 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  tabs: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 12 },
  tab: { paddingHorizontal: 12, paddingVertical: 8, borderRadius: 20, borderWidth: 1, borderColor: '#20b2aa' },
  tabActive: { backgroundColor: '#20b2aa' },
  tabText: { color: '#fff', fontSize: 12 },
  card: { backgroundColor: '#16213e', padding: 14, borderRadius: 10, marginBottom: 10 },
  cardTitle: { color: '#fff', fontWeight: '600', fontSize: 16 },
  cardMeta: { color: '#cbd5e1', marginTop: 4 },
  empty: { color: '#94a3b8', textAlign: 'center', marginTop: 24 },
});
