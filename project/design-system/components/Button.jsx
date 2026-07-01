import * as React from "react";

const VARIANTS = {
  primary: { background: "var(--hivis-500, #FDBB5D)", color: "var(--on-accent, #0B2846)", border: "1.5px solid transparent" },
  navy: { background: "var(--slate-900, #0B2846)", color: "#fff", border: "1.5px solid transparent" },
  ghost: { background: "transparent", color: "var(--slate-900, #0B2846)", border: "1.5px solid var(--border-strong, #C2CBD2)" },
  "ghost-light": { background: "rgba(255,255,255,.08)", color: "#fff", border: "1.5px solid rgba(255,255,255,.4)" },
};
const HOVER = {
  primary: "var(--hivis-600, #E0A33F)",
  navy: "var(--slate-700, #1B4068)",
  ghost: "var(--bg-3, #ECEFF2)",
  "ghost-light": "rgba(255,255,255,.16)",
};

/* Renders a Lucide icon by name (expects the global `lucide` UMD to be present). */
function Glyph({ name }) {
  const ref = React.useRef(null);
  React.useEffect(() => {
    if (ref.current && window.lucide) {
      ref.current.innerHTML = '<i data-lucide="' + name + '"></i>';
      window.lucide.createIcons();
    }
  }, [name]);
  return React.createElement("span", { ref, style: { display: "inline-flex", width: 17, height: 17 } });
}

export function Button({ variant = "primary", size = "md", icon, iconRight, children, onClick, ...rest }) {
  const [hover, setHover] = React.useState(false);
  const base = VARIANTS[variant] || VARIANTS.primary;
  const style = {
    fontFamily: "var(--font-body, system-ui)",
    fontWeight: 700,
    fontSize: size === "lg" ? 16 : 15,
    lineHeight: 1,
    cursor: "pointer",
    display: "inline-flex",
    alignItems: "center",
    justifyContent: "center",
    gap: 9,
    borderRadius: "var(--r-sm, 4px)",
    padding: size === "lg" ? "16px 26px" : "13px 20px",
    transition: "background .15s ease, transform .05s ease",
    whiteSpace: "nowrap",
    ...base,
    background: hover ? (HOVER[variant] || base.background) : base.background,
  };
  return React.createElement(
    "button",
    {
      style,
      onClick,
      onMouseEnter: () => setHover(true),
      onMouseLeave: () => setHover(false),
      ...rest,
    },
    icon ? React.createElement(Glyph, { name: icon }) : null,
    children,
    iconRight ? React.createElement(Glyph, { name: iconRight }) : null
  );
}
