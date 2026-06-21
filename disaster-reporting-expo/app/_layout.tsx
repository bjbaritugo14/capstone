import 'react-native-reanimated';

import { Stack } from 'expo-router';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { AuthProvider } from '../src/context/AuthContext';
import { ReportProvider } from '../src/context/ReportContext';
import { VehicularAccidentProvider } from '../src/context/VehicularAccidentContext';

export default function RootLayout() {
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <AuthProvider>
        <ReportProvider>
          <VehicularAccidentProvider>
            <Stack screenOptions={{ headerShown: false }} />
          </VehicularAccidentProvider>
        </ReportProvider>
      </AuthProvider>
    </GestureHandlerRootView>
  );
}
