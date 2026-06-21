import { Redirect } from 'expo-router';
import { useAuth } from '../src/context/AuthContext';

export default function IndexPage() {
  const { isAuthenticated } = useAuth();

  return <Redirect href={isAuthenticated ? '/(tabs)/dashboard' : '/login'} />;
}
