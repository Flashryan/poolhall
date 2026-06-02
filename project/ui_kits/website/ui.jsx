/* Poolhall UI kit — primitives, icons, header, footer */
const { useState, useEffect, useRef } = React;

/* ---- Icon: renders a Lucide icon (CDN icon set) ---- */
function Icon({ name, className = "", style }) {
  const ref = useRef(null);
  useEffect(() => {
    const el = ref.current;
    if (el && window.lucide) {
      el.innerHTML = '<i data-lucide="' + name + '"></i>';
      window.lucide.createIcons();
    }
  });
  return <span ref={ref} className={"ico " + className} style={{ display: "inline-flex", ...style }} />;
}

function Button({ variant = "primary", size, icon, iconRight, busy, children, className = "", ...rest }) {
  const cls = ["btn", "btn-" + variant, size === "lg" ? "btn-lg" : "", className].join(" ");
  return (
    <button className={cls} disabled={busy || rest.disabled} {...rest}>
      {busy && <span className="spinner" />}
      {!busy && icon && <Icon name={icon} />}
      {children}
      {!busy && iconRight && <Icon name={iconRight} />}
    </button>
  );
}

const NAV = [
  { id: "home", label: "Home" },
  { id: "jobs", label: "Find a Job" },
  { id: "employers", label: "Employers" },
  { id: "sectors", label: "Sectors" },
  { id: "team", label: "Meet the Team" },
  { id: "contact", label: "Contact" },
];

function Header({ route, go, path, setPath }) {
  return (
    <header className="site-header">
      <div className="topbar">
        <div className="container">
          <div className="seg">
            <a href="#" onClick={e => e.preventDefault()}><Icon name="phone" /> 0121 516 3000</a>
            <a href="#" onClick={e => e.preventDefault()}><Icon name="mail" /> jobs@poolhallrecruitment.co.uk</a>
          </div>
          <div className="seg">
            <a href="#" onClick={e => e.preventDefault()}><Icon name="map-pin" /> Birmingham · West Midlands</a>
          </div>
        </div>
      </div>
      <div className="container">
        <nav className="nav">
          <a className="brand" href="#" onClick={e => { e.preventDefault(); go("home"); }}>
            <img src={window.PH_LOGO} alt="Poolhall Recruitment" />
            <span>
              <span className="bn">Poolhall</span><br />
              <span className="bs">Recruitment</span>
            </span>
          </a>
          <div className="nav-links">
            {NAV.map(n => (
              <a key={n.id} href="#" className={route === n.id ? "active" : ""}
                 onClick={e => { e.preventDefault(); go(n.id); }}>{n.label}</a>
            ))}
          </div>
          <div className="nav-cta">
            <div className="path-switch">
              <button className={path === "candidate" ? "on" : ""} onClick={() => { setPath("candidate"); go("jobs"); }}>
                <Icon name="user-round" /> Candidates
              </button>
              <button className={path === "employer" ? "on" : ""} onClick={() => { setPath("employer"); go("employers"); }}>
                <Icon name="building-2" /> Employers
              </button>
            </div>
            <Button variant="primary" onClick={() => go(path === "employer" ? "employers" : "jobs")}>
              {path === "employer" ? "Hire talent" : "Browse jobs"}
            </Button>
          </div>
        </nav>
      </div>
    </header>
  );
}

function Footer({ go }) {
  return (
    <footer className="site-footer">
      <div className="container">
        <div className="footer-grid">
          <div>
            <div className="footer-brand">
              <img src={window.PH_LOGO} alt="Poolhall Recruitment" />
              <span className="bn">Poolhall</span>
            </div>
            <p className="footer-about">Independent recruitment across Construction, Manufacturing and Marketing. Quality and ethical solutions since 2021.</p>
            <div className="footer-soc">
              <a href="#" onClick={e => e.preventDefault()} aria-label="LinkedIn">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.22.79 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg>
              </a>
              <a href="#" onClick={e => e.preventDefault()} aria-label="Instagram">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.3-1.46.72-2.12 1.38C1.35 2.67.93 3.34.63 4.14c-.3.76-.5 1.64-.56 2.9C.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.3.79.72 1.46 1.38 2.12.66.66 1.33 1.08 2.12 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56a5.85 5.85 0 0 0 2.12-1.38 5.85 5.85 0 0 0 1.38-2.12c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91a5.85 5.85 0 0 0-1.38-2.12A5.85 5.85 0 0 0 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.41-10.85a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z"/></svg>
              </a>
            </div>
          </div>
          <div>
            <h5>Candidates</h5>
            <ul>
              <li><a href="#" onClick={e => { e.preventDefault(); go("jobs"); }}>Find a job</a></li>
              <li><a href="#" onClick={e => { e.preventDefault(); go("jobs"); }}>Browse sectors</a></li>
              <li><a href="#" onClick={e => e.preventDefault()}>Register your CV</a></li>
              <li><a href="#" onClick={e => e.preventDefault()}>Career advice</a></li>
            </ul>
          </div>
          <div>
            <h5>Employers</h5>
            <ul>
              <li><a href="#" onClick={e => { e.preventDefault(); go("employers"); }}>Hire talent</a></li>
              <li><a href="#" onClick={e => e.preventDefault()}>Our services</a></li>
              <li><a href="#" onClick={e => e.preventDefault()}>Job advertising</a></li>
              <li><a href="#" onClick={e => e.preventDefault()}>Partner with us</a></li>
            </ul>
          </div>
          <div>
            <h5>Company</h5>
            <ul>
              <li><a href="#" onClick={e => { e.preventDefault(); go("team"); }}>Meet the team</a></li>
              <li><a href="#" onClick={e => e.preventDefault()}>About us</a></li>
              <li><a href="#" onClick={e => { e.preventDefault(); go("contact"); }}>Contact</a></li>
              <li><a href="#" onClick={e => e.preventDefault()}>Privacy policy</a></li>
            </ul>
          </div>
        </div>

        <div className="footer-accred">
          <span className="fa-label">Proud members of</span>
          <div className="fa-logos">
            {window.PH_ACCRED.map(a => (
              <span className="fa-chip" key={a.name} title={a.name}>
                <img src={a.img} alt={a.name} />
              </span>
            ))}
          </div>
        </div>

        <div className="footer-bottom">
          <span>© 2026 Poolhall Recruitment Limited · Company No. 13319338 · VAT 383617377</span>
          <span>Grosvenor House, 11 St Pauls Square, Birmingham, B3 1RB</span>
        </div>
      </div>
    </footer>
  );
}

Object.assign(window, { Icon, Button, Header, Footer, NAV });
