/* Poolhall UI kit v2 — primitives, header, footer */
const { useState, useEffect, useRef } = React;

/* Lucide icon wrapper */
function Icon({ name, className = "", style }) {
  const ref = useRef(null);
  useEffect(() => {
    const el = ref.current;
    if (el && window.lucide) { el.innerHTML = '<i data-lucide="' + name + '"></i>'; window.lucide.createIcons(); }
  });
  return <span ref={ref} className={"ico " + className} style={{ display: "inline-flex", ...style }} />;
}

function Button({ variant = "primary", size, icon, iconRight, children, className = "", ...rest }) {
  const cls = ["btn", "btn-" + variant, size === "lg" ? "btn-lg" : "", className].join(" ");
  return <button className={cls} {...rest}>{icon && <Icon name={icon} />}{children}{iconRight && <Icon name={iconRight} />}</button>;
}

/* nav model */
const EMPLOYER_MENU = [
  { id: "why-us", label: "Why Us", icon: "badge-check" },
  { id: "delivery", label: "Recruitment Delivery Options", icon: "layers" },
  { id: "bespoke", label: "Bespoke Search", icon: "search-check" },
  { id: "bja", label: "Better Job Adverts", icon: "megaphone" },
  { id: "hr", label: "HR Services", icon: "briefcase" },
];
const SECTOR_MENU = [
  { id: "sector-con", label: "Construction", icon: "hard-hat" },
  { id: "sector-man", label: "Manufacturing", icon: "factory" },
  { id: "sector-dig", label: "Digital", icon: "monitor" },
];
const CANDIDATE_MENU = [
  { id: "register", label: "Register", icon: "user-plus" },
  { id: "jobs", label: "Jobs", icon: "briefcase" },
  { id: "guide-reg", label: "Registration Guide", icon: "clipboard-list" },
  { id: "guide-interview", label: "Interview Tips", icon: "messages-square" },
  { id: "guide-cv", label: "CV Tips", icon: "file-text" },
];

function Dropdown({ items, go, right }) {
  return (
    <div className={"dropdown" + (right ? " right" : "")}>
      {items.map(i => (
        <a key={i.id} href="#" onClick={e => { e.preventDefault(); go(i.id); }}>
          <Icon name={i.icon} /> {i.label}
        </a>
      ))}
    </div>
  );
}

function Header({ route, go }) {
  const [drawer, setDrawer] = useState(false);
  const nav = id => { setDrawer(false); go(id); };
  const isActive = r => route === r;
  return (
    <header className="site-header">
      <div className="topbar">
        <div className="container">
          <div className="seg">
            <a href="#" onClick={e => e.preventDefault()}><Icon name="phone" /> 0121 516 3000</a>
            <a className="hide-sm" href="#" onClick={e => e.preventDefault()}><Icon name="mail" /> hello@poolhallrecruitment.co.uk</a>
          </div>
          <div className="seg hide-sm">
            <a href="#" onClick={e => e.preventDefault()}><Icon name="map-pin" /> West Midlands · National reach</a>
          </div>
        </div>
      </div>
      <div className="container">
        <nav className="nav">
          <a className="brand" href="#" onClick={e => { e.preventDefault(); go("home"); }}>
            <img src={window.PH_LOGO || "../../assets/poolhall-logo.png"} alt="Poolhall Recruitment Limited" />
          </a>
          <div className="nav-links">
            <div className="nav-item"><button>Employers <Icon name="chevron-down" className="caret" /></button><Dropdown items={EMPLOYER_MENU} go={go} /></div>
            <div className="nav-item"><button>Sectors <Icon name="chevron-down" className="caret" /></button><Dropdown items={SECTOR_MENU} go={go} /></div>
            <div className="nav-item"><a href="#" className={isActive("jobs") ? "active" : ""} onClick={e => { e.preventDefault(); go("jobs"); }}>Jobs</a></div>
            <div className="nav-item"><button>Candidates <Icon name="chevron-down" className="caret" /></button><Dropdown items={CANDIDATE_MENU} go={go} /></div>
            <div className="nav-item"><a href="#" className={isActive("about") ? "active" : ""} onClick={e => { e.preventDefault(); go("about"); }}>About</a></div>
            <div className="nav-item"><a href="#" className={isActive("blog") ? "active" : ""} onClick={e => { e.preventDefault(); go("blog"); }}>Blog</a></div>
            <div className="nav-item"><a href="#" className={isActive("contact") ? "active" : ""} onClick={e => { e.preventDefault(); go("contact"); }}>Contact</a></div>
          </div>
          <div className="nav-spacer"></div>
          <div className="nav-cta">
            <Button variant="ghost" className="btn-find" onClick={() => go("candidate")}>Find work</Button>
            <Button variant="primary" iconRight="arrow-right" onClick={() => go("employers")}>Hire talent</Button>
            <button className="burger" onClick={() => setDrawer(true)} aria-label="Menu"><Icon name="menu" /></button>
          </div>
        </nav>
      </div>

      {/* mobile drawer — keeps Employers vs Candidates visibly separate */}
      <div className={"drawer-overlay" + (drawer ? " open" : "")} onClick={() => setDrawer(false)}></div>
      <div className={"drawer" + (drawer ? " open" : "")} role="dialog" aria-label="Menu">
        <div className="drawer-head">
          <span className="bn">POOLHALL</span>
          <button className="drawer-close" onClick={() => setDrawer(false)} aria-label="Close"><Icon name="x" /></button>
        </div>
        <div className="drawer-body">
          <div className="drawer-grp emp">
            <div className="gl">Employers — hire talent</div>
            <a onClick={() => nav("employers")}>Employers hub <Icon name="chevron-right" /></a>
            {EMPLOYER_MENU.map(i => <a key={i.id} onClick={() => nav(i.id)}>{i.label} <Icon name="chevron-right" /></a>)}
          </div>
          <div className="drawer-grp cand">
            <div className="gl">Candidates — find work</div>
            <a onClick={() => nav("candidate")}>Candidate hub <Icon name="chevron-right" /></a>
            {CANDIDATE_MENU.map(i => <a key={i.id} onClick={() => nav(i.id)}>{i.label} <Icon name="chevron-right" /></a>)}
          </div>
          <div className="drawer-grp">
            <div className="gl">Sectors</div>
            {SECTOR_MENU.map(i => <a key={i.id} onClick={() => nav(i.id)}>{i.label} <Icon name="chevron-right" /></a>)}
          </div>
          <div className="drawer-grp">
            <div className="gl">Company</div>
            <a onClick={() => nav("about")}>About us <Icon name="chevron-right" /></a>
            <a onClick={() => nav("team")}>Meet the team <Icon name="chevron-right" /></a>
            <a onClick={() => nav("blog")}>Blog <Icon name="chevron-right" /></a>
            <a onClick={() => nav("contact")}>Contact <Icon name="chevron-right" /></a>
          </div>
        </div>
        <div className="drawer-foot">
          <Button variant="ghost" onClick={() => nav("candidate")}>Find work</Button>
          <Button variant="primary" iconRight="arrow-right" onClick={() => nav("employers")}>Hire talent</Button>
        </div>
      </div>
    </header>
  );
}

function Footer({ go }) {
  const link = (id, label) => <li><a href="#" onClick={e => { e.preventDefault(); go(id); }}>{label}</a></li>;
  return (
    <footer className="site-footer">
      <div className="container">
        <div className="footer-top">
          <div className="footer-brand">
            <div className="fb"><img src={window.PH_LOGO || "../../assets/poolhall-logo.png"} alt="Poolhall Recruitment Limited" /></div>
            <p>West Midlands roots, national recruitment reach. Specialists in Construction, Manufacturing and Digital, since 2021.</p>
            <div className="footer-soc">
              <a href="#" onClick={e => e.preventDefault()} aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.22.79 24 1.77 24h20.45c.98 0 1.78-.78 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg></a>
              <a href="#" onClick={e => e.preventDefault()} aria-label="Instagram"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.3-1.46.72-2.12 1.38C1.35 2.67.93 3.34.63 4.14c-.3.76-.5 1.64-.56 2.9C.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.3.79.72 1.46 1.38 2.12.66.66 1.33 1.08 2.12 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56a5.85 5.85 0 0 0 2.12-1.38 5.85 5.85 0 0 0 1.38-2.12c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91a5.85 5.85 0 0 0-1.38-2.12A5.85 5.85 0 0 0 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.41-10.85a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z"/></svg></a>
            </div>
          </div>
          <div><h5>Sectors</h5><ul>{link("sector-con", "Construction")}{link("sector-man", "Manufacturing")}{link("sector-dig", "Digital")}{link("jobs", "All live jobs")}</ul></div>
          <div><h5>Employers</h5><ul>{link("why-us", "Why Us")}{link("delivery", "Delivery Options")}{link("bespoke", "Bespoke Search")}{link("bja", "Better Job Adverts")}{link("hr", "HR Services")}</ul></div>
          <div><h5>Candidates</h5><ul>{link("candidate", "Find work")}{link("register", "Register")}{link("guide-cv", "CV Tips")}{link("guide-interview", "Interview Tips")}</ul></div>
          <div><h5>Company</h5><ul>{link("about", "About us")}{link("team", "Meet the team")}{link("blog", "Blog")}{link("contact", "Contact")}{link("commitment", "Our Commitment")}</ul></div>
        </div>

        <div className="footer-accred">
          <span className="al">Proud members of</span>
          <div className="chips">
            {window.PH_ACCRED.map(a => <div className="chip" key={a.name} title={a.name}><img src={a.img} alt={a.name} /></div>)}
          </div>
        </div>

        <div className="footer-bottom">
          <span>© 2026 Poolhall Recruitment Ltd · Co. 13319338 · VAT 383617377 · Grosvenor House, 11 St Pauls Square, Birmingham B3 1RB</span>
          <span className="links">
            <a href="#" onClick={e => { e.preventDefault(); go("terms"); }}>Terms</a>
            <a href="#" onClick={e => { e.preventDefault(); go("privacy"); }}>Privacy</a>
            <a href="#" onClick={e => { e.preventDefault(); go("cookies"); }}>Cookies</a>
            <a href="#" onClick={e => { e.preventDefault(); go("commitment"); }}>Modern Slavery</a>
          </span>
        </div>
      </div>
    </footer>
  );
}

Object.assign(window, { Icon, Button, Header, Footer });
