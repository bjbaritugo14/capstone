import { Ionicons } from '@expo/vector-icons';
import { Redirect, Tabs } from 'expo-router';
import { useAuth } from '../../src/context/AuthContext';

export default function TabsLayout() {
  const { isAuthenticated } = useAuth();

  if (!isAuthenticated) {
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
          title: 'Dashboard',
          tabBarIcon: ({ color, size }) => <Ionicons name="grid-outline" color={color} size={size} />,
        }}
      />
      <Tabs.Screen
        name="report-form"
        options={{
          title: 'Report',
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
          title: 'Accident Form',
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
