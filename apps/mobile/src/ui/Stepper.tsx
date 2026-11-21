import { ChevronLeft, ChevronRight } from 'lucide-react-native';
import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { IconButton } from './Header';
import { Text } from './Text';

type StepperProps = {
  label: string;
  /** What is chosen now, in words: "Saturday, November 21". */
  value: string;
  /** Screen-reader names for the two buttons: "Day before", "Day after". */
  earlier: string;
  later: string;
  onEarlier: () => void;
  onLater: () => void;
};

/** A value moved in steps with two 48-point buttons (a day, 15 minutes), announced as it changes. */
export function Stepper({ label, value, earlier, later, onEarlier, onLater }: StepperProps) {
  return (
    <View style={styles.stepper}>
      <Text weight="semibold">{label}</Text>
      <View style={styles.row}>
        <IconButton icon={ChevronLeft} label={earlier} onPress={onEarlier} />
        <Text variant="bodyLarge" style={styles.value} accessibilityLabel={`${label}: ${value}`} accessibilityLiveRegion="polite">
          {value}
        </Text>
        <IconButton icon={ChevronRight} label={later} onPress={onLater} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  stepper: { gap: theme.space.xs },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  value: { flex: 1, textAlign: 'center' },
}));
