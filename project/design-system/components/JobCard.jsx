import * as React from "react";

function Glyph({ name, size = 15, color = "var(--steel-400, #8A99A6)" }) {
  const ref = React.useRef(null);
  React.useEffect(() => {
    if (ref.current && window.lucide) {
      ref.current.innerHTML = '<i data-lucide="' + name + '"></i>';
      window.lucide.createIcons();
    }
  }, [name]);
  return React.createElement("span", { ref, style: { display: "inline-flex", width: size, height: size, color } });
}

function gbp(n) { return "£" + n.toLocaleString("en-GB"); }
function salary(j) { return j.salaryMin === j.salaryMax ? gbp(j.salaryMin) : gbp(j.salaryMin) + "–" + gbp(j.salaryMax); }

export function JobCard({ job, onOpen }) {
  const [hover, setHover] = React.useState(false);
  const card = {
    background: "#fff",
    border: "1px solid var(--border, #E2E7EB)",
    borderTop: job.featured ? "3px solid var(--hivis-500, #FDBB5D)" : "1px solid var(--border, #E2E7EB)",
    borderRadius: "var(--r, 6px)",
    boxShadow: hover ? "var(--shadow-md, 0 8px 20px rgba(14,22,30,.12))" : "var(--shadow-sm, 0 2px 6px rgba(14,22,30,.08))",
    transform: hover ? "translateY(-3px)" : "none",
    transition: "box-shadow .18s, transform .18s",
    padding: "22px 24px",
    cursor: "pointer",
    display: "flex",
    flexDirection: "column",
    fontFamily: "var(--font-body, system-ui)",
  };
  const meta = { display: "flex", flexWrap: "wrap", gap: "8px 16px", margin: "14px 0", color: "var(--fg-2, #51606B)", fontFamily: "var(--font-mono, monospace)", fontSize: 12.5 };
  const metaItem = { display: "inline-flex", alignItems: "center", gap: 6 };
  return React.createElement(
    "article",
    { style: card, onMouseEnter: () => setHover(true), onMouseLeave: () => setHover(false), onClick: () => onOpen && onOpen(job) },
    React.createElement(
      "div",
      { style: { display: "flex", justifyContent: "space-between", alignItems: "flex-start", gap: 12 } },
      React.createElement(
        "div",
        null,
        React.createElement("div", { style: { fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "var(--accent-ink, #8A5E12)" } }, job.sector),
        React.createElement("h3", { style: { fontFamily: "var(--font-display, system-ui)", fontWeight: 800, fontSize: "1.3rem", color: "var(--slate-900, #0B2846)", margin: "6px 0 0", lineHeight: 1.2 } }, job.title)
      ),
      job.featured
        ? React.createElement("span", { style: { display: "inline-flex", alignItems: "center", gap: 5, fontSize: 11, fontWeight: 700, color: "var(--accent-ink, #8A5E12)", background: "var(--hivis-50, #FEF8EC)", padding: "4px 10px", borderRadius: 999, whiteSpace: "nowrap" } }, React.createElement(Glyph, { name: "star", size: 12, color: "var(--accent-ink, #8A5E12)" }), "Featured")
        : null
    ),
    React.createElement(
      "div",
      { style: meta },
      React.createElement("span", { style: metaItem }, React.createElement(Glyph, { name: "map-pin" }), job.location),
      React.createElement("span", { style: metaItem }, React.createElement(Glyph, { name: "briefcase" }), job.type),
      React.createElement("span", { style: metaItem }, React.createElement(Glyph, { name: "home" }), job.work),
      React.createElement("span", { style: metaItem }, React.createElement(Glyph, { name: "clock" }), job.posted)
    ),
    job.summary ? React.createElement("p", { style: { color: "var(--fg-2, #51606B)", fontSize: 14, lineHeight: 1.55, margin: "0 0 18px" } }, job.summary) : null,
    React.createElement(
      "div",
      { style: { display: "flex", justifyContent: "space-between", alignItems: "center", borderTop: "1px solid var(--bg-3, #ECEFF2)", paddingTop: 16, marginTop: "auto" } },
      React.createElement("div", { style: { fontFamily: "var(--font-display, system-ui)", fontWeight: 800, fontSize: "1.2rem", color: "var(--slate-900, #0B2846)" } }, salary(job), React.createElement("span", { style: { fontFamily: "var(--font-body, system-ui)", fontWeight: 500, fontSize: 12, color: "var(--fg-3, #8A99A6)" } }, " / year")),
      React.createElement("span", { style: { color: "var(--accent-ink, #8A5E12)", fontWeight: 700, fontSize: 14, display: "inline-flex", alignItems: "center", gap: 6 } }, "View job", React.createElement(Glyph, { name: "arrow-right", size: 16, color: "var(--accent-ink, #8A5E12)" }))
    )
  );
}
