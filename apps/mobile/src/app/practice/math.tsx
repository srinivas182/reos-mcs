import { router, useLocalSearchParams } from "expo-router";
import {
  Calculator as CalculatorIcon,
  CircleCheck,
  Sigma,
} from "lucide-react-native";
import { useEffect, useState } from "react";
import { ActivityIndicator, View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet, useUnistyles } from "react-native-unistyles";

import { Calculator } from "@/features/practice/Calculator";
import {
  asMathKind,
  checkMathStep,
  loadMathKinds,
  type MathKind,
  type MathLabKind,
  type MathProblem,
  mathValue,
  newMathProblem,
} from "@/lib/practice";
import {
  Button,
  Callout,
  Card,
  Icon,
  ListRow,
  Screen,
  Text,
  TextField,
  TitleBar,
} from "@/ui";

type Done = { answer: string; right: boolean };

/**
 * Math lab (candidate screen 48, A9.2c): choose a kind, then work the
 * problem one step at a time. The server checks each value; a wrong one
 * brings the step's hint and Show the answer. A variant of the same kind
 * follows each solve. `kind` (from a missed math question) opens a problem
 * of that kind at once.
 */
export default function MathLabScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { kind: asked } = useLocalSearchParams<{ kind?: string }>();
  const [kinds, setKinds] = useState<MathKind[] | null>(null);
  const [problem, setProblem] = useState<MathProblem | null>(null);
  const [done, setDone] = useState<Done[]>([]);
  const [value, setValue] = useState("");
  const [hint, setHint] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [solved, setSolved] = useState(false);
  const [calculator, setCalculator] = useState(false);

  const open = async (kind: MathLabKind) => {
    const next = await newMathProblem(kind).catch(() => null);
    if (!next) return setError(t("practice.errors.save"));
    setProblem(next);
    setDone([]);
    setValue("");
    setHint(null);
    setError(null);
    setSolved(false);
  };

  useEffect(() => {
    void loadMathKinds()
      .then(setKinds)
      .catch(() => setKinds([]));
    // From a missed math question: straight into a problem of its kind.
    const first = asMathKind(asked);
    const timer = first ? setTimeout(() => void open(first), 0) : undefined;

    return () => clearTimeout(timer);
  }, [asked]); // eslint-disable-line react-hooks/exhaustive-deps

  const send = async (reveal: boolean) => {
    if (!problem) return;
    const number = reveal ? null : mathValue(value);
    if (!reveal && number === null)
      return setError(t("practice.math.errors.number"));
    const result = await checkMathStep(problem, done.length + 1, number).catch(
      () => null,
    );
    if (!result) return setError(t("practice.errors.save"));
    setError(null);
    if (result.answer !== null) {
      setDone((all) => [
        ...all,
        { answer: result.answer ?? "", right: result.correct },
      ]);
      setValue("");
      setHint(null);
    } else {
      setHint(result.hint);
    }
    if (result.solved) setSolved(true);
  };

  const current = problem?.steps[done.length];
  const kindName = (kind: string) => {
    const known = asMathKind(kind);

    return known ? t(`practice.math.kinds.${known}`) : kind;
  };

  return (
    <Screen>
      <TitleBar
        title={t("practice.math.title")}
        onBack={() => (problem ? setProblem(null) : router.back())}
      />
      {!problem ? (
        <>
          <Text tone="muted">{t("practice.math.description")}</Text>
          {kinds === null ? (
            <ActivityIndicator color={theme.colors.action} />
          ) : null}
          {(kinds ?? []).map((k) => {
            const known = asMathKind(k.kind);

            return known ? (
              <ListRow
                key={k.kind}
                icon={Sigma}
                tone="info"
                title={t(`practice.math.kinds.${known}`)}
                subtitle={`${t(`practice.math.kindHints.${known}`)} · ${t("practice.math.solved", { count: k.solved })}`}
                onPress={() => void open(known)}
              />
            ) : null;
          })}
        </>
      ) : (
        <>
          <Text tone="muted">{kindName(problem.kind)}</Text>
          <Text variant="lead" weight="semibold">
            {problem.prompt}
          </Text>
          {done.map((d, i) => (
            <Card key={problem.steps[i]?.n ?? i}>
              <View style={styles.row}>
                <Icon icon={CircleCheck} color="toneSuccessFg" />
                <Text weight="semibold" style={styles.flex}>
                  {problem.steps[i]?.label}
                </Text>
              </View>
              <Text>
                {d.right
                  ? t("practice.math.right", { answer: d.answer })
                  : t("practice.math.shown", { answer: d.answer })}
              </Text>
            </Card>
          ))}
          {current && !solved ? (
            <Card>
              <Text variant="caption" tone="muted">
                {t("practice.math.step", {
                  n: current.n,
                  of: problem.steps.length,
                })}
              </Text>
              <TextField
                label={current.label}
                value={value}
                onChangeText={setValue}
                keyboardType="decimal-pad"
                autoComplete="off"
              />
              <Button
                label={t("practice.math.check")}
                disabled={value.trim() === ""}
                onPress={() => void send(false)}
                fullWidth
              />
              {hint ? (
                <Callout
                  tone="info"
                  title={t("practice.math.wrong")}
                  body={hint}
                  live
                  action={
                    <Button
                      variant="secondary"
                      label={t("practice.math.show")}
                      onPress={() => void send(true)}
                    />
                  }
                />
              ) : null}
            </Card>
          ) : null}
          {solved ? (
            <Callout
              tone="success"
              title={t("practice.math.solvedTitle")}
              body={t("practice.math.solvedBody")}
              live
              action={
                <Button
                  label={t("practice.math.variant")}
                  onPress={() => {
                    const k = asMathKind(problem.kind);
                    if (k) void open(k);
                  }}
                />
              }
            />
          ) : null}
          <View style={styles.row}>
            <Button
              variant="secondary"
              icon={CalculatorIcon}
              label={t("practice.math.calculator")}
              onPress={() => setCalculator(true)}
            />
            <Button
              variant="secondary"
              label={t("practice.math.choose")}
              onPress={() => setProblem(null)}
            />
          </View>
          <Calculator open={calculator} onClose={() => setCalculator(false)} />
        </>
      )}
      {error ? <Callout tone="danger" title={error} live /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: {
    flexDirection: "row",
    alignItems: "center",
    flexWrap: "wrap",
    gap: theme.space.xs,
  },
  flex: { flex: 1 },
}));
