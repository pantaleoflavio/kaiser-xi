export function positionPointsToRows<T>(positionPoints: Record<string, T>): T[] {
  return Object.entries(positionPoints)
    .sort(([left], [right]) => Number(left) - Number(right))
    .map(([, points]) => points);
}

export function positionPointRowsToMap<T>(rows: readonly T[]): Record<string, T> {
  return Object.fromEntries(rows.map((points, index) => [String(index + 1), points]));
}
