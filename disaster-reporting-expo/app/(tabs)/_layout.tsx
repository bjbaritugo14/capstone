import { Ionicons } from '@expo/vector-icons';
import { Redirect, Tabs, useRouter } from 'expo-router';
import { useAuth } from '../../src/context/AuthContext';

export default function TabsLayout() {
  const router = useRouter();
  const { isAuthenticated, user } = useAuth();

  if (!isAuthenticated || user?.role !== 'field_officer') {
    return <Redirect href="/login" />;
  }

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: '#2563eb',
        headerStyle: { backgroundColor: '#ffffff' },
        headerTitleStyle: { fontWeight: '700' },
      }}
    >
      <Tabs.Screen
        name="dashboard"
        options={{
          title: 'Disaster Reports',
          tabBarIcon: ({ color, size }) => <Ionicons name="grid-outline" color={color} size={size} />,
        }}
      />
      <Tabs.Screen
        name="report-form"
        listeners={{
          tabPress: (event) => {
            event.preventDefault();
            router.navigate({
              pathname: '/(tabs)/report-form',
              params: { id: '', newEntry: String(Date.now()) },
            });
          },
        }}
        options={{
          title: 'New Disaster',
          tabBarIcon: ({ color, size }) => <Ionicons name="create-outline" color={color} size={size} />,
        }}
      />
      <Tabs.Screen
        name="vehicular-accidents"
        options={{
          title: 'Accidents',
          tabBarIcon: ({ color, size }) => <Ionicons name="car-outline" color={color} size={size} />,
        }}
      />
      <Tabs.Screen
        name="accident-form"
        options={{
          href: null,
          title: 'New Accident',
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profile',
          tabBarIcon: ({ color, size }) => <Ionicons name="person-outline" color={color} size={size} />,
        }}
      />
    </Tabs>
  );
}
