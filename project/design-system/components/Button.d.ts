import * as React from "react";

export interface ButtonProps {
  /** Visual style. */
  variant?: "primary" | "navy" | "ghost" | "ghost-light";
  /** Larger padding/type for hero CTAs. */
  size?: "md" | "lg";
  /** Lucide icon name shown before the label. */
  icon?: string;
  /** Lucide icon name shown after the label. */
  iconRight?: string;
  children?: React.ReactNode;
  onClick?: (e: React.MouseEvent) => void;
}

/** Poolhall primary action button — engineered, near-square, hi-vis primary. */
export function Button(props: ButtonProps): JSX.Element;
